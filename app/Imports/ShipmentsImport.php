<?php

namespace App\Imports;

use App\Jobs\ProcessNiposTrackingJob;
use App\Models\OutgoingShipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ShipmentsImport
{
    protected string $defaultSeller;
    protected ?\App\Services\TrackingBotService $botService = null;

    public function __construct(string $defaultSeller = 'Aliqa')
    {
        $this->defaultSeller = $defaultSeller;
        $this->botService = new \App\Services\TrackingBotService();
    }

    /**
     * Import Excel/CSV using OpenSpout Streaming Reader (<20MB RAM)
     * @return array Array of imported/updated tracking payloads ready for Google Sheets sync
     */
    public function importFile(string $filePath, string $defaultSeller = 'Aliqa'): array
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $this->defaultSeller = $defaultSeller;

        if (!file_exists($filePath)) {
            $msg = "ShipmentsImport: File not found at {$filePath}";
            Log::error($msg);
            return [];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $msg = "ShipmentsImport streaming start for {$filePath} [{$extension}]";
        Log::info($msg);

        if (in_array($extension, ['xlsx', 'csv'])) {
            return $this->streamWithOpenSpout($filePath, $extension);
        } else {
            // Fallback for .xls (BIFF8) or other formats using low-memory chunking
            return $this->streamWithPhpSpreadsheetChunking($filePath);
        }
    }

    /**
     * Ultra-fast OpenSpout streaming parser (< 20MB RAM) across MULTIPLE SHEETS (Compatible OpenSpout v4/v5)
     * @return array Array of imported/updated tracking payloads ready for Google Sheets sync
     */
    protected function streamWithOpenSpout(string $filePath, string $extension): array
    {
        $totalProcessedRows = 0;
        $totalInsertedRows = 0;
        $detectedHeaders = [];
        $sampleRow = [];
        $allUpdatedPayloads = [];

        try {
            if ($extension === 'csv') {
                $reader = new CSVReader();
            } else {
                $reader = new XLSXReader();
            }

            $reader->open($filePath);

            $batchBuffer = [];
            $resisInBuffer = [];
            $now = now()->toDateTimeString();

            // Iterate through ALL sheets in the Excel file
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetName = $sheet->getName();
                Log::info("ShipmentsImport: Reading Sheet -> {$sheetName}");

                // RESET header detection at the start of EVERY sheet
                $headerMap = [];
                $headerFound = false;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowArray = [];

                    // OpenSpout v4/v5 API compatibility: Use toArray() instead of getCells()
                    if (method_exists($row, 'toArray')) {
                        $rawCells = $row->toArray();
                        foreach ($rawCells as $idx => $cellVal) {
                            if ($cellVal instanceof \DateTimeInterface) {
                                $cellVal = $cellVal->format('Y-m-d');
                            }
                            $rowArray[$idx] = $cellVal;
                        }
                    } elseif (method_exists($row, 'getCells')) {
                        $cells = $row->getCells();
                        foreach ($cells as $idx => $cell) {
                            $val = method_exists($cell, 'getValue') ? $cell->getValue() : (string)$cell;
                            if ($val instanceof \DateTimeInterface) {
                                $val = $val->format('Y-m-d');
                            }
                            $rowArray[$idx] = $val;
                        }
                    } else {
                        $rowArray = (array)$row;
                    }

                    if (empty(array_filter($rowArray))) {
                        continue;
                    }

                    // Check if current row is a header row
                    if (!$headerFound) {
                        $possibleMap = $this->buildHeaderMap($rowArray);
                        if (!empty($possibleMap)) {
                            $headerMap = $possibleMap;
                            $headerFound = true;
                            $detectedHeaders = $rowArray;
                            Log::info("ShipmentsImport: Detected header map on sheet {$sheetName}: " . json_encode($headerMap));
                            continue;
                        }
                    }

                    if (empty($sampleRow)) {
                        $sampleRow = $rowArray;
                    }

                    $totalProcessedRows++;
                    $parsed = $this->parseRowArray($rowArray, $headerMap, $now, $sheetName);
                    if ($parsed !== null) {
                        $batchBuffer[] = $parsed;
                        $resisInBuffer[] = $parsed['no_resi'];
                    }

                    // Process in batches of 1,000 rows to keep memory ultra low (< 20MB)
                    if (count($batchBuffer) >= 1000) {
                        $syncedItems = $this->processBufferBatchWithPayloads($batchBuffer, $resisInBuffer);
                        $totalInsertedRows += count($syncedItems);
                        foreach ($syncedItems as $si) {
                            $allUpdatedPayloads[] = $si;
                        }
                        $batchBuffer = [];
                        $resisInBuffer = [];
                    }
                }
            }

            // Process remaining rows in buffer
            if (!empty($batchBuffer)) {
                $syncedItems = $this->processBufferBatchWithPayloads($batchBuffer, $resisInBuffer);
                $totalInsertedRows += count($syncedItems);
                foreach ($syncedItems as $si) {
                    $allUpdatedPayloads[] = $si;
                }
            }

            $reader->close();

            if ($totalInsertedRows === 0) {
                $msg = "Excel Import Failed: No valid rows found in any sheet. Headers detected: " . json_encode($detectedHeaders) . " | Sample row: " . json_encode($sampleRow);
                Log::error($msg);
            } else {
                Log::info("ShipmentsImport streaming finished. Processed {$totalProcessedRows} rows from all sheets, saved {$totalInsertedRows} rows into outgoing_shipments.");
            }
        } catch (Throwable $e) {
            Log::error("OpenSpout streaming error: " . $e->getMessage() . " - fallback to chunking.");
            return $this->streamWithPhpSpreadsheetChunking($filePath);
        }

        return $allUpdatedPayloads;
    }

    /**
     * Fallback PhpSpreadsheet chunking parser for .xls files across MULTIPLE SHEETS
     * @return array Array of imported/updated tracking payloads ready for Google Sheets sync
     */
    protected function streamWithPhpSpreadsheetChunking(string $filePath): array
    {
        $totalProcessedRows = 0;
        $totalInsertedRows = 0;
        $detectedHeaders = [];
        $sampleRow = [];
        $allUpdatedPayloads = [];

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($filePath);

            $batchBuffer = [];
            $resisInBuffer = [];
            $now = now()->toDateTimeString();

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetName = $sheet->getTitle();
                Log::info("ShipmentsImport: Reading Sheet (PhpSpreadsheet) -> {$sheetName}");

                $headerMap = [];
                $headerFound = false;

                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestDataColumn();
                $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                for ($rowNum = 1; $rowNum <= $highestRow; $rowNum++) {
                    $rowArray = [];
                    for ($col = 1; $col <= $highestColIndex; $col++) {
                        $rowArray[$col - 1] = $sheet->getCell([$col, $rowNum])->getValue();
                    }

                    if (empty(array_filter($rowArray))) {
                        continue;
                    }

                    if (!$headerFound) {
                        $possibleMap = $this->buildHeaderMap($rowArray);
                        if (!empty($possibleMap)) {
                            $headerMap = $possibleMap;
                            $headerFound = true;
                            $detectedHeaders = $rowArray;
                            Log::info("ShipmentsImport: Detected header on sheet {$sheetName}: " . json_encode($detectedHeaders));
                            continue;
                        }
                    }

                    if (empty($sampleRow)) {
                        $sampleRow = $rowArray;
                    }

                    $totalProcessedRows++;
                    $parsed = $this->parseRowArray($rowArray, $headerMap, $now, $sheetName);
                    if ($parsed !== null) {
                        $batchBuffer[] = $parsed;
                        $resisInBuffer[] = $parsed['no_resi'];
                    }

                    if (count($batchBuffer) >= 1000) {
                        $syncedItems = $this->processBufferBatchWithPayloads($batchBuffer, $resisInBuffer);
                        $totalInsertedRows += count($syncedItems);
                        foreach ($syncedItems as $si) {
                            $allUpdatedPayloads[] = $si;
                        }
                        $batchBuffer = [];
                        $resisInBuffer = [];
                    }
                }
            }

            if (!empty($batchBuffer)) {
                $syncedItems = $this->processBufferBatchWithPayloads($batchBuffer, $resisInBuffer);
                $totalInsertedRows += count($syncedItems);
                foreach ($syncedItems as $si) {
                    $allUpdatedPayloads[] = $si;
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($totalInsertedRows === 0) {
                $msg = "Excel Import Failed: No valid rows found. Headers detected: " . json_encode($detectedHeaders) . " | Sample row: " . json_encode($sampleRow);
                Log::error($msg);
            } else {
                Log::info("ShipmentsImport PhpSpreadsheet chunking finished. Processed {$totalProcessedRows} rows, saved {$totalInsertedRows} rows.");
            }
        } catch (Throwable $e) {
            Log::error("PhpSpreadsheet chunking error: " . $e->getMessage());
        }

        return $allUpdatedPayloads;
    }

    /**
     * Build dynamic header mapping (case-insensitive & flexible)
     */
    protected function buildHeaderMap(array $rowArray): array
    {
        $map = [];
        $foundCount = 0;

        foreach ($rowArray as $colIdx => $val) {
            $valStr = strtolower(trim((string)$val));
            $clean = preg_replace('/[^a-z0-9]/', '', $valStr);

            if (empty($clean)) {
                continue;
            }

            // Resi Column
            if (in_array($clean, ['noresi', 'resi', 'awb', 'barcode', 'nobarcode', 'nomorresi', 'resiposis', 'barcodeitem'])) {
                $map['resi'] = $colIdx;
                $foundCount++;
            }
            // Nama CS Column (Support "Ditugaskan Ke", "Nama CS", "CRM", "CS", dll)
            elseif (
                in_array($clean, [
                    'ditugaskanke', 'ditugaskan', 'assignedto', 'assignee', 'penugasan',
                    'namacs', 'cs', 'crm', 'admincs', 'namaadmin', 'piccs', 'pic',
                    'csname', 'customerservice', 'sales', 'namasales'
                ])
                || str_contains($clean, 'ditugaskan')
                || str_contains($clean, 'namacs')
                || str_contains($clean, 'assigned')
                || str_contains($clean, 'penugasan')
            ) {
                $map['nama_cs'] = $colIdx;
                $foundCount++;
            }
            // Seller Column
            elseif (in_array($clean, ['seller', 'mitra', 'namaseller', 'sellermitra', 'pengirim', 'sellers'])) {
                $map['seller'] = $colIdx;
                $foundCount++;
            }
            // Tanggal Kirim Column
            elseif (str_contains($clean, 'tanggal') || str_contains($clean, 'tgl') || in_array($clean, ['aliqatanggal', 'tglkirim', 'tanggalkirim', 'tgl', 'tanggal', 'tglkirimpos', 'tanggalkirimpos', 'date'])) {
                $map['tanggal_kirim'] = $colIdx;
                $foundCount++;
            }
            // Penerima Column
            elseif (in_array($clean, ['nama', 'namadepan', 'namalengkap', 'penerima', 'namapenerima', 'namakonsumen', 'konsumen', 'pembeli', 'namapembeli', 'penerimabarang', 'namatujuan'])) {
                if (!isset($map['penerima'])) {
                    $map['penerima'] = $colIdx;
                    $foundCount++;
                }
            }
            // No HP / Telepon Column
            elseif (in_array($clean, ['teleponalamat', 'telepon', 'nohp', 'hp', 'notelpon', 'phone', 'contact', 'nohppenerima'])) {
                $map['no_hp'] = $colIdx;
                $foundCount++;
            }
            // Alamat Column
            elseif (in_array($clean, ['alamat', 'tujuan', 'asaltujuan', 'alamatpenerima', 'kotatujuan', 'tujuankirim'])) {
                $map['alamat'] = $colIdx;
                $foundCount++;
            }
            // Status POS Column
            elseif (in_array($clean, ['tracking', 'trackingpos', 'statuspos', 'status', 'statusnipos', 'statusniposl', 'nipos', 'statusakhir'])) {
                $map['status_pos'] = $colIdx;
                $foundCount++;
            }
            // Keterangan Column
            elseif (in_array($clean, ['posketerangan', 'keterangan', 'penerimaketerangank', 'keterangank', 'alasan', 'note', 'penerimaantaran'])) {
                $map['keterangan'] = $colIdx;
                $foundCount++;
            }
            // SLA Days Column
            elseif (in_array($clean, ['sla', 'slamasatahan', 'sladays', 'slaindays', 'slam'])) {
                $map['sla_days'] = $colIdx;
                $foundCount++;
            }
            // Status FU / Warna / Follow-Up Column
            elseif (in_array($clean, [
                'fu', 'statusfu', 'followup', 'statusfollowup', 'warna', 'color',
                'colorcode', 'warnafu', 'sudahfu', 'fucs', 'statuscs', 'fustat',
                'sudahdifu', 'fu1', 'fu2', 'fupos', 'follow', 'statusfollow',
            ])) {
                $map['status_fu'] = $colIdx;
                $foundCount++;
            }
        }

        return ($foundCount > 0 && isset($map['resi'])) ? $map : ($foundCount >= 2 ? $map : []);
    }

    /**
     * Parse raw row array into OutgoingShipment record (with Sheet Name Fallback)
     */
    protected function parseRowArray(array $rowArray, array $headerMap, string $now, ?string $sheetName = null): ?array
    {
        if (empty($rowArray)) {
            return null;
        }

        $isAliqaSheet = ($sheetName && str_contains(strtoupper($sheetName), 'ALIQA')) || str_contains(strtoupper($this->defaultSeller), 'ALIQA');
        $isZaherbaSheet = ($sheetName && str_contains(strtoupper($sheetName), 'ZAHERBA')) || str_contains(strtoupper($this->defaultSeller), 'ZAHERBA');

        // 1. Extract Resi Number (Zaherba Kolom E -> Header Map -> Named Keys -> Positional Index Fallback -> Regex Scan)
        $resi = null;
        if ($isZaherbaSheet) {
            $candE = isset($rowArray[4]) ? trim((string)$rowArray[4]) : (isset($rowArray['resi']) ? trim((string)$rowArray['resi']) : '');
            $candEUpper = strtoupper($candE);
            if (!empty($candE) && !in_array($candEUpper, ['RESI', 'NO RESI', 'BARCODE', 'AWB', 'NO', 'INVOICE'])) {
                if (preg_match('/^[A-Za-z0-9]{8,35}$/', $candE)) {
                    $resi = $candE;
                }
            }
        }

        if (empty($resi)) {
            if (isset($headerMap['resi']) && isset($rowArray[$headerMap['resi']])) {
                $resi = $rowArray[$headerMap['resi']];
            } elseif (!empty($rowArray['resi'])) {
                $resi = $rowArray['resi'];
            } elseif (!empty($rowArray['no_resi'])) {
                $resi = $rowArray['no_resi'];
            } elseif (!empty($rowArray['barcode'])) {
                $resi = $rowArray['barcode'];
            }
        }

        // Index Fallback if resi is still empty:
        // For Aliqa: Check Index 2 (C) first, then 0 (A), 4 (E), 1 (B), 3 (D)
        // For Zaherba / general: Check Index 4 (E), 0 (A), 1 (B), 3 (D), 2 (C)
        if (empty($resi)) {
            $candidateIndexes = $isAliqaSheet ? [2, 0, 4, 1, 3] : [4, 0, 1, 3, 2]; // Zaherba: prioritize column E (index 4) for Resi
            foreach ($candidateIndexes as $idx) {
                if (isset($rowArray[$idx])) {
                    $strVal = trim((string)$rowArray[$idx]);
                    $strUpper = strtoupper($strVal);
                    $hasResiPrefix = str_starts_with($strUpper, 'P') || str_starts_with($strUpper, 'BAC') || str_starts_with($strUpper, 'POS') || str_starts_with($strUpper, 'E') || str_starts_with($strUpper, 'J');
                    if (preg_match('/^[A-Za-z0-9]{8,35}$/', $strVal) && ($hasResiPrefix || is_numeric($strVal))) {
                        if (!in_array($strUpper, ['RESI', 'NO RESI', 'BARCODE', 'NO', 'INVOICE', 'NO HP', 'TELEPON', 'TANGGAL', 'STATUS', 'SELLER'])) {
                            $resi = $strVal;
                            break;
                        }
                    }
                }
            }
        }

        // Final Regex Scan across ALL cell values in the row
        if (empty($resi)) {
            foreach ($rowArray as $colVal) {
                $strVal = trim((string)$colVal);
                $strUpper = strtoupper($strVal);
                $hasResiPrefix = str_starts_with($strUpper, 'P') || str_starts_with($strUpper, 'BAC') || str_starts_with($strUpper, 'POS') || str_starts_with($strUpper, 'E') || str_starts_with($strUpper, 'J');
                if (preg_match('/^[A-Za-z0-9]{8,35}$/', $strVal) && ($hasResiPrefix || (is_numeric($strVal) && strlen($strVal) >= 10))) {
                    if (!in_array($strUpper, ['RESI', 'NO RESI', 'BARCODE', 'NO', 'INVOICE', 'NO HP', 'TELEPON', 'TANGGAL', 'STATUS', 'SELLER'])) {
                        $resi = $strVal;
                        break;
                    }
                }
            }
        }

        $resi = trim((string)$resi);
        if (empty($resi)) {
            return null;
        }

        // Resi MUST be valid alphanumeric (length 8-35)
        if (!preg_match('/^[A-Za-z0-9]{8,35}$/', $resi)) {
            return null;
        }

        $resiUpper = strtoupper($resi);
        $headerWords = [
            'RESI', 'NO RESI', 'BARCODE', 'TRACKING POS', 'FORM PEMANTAUAN', 'NO', 'NO RESI',
            'BARCODE ITEM', 'INVOICE', 'TELEPON', 'PRODUK', 'JUMLAH COD', 'SLA', 'KETERANGAN',
            'TANGGAL', 'PENERIMA', 'NAMA CS', 'SELLER', 'KONSUMEN', 'ALAMAT', 'STATUS', 'STATUS POS', 'TRACKING'
        ];
        if (in_array($resiUpper, $headerWords) || str_starts_with($resiUpper, 'FORM') || str_starts_with($resiUpper, 'PEM')) {
            return null;
        }

        if (is_numeric($resi) && strlen($resi) < 8) {
            return null; // Skip pure numeric row indexes (1, 2, 3...)
        }

        // 2. Extract Seller
        if ($isAliqaSheet) {
            $seller = 'Mitra Aliqa';
        } elseif ($isZaherbaSheet) {
            $seller = 'Mitra Zaherba';
        } else {
            $sellerRaw = isset($headerMap['seller']) ? ($rowArray[$headerMap['seller']] ?? null) : ($rowArray['nama_seller'] ?? $rowArray['seller'] ?? ($rowArray[2] ?? null));
            $seller = trim((string)($sellerRaw ?: $this->defaultSeller));
            if (empty($seller) || strtoupper($seller) === 'NAMA CS' || str_starts_with(strtoupper($seller), 'CS ') || strtoupper($seller) === 'SELLER' || is_numeric($seller)) {
                $seller = $this->defaultSeller;
            }
            if ($seller === 'Aliqa') {
                $seller = 'Mitra Aliqa';
            } elseif ($seller === 'Zaherba') {
                $seller = 'Mitra Zaherba';
            }
        }

        // 2b. Extract Nama CS
        $namaCsRaw = null;
        if (isset($headerMap['nama_cs']) && isset($rowArray[$headerMap['nama_cs']])) {
            $namaCsRaw = $rowArray[$headerMap['nama_cs']];
        } elseif (!empty($rowArray['nama_cs'])) {
            $namaCsRaw = $rowArray['nama_cs'];
        } elseif (!empty($rowArray['cs'])) {
            $namaCsRaw = $rowArray['cs'];
        } elseif (!empty($rowArray['ditugaskan_ke'])) {
            $namaCsRaw = $rowArray['ditugaskan_ke'];
        } elseif (!empty($rowArray['ditugaskan'])) {
            $namaCsRaw = $rowArray['ditugaskan'];
        } elseif ($isZaherbaSheet) {
            // Sheet Zaherba: Kolom 7 (jika ada Provinsi di kolom 6) atau Kolom 6 (jika tanpa Provinsi)
            $cand7 = isset($rowArray[7]) ? trim((string)$rowArray[7]) : '';
            $cand6 = isset($rowArray[6]) ? trim((string)$rowArray[6]) : '';
            if (!empty($cand7) && (preg_match('/^(?:CS|CRM|IVI)\b/i', $cand7) || (!is_numeric($cand7) && !str_contains(strtoupper($cand7), 'SACHET') && !str_contains(strtoupper($cand7), 'LAMBUNG')))) {
                $namaCsRaw = $cand7;
            } elseif (!empty($cand6) && (preg_match('/^(?:CS|CRM|IVI)\b/i', $cand6) || (!is_numeric($cand6) && !in_array(strtoupper($cand6), ['JAWA TIMUR', 'PAPUA BARAT', 'SUMATERA UTARA', 'SUMATERA BARAT', 'PROVINSI'])))) {
                $namaCsRaw = $cand6;
            }
        } elseif ($isAliqaSheet) {
            // Sheet Aliqa: Kolom 9 adalah "Ditugaskan Ke" (Juni-Desember)
            $cand9 = isset($rowArray[9]) ? trim((string)$rowArray[9]) : '';
            $cand5 = isset($rowArray[5]) ? trim((string)$rowArray[5]) : '';
            if (!empty($cand9) && !is_numeric($cand9) && strlen($cand9) > 1 && !in_array(strtoupper($cand9), ['DITUGASKAN KE', 'DITUGASKAN', 'POS', 'POS COD', '1'])) {
                $namaCsRaw = $cand9;
            } elseif (!empty($cand5) && !is_numeric($cand5) && strlen($cand5) > 1 && !in_array(strtoupper($cand5), ['JUMLAH PRODUK', '1', '2', '3', 'NAMA CS', 'PRODUK'])) {
                $namaCsRaw = $cand5;
            }
        }

        $namaCs = trim((string)($namaCsRaw ?: ''));
        // Bersihkan jika hanya angka (misal "1"), 1 karakter, alamat nyasar, atau kata kunci judul/header
        if (
            is_numeric($namaCs)
            || strlen($namaCs) <= 1
            || strlen($namaCs) > 40
            || preg_match('/\b(?:jalan|jln|desa|kecamatan|kelurahan|kabupaten|patokan|rt[\s\/\.]*rw)\b/i', $namaCs)
            || in_array(strtoupper($namaCs), [
                'NAMA CS', 'CS', 'CRM', 'ADMIN', 'PIC CS', 'NAMA ADMIN', 'SELLER', 'NAMA', 'RESI',
                '-', '', 'N/A', 'NULL', 'POS', 'POS COD', 'COD', 'DITUGASKAN KE', 'DITUGASKAN', 'PROVINSI',
                'JUMLAH PRODUK', 'METODE PENGIRIMAN', 'CATATAN ADMIN'
            ])
        ) {
            $namaCs = null;
        }

        // Fallback cerdas: Scan teks sel baris (misal Catatan Admin) jika tertulis "CS ELLA", "CRM SEVI", dll
        if (empty($namaCs)) {
            foreach ($rowArray as $cellVal) {
                if (is_string($cellVal) && preg_match('/(?:^|\n|\r|\s)(?:CS|CRM)\s+([A-Za-z0-9_\.\-]+)/i', $cellVal, $m)) {
                    $foundCandidate = trim($m[1]);
                    if (!empty($foundCandidate) && !is_numeric($foundCandidate) && !in_array(strtoupper($foundCandidate), ['COD', 'POS', 'ADMIN', 'NOTE', 'UPDATE', 'KIRIM', 'PENGIRIM'])) {
                        $namaCs = (str_starts_with(strtoupper($m[0]), 'CRM') || str_contains(strtoupper($m[0]), 'CRM')) ? ('CRM ' . $foundCandidate) : ('CS ' . $foundCandidate);
                        break;
                    }
                }
            }
        }

        // 3. Extract Penerima (Zaherba: Kolom C / Index 2; Aliqa Jan-Mei: Kolom B / Index 1; Aliqa Jun-Agt: Kolom D / Index 3)
        $penerimaRaw = null;
        if ($isZaherbaSheet) {
            $penerimaRaw = $rowArray[2] ?? ($rowArray['nama_penerima'] ?? ($rowArray['penerima'] ?? null));
        } elseif (isset($headerMap['penerima']) && isset($rowArray[$headerMap['penerima']])) {
            $penerimaRaw = $rowArray[$headerMap['penerima']];
        } elseif (!empty($rowArray['nama_penerima'])) {
            $penerimaRaw = $rowArray['nama_penerima'];
        } elseif (!empty($rowArray['penerima'])) {
            $penerimaRaw = $rowArray['penerima'];
        } elseif (!empty($rowArray['nama'])) {
            $penerimaRaw = $rowArray['nama'];
        } elseif (!empty($rowArray['namadepan'])) {
            $penerimaRaw = $rowArray['namadepan'];
        }

        $penerima = trim((string)($penerimaRaw ?: ''));
        // Cek jika penerima masih kosong atau tidak sengaja berisi nomor resi (dimulai BAC... atau P26...)
        $isResiValue = !empty($penerima) && preg_match('/^(BAC\d|P26\d|P\d{7})/i', $penerima);
        if (empty($penerima) || $isResiValue) {
            $candidates = $isAliqaSheet ? [1, 3, 2] : [2, 1, 3];
            foreach ($candidates as $cIdx) {
                if (isset($rowArray[$cIdx])) {
                    $candStr = trim((string)$rowArray[$cIdx]);
                    if (!empty($candStr) && !preg_match('/^(BAC\d|P26\d|P\d{7}|\d{10,})/i', $candStr) && !in_array(strtoupper($candStr), ['NAMA', 'RESI', 'INVOICE', '-', 'NAMA DEPAN'])) {
                        $penerima = $candStr;
                        break;
                    }
                }
            }
        }

        if (in_array(strtoupper($penerima), ['NAMA KONSUMEN', 'PENERIMA', 'NAMA PENERIMA', 'PENERIMA BARANG', 'NAMA DEPAN', 'NAMA'])) {
            return null; // Skip header row if caught
        }

        // 4. Extract No HP (Zaherba: Kolom I / Index 8; Aliqa: Kolom O / Index 14)
        $noHpRaw = null;
        if ($isZaherbaSheet) {
            $noHpRaw = $rowArray[8] ?? ($rowArray['no_hp'] ?? ($rowArray['hp'] ?? ($rowArray['telepon'] ?? null)));
        } elseif (isset($headerMap['no_hp'])) {
            $noHpRaw = $rowArray[$headerMap['no_hp']] ?? null;
        } else {
            $noHpRaw = $rowArray['no_hp'] ?? $rowArray['hp'] ?? $rowArray['telepon'] ?? ($isAliqaSheet ? ($rowArray[14] ?? null) : ($rowArray[8] ?? ($rowArray[6] ?? null)));
        }
        $noHp = trim((string)($noHpRaw ?: ''));

        // 5. Extract Alamat (Zaherba: Kolom F / Index 5; Aliqa: Kolom N / Index 13)
        $alamatRaw = null;
        if ($isZaherbaSheet) {
            $alamatRaw = $rowArray[5] ?? ($rowArray['alamat'] ?? null);
        } elseif (isset($headerMap['alamat'])) {
            $alamatRaw = $rowArray[$headerMap['alamat']] ?? null;
        } else {
            $alamatRaw = $rowArray['alamat'] ?? ($isAliqaSheet ? ($rowArray[13] ?? null) : ($rowArray[5] ?? ($rowArray[3] ?? null)));
        }
        $alamat = trim((string)($alamatRaw ?: ''));

        // 6. Extract Tanggal Kirim (Zaherba: Kolom B / Index 1; Header Map -> Resi Barcode Pattern -> Sheet Name Fallback)
        $tanggalRaw = null;
        if ($isZaherbaSheet) {
            $tanggalRaw = $rowArray[1] ?? ($rowArray['tanggal_kirim'] ?? ($rowArray['tanggal'] ?? null));
        } elseif (isset($headerMap['tanggal_kirim'])) {
            $tanggalRaw = $rowArray[$headerMap['tanggal_kirim']] ?? null;
        } else {
            $tanggalRaw = $rowArray['tanggal_kirim'] ?? $rowArray['tanggal'] ?? ($rowArray[1] ?? ($rowArray[0] ?? null));
        }
        $tanggalKirim = $this->parseDateValue($tanggalRaw, $sheetName, $resi);

        // 7. Extract Status POS & Keterangan & SLA
        if ($isZaherbaSheet) {
            $statusPosRaw = $rowArray[11] ?? ($rowArray['status_pos'] ?? ($rowArray['tracking_pos'] ?? null));
            $ketRaw = $rowArray[10] ?? ($rowArray['keterangan'] ?? null);
            $slaRaw = $rowArray[12] ?? ($rowArray['sla_days'] ?? null);
        } else {
            $statusPosRaw = isset($headerMap['status_pos']) ? ($rowArray[$headerMap['status_pos']] ?? null) : ($rowArray['status_pos'] ?? $rowArray['tracking_pos'] ?? $rowArray['status'] ?? ($isAliqaSheet ? ($rowArray[16] ?? null) : ($rowArray[11] ?? null)));
            $ketRaw = isset($headerMap['keterangan']) ? ($rowArray[$headerMap['keterangan']] ?? null) : ($rowArray['keterangan'] ?? ($isAliqaSheet ? ($rowArray[15] ?? null) : ($rowArray[10] ?? null)));
            $slaRaw = isset($headerMap['sla_days']) ? ($rowArray[$headerMap['sla_days']] ?? null) : ($rowArray['sla_days'] ?? $rowArray['sla'] ?? ($isAliqaSheet ? ($rowArray[17] ?? null) : null));
        }

        $statusPos = trim((string)($statusPosRaw ?: ''));
        if (in_array(strtoupper($statusPos), ['TRACKING POS', 'STATUS', 'STATUS POS', 'TRACKING'])) {
            $statusPos = '';
        }

        $keterangan = trim((string)($ketRaw ?: ''));
        if (in_array(strtoupper($keterangan), ['KETERANGAN', 'PENERIMA & KETERANGAN', 'POS KETERANGAN'])) {
            $keterangan = '';
        }

        $slaDays = is_numeric($slaRaw) ? (int)$slaRaw : (!empty($slaRaw) && preg_match('/(\d+)/', (string)$slaRaw, $sm) ? (int)$sm[1] : null);

        // 9. Extract Status FU / Warna dari kolom sheet (jika ada)
        $fuRaw = isset($headerMap['status_fu']) ? ($rowArray[$headerMap['status_fu']] ?? null) : ($rowArray['status_fu'] ?? $rowArray['fu'] ?? $rowArray['warna'] ?? null);
        $fuRawStr = strtoupper(trim((string)($fuRaw ?: '')));

        // Categorize status dengan RETUR priority dicek PERTAMA
        $botService = $this->botService ??= new \App\Services\TrackingBotService();
        $kategori = $botService->categorizeStatus($statusPos, $keterangan);

        // Tentukan color_code dari kolom FU sheet (prioritas) atau dari status_pos/keterangan
        $explicitFuColor = $this->extractExplicitFuColor($fuRawStr);
        $colorCode = $explicitFuColor ?: $botService->determineColorCode($kategori);

        return [
            'nama_seller'     => $seller,
            'nama_cs'         => $namaCs ?: null,
            'no_resi'         => $resi,
            'nama_penerima'   => $penerima ?: null,
            'no_hp'           => $noHp ?: null,
            'alamat'          => $alamat ?: null,
            'tanggal_kirim'   => $tanggalKirim,
            'status_pos'      => $statusPos ?: null,
            'keterangan'      => $keterangan ?: null,
            'status_kategori' => $kategori,
            'color_code'      => $colorCode,
            'has_explicit_fu' => ($explicitFuColor !== null),
            'sla_days'        => $slaDays,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
    }

    /**
     * Extract explicit color code dari nilai kolom status FU sheet jika ada.
     */
    protected function extractExplicitFuColor(string $fuRawStr): ?string
    {
        if (empty($fuRawStr) || in_array($fuRawStr, ['FU', 'STATUS FU', 'FOLLOW UP', 'WARNA', 'COLOR', 'STATUS', '-'])) {
            return null;
        }

        // RETUR / RETURN / GAGAL / IRREGULARITY / DITOLAK → ORANGE
        if (str_contains($fuRawStr, 'RETUR') || str_contains($fuRawStr, 'RETURN') || str_contains($fuRawStr, 'GAGAL') || str_contains($fuRawStr, 'IRREGULARITY') || str_contains($fuRawStr, 'DITOLAK') || str_contains($fuRawStr, 'ORANGE')) {
            return 'ORANGE';
        }
        // DELIVERED / SUKSES / SELESAI / BIRU → BIRU
        if (str_contains($fuRawStr, 'DELIVERED') || str_contains($fuRawStr, 'SUKSES') || str_contains($fuRawStr, 'SELESAI') || $fuRawStr === 'BIRU') {
            return 'BIRU';
        }
        // FU POS / ESKALASI / FUPOS / BIRU_TUA → BIRU_TUA
        if (str_contains($fuRawStr, 'FU POS') || str_contains($fuRawStr, 'FUPOS') || str_contains($fuRawStr, 'ESKALASI') || $fuRawStr === 'BIRU_TUA') {
            return 'BIRU_TUA';
        }
        // FU DUA KALI / FU 2 / FU2 / FU 2X / HIJAU → HIJAU
        if (
            str_contains($fuRawStr, 'DUA') || str_contains($fuRawStr, '2 KALI') || str_contains($fuRawStr, '2X') ||
            $fuRawStr === 'FU2' || $fuRawStr === 'FU 2' || $fuRawStr === 'HIJAU'
        ) {
            return 'HIJAU';
        }
        // FU SEKALI / SUDAH FU / FU 1 / FU1 / KUNING → KUNING
        if (
            str_contains($fuRawStr, 'SEKALI') || str_contains($fuRawStr, 'SUDAH FU') || str_contains($fuRawStr, 'SUDAH DI FU') ||
            str_contains($fuRawStr, '1 KALI') || str_contains($fuRawStr, '1X') ||
            $fuRawStr === 'FU1' || $fuRawStr === 'FU 1' || $fuRawStr === 'KUNING' || $fuRawStr === 'FU'
        ) {
            return 'KUNING';
        }
        // BELUM FU / PROSES / PUTIH → PUTIH
        if (str_contains($fuRawStr, 'BELUM') || str_contains($fuRawStr, 'PROSES') || $fuRawStr === 'PUTIH') {
            return 'PUTIH';
        }

        return null;
    }

    /**
     * Resolve color_code dari teks status FU di sheet atau dari kategori NIPPOS.
     */
    protected function resolveFuColorCode(string $fuRawStr, string $kategori, \App\Services\TrackingBotService $botService): string
    {
        $explicit = $this->extractExplicitFuColor($fuRawStr);
        if ($explicit !== null) {
            return $explicit;
        }

        // Derive dari kategori (SUKSES/RETUR dari status_pos/keterangan)
        return $botService->determineColorCode($kategori);
    }


    /**
     * Extract date from Pos Indonesia barcode / resi pattern
     */
    public static function extractDateFromResi(?string $resi): ?string
    {
        if (empty($resi)) {
            return null;
        }

        $r = strtoupper(trim($resi));

        // Pattern 1: P + YY + MM + DD (e.g. P2602030004347 -> 2026-02-03, P260115... -> 2026-01-15)
        if (preg_match('/^P(\d{2})(\d{2})(\d{2})/i', $r, $m)) {
            $year = '20' . $m[1];
            $month = (int)$m[2];
            $day = (int)$m[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return sprintf('%s-%02d-%02d', $year, $month, $day);
            }
        }

        // Pattern 2: BAC + DD + MM + YYYY (2024-2030) (e.g. BAC20022026 -> 2026-02-20)
        if (preg_match('/^BAC(\d{2})(\d{2})(20[2-3]\d)/i', $r, $m)) {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = $m[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return sprintf('%s-%02d-%02d', $year, $month, $day);
            }
        }

        // Pattern 3: BAC + DD + MM + YY (e.g. BAC100426... -> 2026-04-10)
        if (preg_match('/^BAC(\d{2})(\d{2})(2[4-9])/i', $r, $m)) {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = '20' . $m[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return sprintf('%s-%02d-%02d', $year, $month, $day);
            }
        }

        return null;
    }

    /**
     * Robust Date Parsing with Resi Barcode & Sheet Name Month Fallback
     */
    protected function parseDateValue($val, ?string $sheetName = null, ?string $resi = null): ?string
    {
        // 1. High-Confidence Pos Indonesia Barcode Resi Date Extraction First
        if (!empty($resi)) {
            $resiDate = self::extractDateFromResi($resi);
            if ($resiDate) {
                return $resiDate;
            }
        }

        // 2. Direct date value parsing from cell
        if (!empty($val) && !in_array(strtoupper(trim((string)$val)), ['TANGGAL', 'TGL', 'TGL KIRIM', 'TANGGAL KIRIM', 'TANGGAL_KIRIM', 'DATE', '-'])) {
            try {
                if (is_numeric($val) && (float)$val > 10000) {
                    return ExcelDate::excelToDateTimeObject((float)$val)->format('Y-m-d');
                }

                $str = trim((string)$val);
                // Format: YYYY-MM-DD or YYYY/MM/DD
                if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $str, $m)) {
                    $yr = (int)$m[1];
                    $mo = (int)$m[2];
                    $dy = (int)$m[3];
                    if ($mo >= 1 && $mo <= 12 && $dy >= 1 && $dy <= 31) {
                        return sprintf('%04d-%02d-%02d', $yr, $mo, $dy);
                    }
                }

                // Format: DD/MM/YYYY or DD-MM-YYYY or MM/DD/YYYY
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $str, $m)) {
                    $d1 = (int)$m[1];
                    $d2 = (int)$m[2];
                    $yr = (int)$m[3];
                    // If d1 > 12 -> d1 is Day, d2 is Month (DD/MM/YYYY)
                    if ($d1 > 12 && $d2 <= 12) {
                        return sprintf('%04d-%02d-%02d', $yr, $d2, $d1);
                    }
                    // If d2 > 12 -> d2 is Day, d1 is Month (MM/DD/YYYY)
                    if ($d2 > 12 && $d1 <= 12) {
                        return sprintf('%04d-%02d-%02d', $yr, $d1, $d2);
                    }
                    // Standard Indonesian default: DD/MM/YYYY ($d1 = Day, $d2 = Month)
                    if ($d1 <= 31 && $d2 <= 12) {
                        return sprintf('%04d-%02d-%02d', $yr, $d2, $d1);
                    }
                }

                // Format: DD/MM/YY or DD-MM-YY
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2})$/', $str, $m)) {
                    $d1 = (int)$m[1];
                    $d2 = (int)$m[2];
                    $yr = (int)('20' . $m[3]);
                    if ($d1 <= 31 && $d2 <= 12) {
                        return sprintf('%04d-%02d-%02d', $yr, $d2, $d1);
                    }
                }

                // Translate Indonesian month names to English before Carbon parsing
                $indoToEng = [
                    'januari' => 'January', 'februari' => 'February', 'maret' => 'March',
                    'april' => 'April', 'mei' => 'May', 'juni' => 'June',
                    'juli' => 'July', 'agustus' => 'August', 'september' => 'September',
                    'oktober' => 'October', 'november' => 'November', 'desember' => 'December',
                    'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar', 'apr' => 'Apr',
                    'jun' => 'Jun', 'jul' => 'Jul', 'agt' => 'Aug', 'agu' => 'Aug',
                    'sep' => 'Sep', 'okt' => 'Oct', 'nov' => 'Nov', 'des' => 'Dec',
                ];
                $engStr = str_ireplace(array_keys($indoToEng), array_values($indoToEng), $str);

                $hasDigit = preg_match('/\d/', $str);
                $hasMonth = false;
                foreach (array_keys($indoToEng) as $mName) {
                    if (stripos($str, $mName) !== false) {
                        $hasMonth = true;
                        break;
                    }
                }

                if ($hasDigit || $hasMonth) {
                    $parsedCarbon = Carbon::parse($engStr);
                    if ($parsedCarbon && $parsedCarbon->year >= 2020 && $parsedCarbon->year <= 2030) {
                        return $parsedCarbon->format('Y-m-d');
                    }
                }
            } catch (Throwable $e) {
                // Fallback below
            }
        }

        // 3. Sheet Name Month Fallback (e.g. "JANUARI (ZAHERBA)", "Februari", "Maret", "AGUSTUS", etc.)
        if (!empty($sheetName)) {
            $sheetUpper = strtoupper($sheetName);
            $monthsMap = [
                'JANUARI' => '01', 'JAN' => '01',
                'FEBRUARI' => '02', 'FEB' => '02',
                'MARET' => '03', 'MAR' => '03',
                'APRIL' => '04', 'APR' => '04',
                'MEI' => '05', 'MAY' => '05',
                'JUNI' => '06', 'JUN' => '06',
                'JULI' => '07', 'JUL' => '07',
                'AGUSTUS' => '08', 'AGUS' => '08', 'AGT' => '08', 'AUG' => '08',
                'SEPTEMBER' => '09', 'SEP' => '09',
                'OKTOBER' => '10', 'OKT' => '10', 'OCT' => '10',
                'NOVEMBER' => '11', 'NOV' => '11',
                'DESEMBER' => '12', 'DES' => '12', 'DEC' => '12',
            ];

            // Extract Year if present in Sheet Name (e.g. "MARET 2026" -> 2026)
            $sheetYear = date('Y');
            if (preg_match('/(20\d{2})/', $sheetUpper, $ym)) {
                $sheetYear = $ym[1];
            }

            foreach ($monthsMap as $keyword => $mNum) {
                if (str_contains($sheetUpper, $keyword)) {
                    return sprintf('%s-%s-01', $sheetYear, $mNum);
                }
            }
        }

        return date('Y-m-d');
    }

    /**
     * Process 1,000 rows buffer batch with DB Transaction & Data Preservation
     * @return int Number of inserted/updated rows
     */
    protected function processBufferBatch(array $batchBuffer, array $resisInBuffer): int
    {
        $payloads = $this->processBufferBatchWithPayloads($batchBuffer, $resisInBuffer);
        return count($payloads);
    }

    /**
     * Process 1,000 rows buffer batch with DB Transaction & Data Preservation
     * @return array Array of formatted payload items for Google Sheets reverse sync
     */
    protected function processBufferBatchWithPayloads(array $batchBuffer, array $resisInBuffer): array
    {
        if (empty($batchBuffer)) {
            return [];
        }

        // Deduplicate buffer batch by no_resi to ensure clean unique key upserting
        $uniqueMap = [];
        foreach ($batchBuffer as $item) {
            $uniqueMap[$item['no_resi']] = $item;
        }
        $batchBuffer = array_values($uniqueMap);
        $resisInBuffer = array_keys($uniqueMap);

        $botService = $this->botService ??= new \App\Services\TrackingBotService();

        // 1. Fetch existing database records to preserve established statuses & manual CS updates
        $existingMap = OutgoingShipment::whereIn('no_resi', $resisInBuffer)
            ->select(['id', 'nama_seller', 'nama_cs', 'no_resi', 'nama_penerima', 'no_hp', 'alamat', 'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori', 'color_code', 'sla_days', 'fu_pos_date', 'noted', 'kantor_tujuan', 'last_location', 'kantor_pos_id', 'last_tracked_at'])
            ->get()
            ->keyBy('no_resi');

        $mergedBatch = [];
        $now = now()->toDateTimeString();

        foreach ($batchBuffer as $data) {
            $resiKey = $data['no_resi'];
            $existing = $existingMap[$resiKey] ?? null;

            if ($existing) {
                // PRESERVE established data for existing records:
                $seller = (!empty($data['nama_seller']) && $data['nama_seller'] !== 'Aliqa') ? $data['nama_seller'] : ($existing->nama_seller ?: $data['nama_seller']);
                $existingCs = ($existing->nama_cs && !is_numeric($existing->nama_cs) && strlen($existing->nama_cs) > 1 && $existing->nama_cs !== '1') ? $existing->nama_cs : null;
                $incomingCs = (!empty($data['nama_cs']) && !is_numeric($data['nama_cs']) && strlen($data['nama_cs']) > 1 && $data['nama_cs'] !== '1') ? $data['nama_cs'] : null;
                $namaCs = $incomingCs ?: $existingCs;
                $penerima = !empty($data['nama_penerima']) ? $data['nama_penerima'] : $existing->nama_penerima;
                $noHp = !empty($data['no_hp']) ? $data['no_hp'] : $existing->no_hp;
                $alamat = !empty($data['alamat']) ? $data['alamat'] : $existing->alamat;
                $resiBarcodeDate = self::extractDateFromResi($resiKey);
                $tanggalKirim = !empty($data['tanggal_kirim']) 
                    ? $data['tanggal_kirim'] 
                    : ($resiBarcodeDate 
                        ?: ($existing->tanggal_kirim 
                            ? (is_string($existing->tanggal_kirim) ? substr($existing->tanggal_kirim, 0, 10) : $existing->tanggal_kirim->format('Y-m-d')) 
                            : date('Y-m-d')));

                $incomingStatus = $data['status_pos'] ?? '';
                $incomingKet = $data['keterangan'] ?? '';
                $incomingCategory = $botService->categorizeStatus($incomingStatus, $incomingKet);

                $existingStatus = $existing->status_pos ?? '';
                $existingKet = $existing->keterangan ?? '';
                $existingCategory = $botService->categorizeStatus($existingStatus, $existingKet);

                $isRetur = ($incomingCategory === 'RETUR' || $existingCategory === 'RETUR' || ($existing->status_kategori ?? '') === 'RETUR' || ($existing->color_code ?? '') === 'ORANGE' || ($existing->isReturn()));
                $isSukses = !$isRetur && ($incomingCategory === 'SUKSES' || $existingCategory === 'SUKSES' || ($existing->status_kategori ?? '') === 'SUKSES' || ($existing->color_code ?? '') === 'BIRU' || ($existing->status_pos ?? '') === 'DELIVERED');

                // Priority Check: RETUR -> SUKSES -> IN_PROCESS / FOLLOW_UP
                if ($isRetur) {
                    $statusPos = ($incomingCategory === 'RETUR') ? ($data['status_pos'] ?: 'DELIVERED (RETURN DELIVERY)') : ($existing->status_pos ?: 'DELIVERED (RETURN DELIVERY)');
                    $keterangan = ($incomingCategory === 'RETUR') ? ($data['keterangan'] ?: 'DITERIMA PENGIRIM') : ($existing->keterangan ?: 'DITERIMA PENGIRIM');
                    $statusKategori = 'RETUR';
                    $colorCode = 'ORANGE';
                    $slaDays = $data['sla_days'] ?: $existing->sla_days;
                } elseif ($isSukses) {
                    $statusPos = ($incomingCategory === 'SUKSES') ? ($data['status_pos'] ?: 'DELIVERED') : ($existing->status_pos ?: 'DELIVERED');
                    $keterangan = ($incomingCategory === 'SUKSES') ? ($data['keterangan'] ?: 'DITERIMA YANG BERSANGKUTAN') : ($existing->keterangan ?: 'DITERIMA YANG BERSANGKUTAN');
                    $statusKategori = 'SUKSES';
                    $colorCode = 'BIRU';
                    $slaDays = $data['sla_days'] ?: $existing->sla_days;
                } else {
                    $statusPos = !empty($data['status_pos']) ? $data['status_pos'] : ($existing->status_pos ?: 'ON PROCESS');
                    $keterangan = !empty($data['keterangan']) ? $data['keterangan'] : ($existing->keterangan ?: 'PROSES PENGIRIMAN POS');
                    $statusKategori = $incomingCategory ?: 'IN_PROCESS';

                    // === PRIORITAS COLOR_CODE ===
                    // 1. Jika sheet punya kolom FU dengan nilai eksplisit (KUNING/HIJAU/BIRU_TUA/PUTIH/dll)
                    //    → gunakan langsung dari sheet (data fix sesuai yang diinput di sheet)
                    // 2. Jika sheet TIDAK punya kolom FU eksplisit → pertahankan warna yang sudah ada di DB
                    //    (misal CS sudah set BIRU_TUA / HIJAU / KUNING via website, JANGAN di-reset ke PUTIH!)
                    $hasExplicitFu = !empty($data['has_explicit_fu']);
                    $incomingColorFromSheet = $data['color_code'] ?? null;

                    if ($hasExplicitFu && !empty($incomingColorFromSheet)) {
                        // Sheet punya data FU yang eksplisit → pakai dari sheet
                        $colorCode = $incomingColorFromSheet;
                        if (in_array($colorCode, ['KUNING', 'HIJAU', 'BIRU_TUA'])) {
                            $statusKategori = 'FOLLOW_UP';
                        }
                    } elseif (!empty($existing->color_code) && in_array($existing->color_code, ['KUNING', 'HIJAU', 'BIRU_TUA'])) {
                        // Pertahankan warna DB manual yang diupdate CS / Admin
                        $colorCode = $existing->color_code;
                        $statusKategori = 'FOLLOW_UP';
                    } else {
                        $colorCode = $botService->determineColorCode($statusKategori);
                    }

                    $slaDays = $data['sla_days'] ?: $existing->sla_days;
                }

                $fuPosDate = $existing->fu_pos_date;
                $noted = $existing->noted;
                $lastTrackedAt = $existing->last_tracked_at;

                $mergedBatch[] = [
                    'nama_seller' => $seller,
                    'nama_cs' => $namaCs,
                    'no_resi' => $resiKey,
                    'nama_penerima' => $penerima,
                    'no_hp' => $noHp,
                    'alamat' => $alamat,
                    'tanggal_kirim' => $tanggalKirim,
                    'status_pos' => $statusPos ?: 'ON PROCESS',
                    'keterangan' => $keterangan ?: 'PROSES PENGIRIMAN POS',
                    'status_kategori' => $statusKategori ?: 'IN_PROCESS',
                    'color_code' => $colorCode,
                    'fu_pos_date' => $fuPosDate,
                    'noted' => $noted,
                    'sla_days' => $slaDays,
                    'last_tracked_at' => $lastTrackedAt,
                    'created_at' => $existing->created_at ? (is_string($existing->created_at) ? $existing->created_at : $existing->created_at->toDateTimeString()) : $now,
                    'updated_at' => $now,
                ];
            } else {
                // NEW SHIPMENT: Gunakan data dari sheet langsung sebagai data fix awal
                // color_code sudah di-resolve dari kolom FU sheet oleh parseRowArray()
                $incomingCategory = $data['status_kategori'] ?? 'IN_PROCESS';
                if ($incomingCategory === 'RETUR') {
                    $color = 'ORANGE';
                } elseif ($incomingCategory === 'SUKSES') {
                    $color = 'BIRU';
                } else {
                    $color = !empty($data['color_code']) ? $data['color_code'] : $botService->determineColorCode($incomingCategory);
                }

                $mergedBatch[] = [
                    'nama_seller'     => $data['nama_seller'] ?: $this->defaultSeller,
                    'nama_cs'         => $data['nama_cs'] ?? null,
                    'no_resi'         => $resiKey,
                    'nama_penerima'   => $data['nama_penerima'],
                    'no_hp'           => $data['no_hp'],
                    'alamat'          => $data['alamat'],
                    'tanggal_kirim'   => $data['tanggal_kirim'] ?: ($newResiDate ?: date('Y-m-d')),
                    'status_pos'      => $data['status_pos'] ?: 'ON PROCESS',
                    'keterangan'      => $data['keterangan'] ?: 'PROSES PENGIRIMAN POS',
                    'status_kategori' => $data['status_kategori'] ?? 'IN_PROCESS',
                    'color_code'      => $color,
                    'fu_pos_date'     => null,
                    'noted'           => null,
                    'sla_days'        => $data['sla_days'],
                    'last_tracked_at' => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        // 2. Safe DB Transactions & Upsert per 500 items (releases locks immediately, prevents hanging)
        $insertedCount = 0;
        try {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
        } catch (Throwable $e) {
            // ignore if not permitted
        }

        foreach (array_chunk($mergedBatch, 500) as $chunk) {
            DB::beginTransaction();
            try {
                DB::table('outgoing_shipments')->upsert(
                    $chunk,
                    ['no_resi'],
                    ['nama_seller', 'nama_cs', 'nama_penerima', 'no_hp', 'alamat', 'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori', 'color_code', 'fu_pos_date', 'noted', 'sla_days', 'last_tracked_at', 'updated_at']
                );
                DB::commit();
                $insertedCount += count($chunk);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error("ERROR IMPORT CHUNK: " . $e->getMessage());
            }
        }

        Log::info("ShipmentsImport: Inserted/Synced {$insertedCount} rows to DB");

        // 3. Construct Sync Payloads for Google Sheets
        $statusLabelMap = [
            'BIRU'     => 'PAKET SUKSES (DELIVERED)',
            'ORANGE'   => 'PAKET RETUR (RETURN)',
            'KUNING'   => 'SUDAH DI FU (1x)',
            'HIJAU'    => 'FU 2 KALI',
            'BIRU_TUA' => 'FU POS (ESKALASI KC/KCU)',
            'PUTIH'    => 'BELUM DI FOLLOW UP',
        ];

        $syncPayloads = [];
        foreach ($mergedBatch as $item) {
            $cc = $item['color_code'] ?: 'PUTIH';
            $slaStr = $item['sla_days'] !== null ? (string)$item['sla_days'] : '';
            $syncPayloads[] = [
                'resi' => $item['no_resi'],
                'seller' => $item['nama_seller'],
                'status_pos' => $item['status_pos'],
                'keterangan' => $item['keterangan'],
                'status_kategori' => $item['status_kategori'],
                'color_code' => $cc,
                'status_label' => $statusLabelMap[$cc] ?? $cc,
                'fu_type' => $cc,
                'sla' => $slaStr,
                'sla_days' => $slaStr,
            ];
        }

        return $syncPayloads;
    }
}
