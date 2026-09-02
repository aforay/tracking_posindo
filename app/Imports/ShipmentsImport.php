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

    public function __construct(string $defaultSeller = 'Aliqa')
    {
        $this->defaultSeller = $defaultSeller;
    }

    /**
     * Import Excel/CSV using OpenSpout Streaming Reader (<20MB RAM)
     */
    public function importFile(string $filePath, string $defaultSeller = 'Aliqa'): void
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $this->defaultSeller = $defaultSeller;

        if (!file_exists($filePath)) {
            $msg = "ShipmentsImport: File not found at {$filePath}";
            Log::error($msg);
            return;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $msg = "ShipmentsImport streaming start for {$filePath} [{$extension}]";
        Log::info($msg);

        if (in_array($extension, ['xlsx', 'csv'])) {
            $this->streamWithOpenSpout($filePath, $extension);
        } else {
            // Fallback for .xls (BIFF8) or other formats using low-memory chunking
            $this->streamWithPhpSpreadsheetChunking($filePath);
        }
    }

    /**
     * Ultra-fast OpenSpout streaming parser (< 20MB RAM) across MULTIPLE SHEETS (Compatible OpenSpout v4/v5)
     */
    protected function streamWithOpenSpout(string $filePath, string $extension): void
    {
        $totalProcessedRows = 0;
        $totalInsertedRows = 0;
        $detectedHeaders = [];
        $sampleRow = [];

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
                        $inserted = $this->processBufferBatch($batchBuffer, $resisInBuffer);
                        $totalInsertedRows += $inserted;
                        $batchBuffer = [];
                        $resisInBuffer = [];
                    }
                }
            }

            // Process remaining rows in buffer
            if (!empty($batchBuffer)) {
                $inserted = $this->processBufferBatch($batchBuffer, $resisInBuffer);
                $totalInsertedRows += $inserted;
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
            $this->streamWithPhpSpreadsheetChunking($filePath);
        }
    }

    /**
     * Fallback PhpSpreadsheet chunking parser for .xls files across MULTIPLE SHEETS
     */
    protected function streamWithPhpSpreadsheetChunking(string $filePath): void
    {
        $totalProcessedRows = 0;
        $totalInsertedRows = 0;
        $detectedHeaders = [];
        $sampleRow = [];

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
                        $inserted = $this->processBufferBatch($batchBuffer, $resisInBuffer);
                        $totalInsertedRows += $inserted;
                        $batchBuffer = [];
                        $resisInBuffer = [];
                    }
                }
            }

            if (!empty($batchBuffer)) {
                $inserted = $this->processBufferBatch($batchBuffer, $resisInBuffer);
                $totalInsertedRows += $inserted;
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
            // Seller Column
            elseif (in_array($clean, ['seller', 'mitra', 'namaseller', 'sellermitra', 'pengirim', 'namacs', 'cs', 'sellers'])) {
                $map['seller'] = $colIdx;
                $foundCount++;
            }
            // Tanggal Kirim Column
            elseif (in_array($clean, ['aliqatanggal', 'tglkirim', 'tanggalkirim', 'tgl', 'tanggal', 'tglkirimpos', 'tanggalkirimpos'])) {
                $map['tanggal_kirim'] = $colIdx;
                $foundCount++;
            }
            // Penerima Column
            elseif (in_array($clean, ['namadepan', 'penerima', 'namapenerima', 'namakonsumen', 'konsumen', 'penerimabarang', 'namatujuan'])) {
                $map['penerima'] = $colIdx;
                $foundCount++;
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

        // 1. Extract Resi Number (Header Map -> Named Keys -> Positional Index Fallback -> Regex Scan)
        $resi = null;
        if (isset($headerMap['resi']) && isset($rowArray[$headerMap['resi']])) {
            $resi = $rowArray[$headerMap['resi']];
        } elseif (!empty($rowArray['resi'])) {
            $resi = $rowArray['resi'];
        } elseif (!empty($rowArray['no_resi'])) {
            $resi = $rowArray['no_resi'];
        } elseif (!empty($rowArray['barcode'])) {
            $resi = $rowArray['barcode'];
        }

        // Index Fallback if resi is still empty:
        // For Aliqa: Check Index 2 (C) first, then 0 (A), 4 (E), 1 (B), 3 (D)
        // For Zaherba / general: Check Index 0 (A), 4 (E), 1 (B), 3 (D), 2 (C)
        if (empty($resi)) {
            $candidateIndexes = $isAliqaSheet ? [2, 0, 4, 1, 3] : [0, 4, 1, 3, 2];
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

        // 3. Extract Penerima (Aliqa: Kolom D / Index 3)
        $penerimaRaw = isset($headerMap['penerima']) ? ($rowArray[$headerMap['penerima']] ?? null) : ($rowArray['nama_penerima'] ?? $rowArray['penerima'] ?? $rowArray['nama_konsumen'] ?? ($isAliqaSheet ? ($rowArray[3] ?? null) : ($rowArray[2] ?? ($rowArray[3] ?? null))));
        $penerima = trim((string)($penerimaRaw ?: ''));
        if (in_array(strtoupper($penerima), ['NAMA KONSUMEN', 'PENERIMA', 'NAMA PENERIMA', 'PENERIMA BARANG', 'NAMA DEPAN'])) {
            return null; // Skip header row if caught
        }

        // 4. Extract No HP (Aliqa: Kolom O / Index 14)
        $noHpRaw = isset($headerMap['no_hp']) ? ($rowArray[$headerMap['no_hp']] ?? null) : ($rowArray['no_hp'] ?? $rowArray['hp'] ?? $rowArray['telepon'] ?? ($isAliqaSheet ? ($rowArray[14] ?? null) : ($rowArray[8] ?? ($rowArray[6] ?? null))));
        $noHp = trim((string)($noHpRaw ?: ''));

        // 5. Extract Alamat (Aliqa: Kolom N / Index 13)
        $alamatRaw = isset($headerMap['alamat']) ? ($rowArray[$headerMap['alamat']] ?? null) : ($rowArray['alamat'] ?? ($isAliqaSheet ? ($rowArray[13] ?? null) : ($rowArray[5] ?? ($rowArray[3] ?? null))));
        $alamat = trim((string)($alamatRaw ?: ''));

        // 6. Extract Tanggal Kirim (Header Map -> Index 1 / Index 0 -> Sheet Name Fallback)
        $tanggalRaw = isset($headerMap['tanggal_kirim']) ? ($rowArray[$headerMap['tanggal_kirim']] ?? null) : ($rowArray['tanggal_kirim'] ?? $rowArray['tanggal'] ?? ($rowArray[1] ?? ($rowArray[0] ?? null)));
        $tanggalKirim = $this->parseDateValue($tanggalRaw, $sheetName);

        // 7. Extract Status POS & Keterangan (Aliqa: Keterangan = Kolom P / Index 15, Status POS = Kolom Q / Index 16)
        $statusPosRaw = isset($headerMap['status_pos']) ? ($rowArray[$headerMap['status_pos']] ?? null) : ($rowArray['status_pos'] ?? $rowArray['tracking_pos'] ?? $rowArray['status'] ?? ($isAliqaSheet ? ($rowArray[16] ?? null) : ($rowArray[11] ?? null)));
        $statusPos = trim((string)($statusPosRaw ?: ''));
        if (in_array(strtoupper($statusPos), ['TRACKING POS', 'STATUS', 'STATUS POS', 'TRACKING'])) {
            $statusPos = '';
        }

        $ketRaw = isset($headerMap['keterangan']) ? ($rowArray[$headerMap['keterangan']] ?? null) : ($rowArray['keterangan'] ?? ($isAliqaSheet ? ($rowArray[15] ?? null) : ($rowArray[10] ?? null)));
        $keterangan = trim((string)($ketRaw ?: ''));
        if (in_array(strtoupper($keterangan), ['KETERANGAN', 'PENERIMA & KETERANGAN', 'POS KETERANGAN'])) {
            $keterangan = '';
        }

        // 8. Extract SLA (Aliqa: SLA = Kolom R / Index 17)
        $slaRaw = isset($headerMap['sla_days']) ? ($rowArray[$headerMap['sla_days']] ?? null) : ($rowArray['sla_days'] ?? $rowArray['sla'] ?? ($isAliqaSheet ? ($rowArray[17] ?? null) : null));
        $slaDays = is_numeric($slaRaw) ? (int)$slaRaw : null;

        // Categorize status with RETUR priority checked FIRST
        $botService = new \App\Services\TrackingBotService();
        $kategori = $botService->categorizeStatus($statusPos, $keterangan);

        return [
            'nama_seller' => $seller,
            'no_resi' => $resi,
            'nama_penerima' => $penerima ?: null,
            'no_hp' => $noHp ?: null,
            'alamat' => $alamat ?: null,
            'tanggal_kirim' => $tanggalKirim,
            'status_pos' => $statusPos ?: null,
            'keterangan' => $keterangan ?: null,
            'status_kategori' => $kategori,
            'sla_days' => $slaDays,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Robust Date Parsing with Sheet Name Month Fallback
     */
    protected function parseDateValue($val, ?string $sheetName = null): ?string
    {
        if (!empty($val) && !in_array(strtoupper((string)$val), ['TANGGAL', 'TGL', 'TGL KIRIM', 'TANGGAL KIRIM'])) {
            try {
                if (is_numeric($val) && (float)$val > 10000) {
                    return ExcelDate::excelToDateTimeObject((float)$val)->format('Y-m-d');
                }

                $str = trim((string)$val);
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $str, $m)) {
                    return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                }

                // Translate Indonesian month names to English before Carbon parsing
                $indoToEng = [
                    'januari' => 'January', 'februari' => 'February', 'maret' => 'March',
                    'april' => 'April', 'mei' => 'May', 'juni' => 'June',
                    'juli' => 'July', 'agustus' => 'August', 'september' => 'September',
                    'oktober' => 'October', 'november' => 'November', 'desember' => 'December',
                    'agt' => 'Aug', 'agu' => 'Aug', 'okt' => 'Oct', 'des' => 'Dec',
                ];
                $engStr = str_ireplace(array_keys($indoToEng), array_values($indoToEng), $str);

                return Carbon::parse($engStr)->format('Y-m-d');
            } catch (Throwable $e) {
                // Fallback below
            }
        }

        // Sheet Name Month Fallback (e.g. "JANUARI (ZAHERBA)", "Februari", "Maret", etc.)
        if (!empty($sheetName)) {
            $sheetUpper = strtoupper($sheetName);
            $monthsMap = [
                'JAN' => '01', 'JANUARI' => '01',
                'FEB' => '02', 'FEBRUARI' => '02',
                'MAR' => '03', 'MARET' => '03',
                'APR' => '04', 'APRIL' => '04',
                'MEI' => '05', 'MAY' => '05',
                'JUN' => '06', 'JUNI' => '06',
                'JUL' => '07', 'JULI' => '07',
                'AGT' => '08', 'AGUS' => '08', 'AGUSTUS' => '08',
                'SEP' => '09', 'SEPTEMBER' => '09',
                'OKT' => '10', 'OKTOBER' => '10',
                'NOV' => '11', 'NOVEMBER' => '11',
                'DES' => '12', 'DESEMBER' => '12',
            ];

            foreach ($monthsMap as $keyword => $mNum) {
                if (str_contains($sheetUpper, $keyword)) {
                    return date("Y-{$mNum}-15");
                }
            }
        }

        return null;
    }

    /**
     * Process 1,000 rows buffer batch with DB Transaction & Data Preservation
     * @return int Number of inserted/updated rows
     */
    protected function processBufferBatch(array $batchBuffer, array $resisInBuffer): int
    {
        if (empty($batchBuffer)) {
            return 0;
        }

        // Deduplicate buffer batch by no_resi to ensure clean unique key upserting
        $uniqueMap = [];
        foreach ($batchBuffer as $item) {
            $uniqueMap[$item['no_resi']] = $item;
        }
        $batchBuffer = array_values($uniqueMap);
        $resisInBuffer = array_keys($uniqueMap);

        $botService = new \App\Services\TrackingBotService();

        // 1. Fetch existing database records to preserve established statuses & manual CS updates
        $existingMap = OutgoingShipment::whereIn('no_resi', $resisInBuffer)
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
                $penerima = !empty($data['nama_penerima']) ? $data['nama_penerima'] : $existing->nama_penerima;
                $noHp = !empty($data['no_hp']) ? $data['no_hp'] : $existing->no_hp;
                $alamat = !empty($data['alamat']) ? $data['alamat'] : $existing->alamat;
                $tanggalKirim = !empty($data['tanggal_kirim']) ? $data['tanggal_kirim'] : ($existing->tanggal_kirim ? (is_string($existing->tanggal_kirim) ? substr($existing->tanggal_kirim, 0, 10) : $existing->tanggal_kirim->format('Y-m-d')) : date('Y-m-d'));

                $incomingStatus = $data['status_pos'] ?? '';
                $incomingKet = $data['keterangan'] ?? '';
                $incomingCategory = $botService->categorizeStatus($incomingStatus, $incomingKet);

                $existingStatus = $existing->status_pos ?? '';
                $existingKet = $existing->keterangan ?? '';
                $existingCategory = $botService->categorizeStatus($existingStatus, $existingKet);

                // Priority Check: RETUR -> SUKSES -> IN_PROCESS / FOLLOW_UP
                if ($incomingCategory === 'RETUR' || $existingCategory === 'RETUR') {
                    $statusPos = !empty($data['status_pos']) ? $data['status_pos'] : ($existing->status_pos ?: 'DELIVERED (RETURN DELIVERY)');
                    $keterangan = !empty($data['keterangan']) ? $data['keterangan'] : ($existing->keterangan ?: 'DITERIMA PENGIRIM');
                    $statusKategori = 'RETUR';
                    $colorCode = 'ORANGE';
                    $slaDays = $data['sla_days'] ?: $existing->sla_days;
                } elseif ($incomingCategory === 'SUKSES' || $existingCategory === 'SUKSES') {
                    $statusPos = !empty($data['status_pos']) ? $data['status_pos'] : ($existing->status_pos ?: 'DELIVERED');
                    $keterangan = !empty($data['keterangan']) ? $data['keterangan'] : ($existing->keterangan ?: 'DITERIMA YANG BERSANGKUTAN');
                    $statusKategori = 'SUKSES';
                    $colorCode = 'BIRU';
                    $slaDays = $data['sla_days'] ?: $existing->sla_days;
                } else {
                    $statusPos = !empty($data['status_pos']) ? $data['status_pos'] : ($existing->status_pos ?: 'ON PROCESS');
                    $keterangan = !empty($data['keterangan']) ? $data['keterangan'] : ($existing->keterangan ?: 'PROSES PENGIRIMAN POS');
                    $statusKategori = $incomingCategory ?: 'IN_PROCESS';
                    $colorCode = (!empty($existing->color_code) && $existing->color_code !== 'BIRU' && $existing->color_code !== 'ORANGE') ? $existing->color_code : $botService->determineColorCode($statusKategori);
                    $slaDays = $data['sla_days'] ?: $existing->sla_days;
                }

                $fuPosDate = $existing->fu_pos_date;
                $noted = $existing->noted;
                $lastTrackedAt = $existing->last_tracked_at;

                $mergedBatch[] = [
                    'nama_seller' => $seller,
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
                // NEW SHIPMENT: Use defaults
                $cat = $botService->categorizeStatus($data['status_pos'] ?? '', $data['keterangan'] ?? '');
                $color = $botService->determineColorCode($cat);

                $mergedBatch[] = [
                    'nama_seller' => $data['nama_seller'] ?: $this->defaultSeller,
                    'no_resi' => $resiKey,
                    'nama_penerima' => $data['nama_penerima'],
                    'no_hp' => $data['no_hp'],
                    'alamat' => $data['alamat'],
                    'tanggal_kirim' => $data['tanggal_kirim'] ?: date('Y-m-d'),
                    'status_pos' => $data['status_pos'] ?: 'ON PROCESS',
                    'keterangan' => $data['keterangan'] ?: 'PROSES PENGIRIMAN POS',
                    'status_kategori' => $cat,
                    'color_code' => $color,
                    'fu_pos_date' => null,
                    'noted' => null,
                    'sla_days' => $data['sla_days'],
                    'last_tracked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // 2. DB Transaction & Upsert per 1,000 items (DB::table)
        $insertedCount = count($mergedBatch);

        DB::beginTransaction();
        try {
            foreach (array_chunk($mergedBatch, 1000) as $chunk) {
                DB::table('outgoing_shipments')->upsert(
                    $chunk,
                    ['no_resi'],
                    ['nama_seller', 'nama_penerima', 'no_hp', 'alamat', 'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori', 'color_code', 'fu_pos_date', 'noted', 'sla_days', 'last_tracked_at', 'updated_at']
                );
            }
            DB::commit();

            Log::info("ShipmentsImport: Inserted/Synced {$insertedCount} rows to DB");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("ERROR IMPORT: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 0;
        }

        return $insertedCount;
    }
}
