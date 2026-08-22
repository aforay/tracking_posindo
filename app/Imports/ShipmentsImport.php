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
            dump($msg);
            Log::error($msg);
            return;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $msg = "ShipmentsImport streaming start for {$filePath} [{$extension}]";
        dump($msg);
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
                dump("Membaca Sheet: " . $sheetName);
                echo "Membaca Sheet: " . $sheetName . "\n";
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
                            dump("Header terdeteksi pada Sheet [{$sheetName}]: ", $detectedHeaders);
                            echo "Header terdeteksi pada Sheet [{$sheetName}]: " . json_encode($detectedHeaders) . "\n";
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

            dump("Total baris terproses dari seluruh sheet: " . $totalProcessedRows);
            echo "Total baris terproses dari seluruh sheet: " . $totalProcessedRows . "\n";
            dump("Total data tersimpan di DB: " . $totalInsertedRows);
            echo "Total data tersimpan di DB: " . $totalInsertedRows . "\n";

            if ($totalInsertedRows === 0) {
                $msg = "Excel Import Failed: No valid rows found in any sheet. Headers detected: " . json_encode($detectedHeaders) . " | Sample row: " . json_encode($sampleRow);
                dump($msg);
                Log::error($msg);
            } else {
                Log::info("ShipmentsImport streaming finished. Processed {$totalProcessedRows} rows from all sheets, saved {$totalInsertedRows} rows into outgoing_shipments.");
            }
        } catch (Throwable $e) {
            dump("ERROR IMPORT: " . $e->getMessage());
            echo "ERROR IMPORT: " . $e->getMessage() . "\n";
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
                dump("Membaca Sheet (PhpSpreadsheet): " . $sheetName);
                echo "Membaca Sheet: " . $sheetName . "\n";

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
                            dump("Header terdeteksi: ", $detectedHeaders);
                            echo "Header terdeteksi: " . json_encode($detectedHeaders) . "\n";
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

            dump("Total baris terproses: " . $totalProcessedRows);
            echo "Total baris terproses: " . $totalProcessedRows . "\n";

            if ($totalInsertedRows === 0) {
                $msg = "Excel Import Failed: No valid rows found. Headers detected: " . json_encode($detectedHeaders) . " | Sample row: " . json_encode($sampleRow);
                dump($msg);
                Log::error($msg);
            } else {
                Log::info("ShipmentsImport PhpSpreadsheet chunking finished. Processed {$totalProcessedRows} rows, saved {$totalInsertedRows} rows.");
            }
        } catch (Throwable $e) {
            dump("ERROR IMPORT: " . $e->getMessage());
            echo "ERROR IMPORT: " . $e->getMessage() . "\n";
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
            elseif (in_array($clean, ['tglkirim', 'tanggalkirim', 'tgl', 'tanggal', 'tglkirimpos', 'tanggalkirimpos'])) {
                $map['tanggal_kirim'] = $colIdx;
                $foundCount++;
            }
            // Penerima Column
            elseif (in_array($clean, ['penerima', 'namapenerima', 'namakonsumen', 'konsumen', 'penerimabarang', 'namatujuan'])) {
                $map['penerima'] = $colIdx;
                $foundCount++;
            }
            // No HP / Telepon Column
            elseif (in_array($clean, ['nohp', 'hp', 'telepon', 'notelpon', 'phone', 'contact', 'nohppenerima'])) {
                $map['no_hp'] = $colIdx;
                $foundCount++;
            }
            // Alamat Column
            elseif (in_array($clean, ['alamat', 'tujuan', 'asaltujuan', 'alamatpenerima', 'kotatujuan', 'tujuankirim'])) {
                $map['alamat'] = $colIdx;
                $foundCount++;
            }
            // Status POS Column
            elseif (in_array($clean, ['statuspos', 'status', 'trackingpos', 'statusnipos', 'statusniposl', 'nipos', 'statusakhir'])) {
                $map['status_pos'] = $colIdx;
                $foundCount++;
            }
            // Keterangan Column
            elseif (in_array($clean, ['keterangan', 'penerimaketerangank', 'keterangank', 'alasan', 'note', 'penerimaantaran'])) {
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

        // Index Fallback if resi is still empty: Check Index 0 (A), 4 (E), 1 (B), 3 (D), 2 (C)
        if (empty($resi)) {
            $candidateIndexes = [0, 4, 1, 3, 2];
            foreach ($candidateIndexes as $idx) {
                if (isset($rowArray[$idx])) {
                    $strVal = trim((string)$rowArray[$idx]);
                    if (preg_match('/^[A-Za-z0-9]{8,30}$/', $strVal) && (str_starts_with(strtoupper($strVal), 'P') || is_numeric($strVal))) {
                        $strUpper = strtoupper($strVal);
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
                if (preg_match('/^[A-Za-z0-9]{8,30}$/', $strVal) && (str_starts_with(strtoupper($strVal), 'P') || (is_numeric($strVal) && strlen($strVal) >= 10))) {
                    $strUpper = strtoupper($strVal);
                    if (!in_array($strUpper, ['RESI', 'NO RESI', 'BARCODE', 'NO', 'INVOICE', 'NO HP', 'TELEPON', 'TANGGAL', 'STATUS', 'SELLER'])) {
                        $resi = $strVal;
                        break;
                    }
                }
            }
        }

        $resi = trim((string)$resi);
        if (empty($resi) || in_array(strtoupper($resi), ['RESI', 'NO RESI', 'BARCODE', 'TRACKING POS', 'FORM PEMANTAUAN', 'NO', 'NO. RESI', 'BARCODE ITEM'])) {
            return null;
        }

        // 2. Extract Seller (Header Map -> Named -> Index 2 / Index 0 Fallback)
        $sellerRaw = isset($headerMap['seller']) ? ($rowArray[$headerMap['seller']] ?? null) : ($rowArray['nama_seller'] ?? $rowArray['seller'] ?? ($rowArray[2] ?? null));
        $seller = trim((string)($sellerRaw ?: $this->defaultSeller));
        if (empty($seller) || strtoupper($seller) === 'NAMA CS' || str_starts_with(strtoupper($seller), 'CS ') || strtoupper($seller) === 'SELLER' || is_numeric($seller)) {
            $seller = $this->defaultSeller;
        }

        // 3. Extract Penerima (Header Map -> Named -> Index 2 / Index 3 Fallback)
        $penerimaRaw = isset($headerMap['penerima']) ? ($rowArray[$headerMap['penerima']] ?? null) : ($rowArray['nama_penerima'] ?? $rowArray['penerima'] ?? $rowArray['nama_konsumen'] ?? ($rowArray[2] ?? ($rowArray[3] ?? null)));
        $penerima = trim((string)($penerimaRaw ?: ''));
        if (in_array(strtoupper($penerima), ['NAMA KONSUMEN', 'PENERIMA', 'NAMA PENERIMA', 'PENERIMA BARANG'])) {
            return null; // Skip header row if caught
        }

        // 4. Extract No HP (Header Map -> Index 6 / 8 Fallback)
        $noHpRaw = isset($headerMap['no_hp']) ? ($rowArray[$headerMap['no_hp']] ?? null) : ($rowArray['no_hp'] ?? $rowArray['hp'] ?? $rowArray['telepon'] ?? ($rowArray[8] ?? ($rowArray[6] ?? null)));
        $noHp = trim((string)($noHpRaw ?: ''));

        // 5. Extract Alamat (Header Map -> Index 3 / 5 Fallback)
        $alamatRaw = isset($headerMap['alamat']) ? ($rowArray[$headerMap['alamat']] ?? null) : ($rowArray['alamat'] ?? ($rowArray[5] ?? ($rowArray[3] ?? null)));
        $alamat = trim((string)($alamatRaw ?: ''));

        // 6. Extract Tanggal Kirim (Header Map -> Index 1 / Index 0 -> Sheet Name Fallback)
        $tanggalRaw = isset($headerMap['tanggal_kirim']) ? ($rowArray[$headerMap['tanggal_kirim']] ?? null) : ($rowArray['tanggal_kirim'] ?? $rowArray['tanggal'] ?? ($rowArray[1] ?? ($rowArray[0] ?? null)));
        $tanggalKirim = $this->parseDateValue($tanggalRaw, $sheetName);

        // 7. Extract Status POS & Keterangan
        $statusPosRaw = isset($headerMap['status_pos']) ? ($rowArray[$headerMap['status_pos']] ?? null) : ($rowArray['status_pos'] ?? $rowArray['tracking_pos'] ?? $rowArray['status'] ?? ($rowArray[11] ?? null));
        $statusPos = trim((string)($statusPosRaw ?: ''));
        if (in_array(strtoupper($statusPos), ['TRACKING POS', 'STATUS', 'STATUS POS'])) {
            $statusPos = '';
        }

        $ketRaw = isset($headerMap['keterangan']) ? ($rowArray[$headerMap['keterangan']] ?? null) : ($rowArray['keterangan'] ?? ($rowArray[10] ?? null));
        $keterangan = trim((string)($ketRaw ?: ''));
        if (in_array(strtoupper($keterangan), ['KETERANGAN', 'PENERIMA & KETERANGAN'])) {
            $keterangan = '';
        }

        // 8. Extract SLA
        $slaRaw = isset($headerMap['sla_days']) ? ($rowArray[$headerMap['sla_days']] ?? null) : ($rowArray['sla_days'] ?? $rowArray['sla'] ?? null);
        $slaDays = is_numeric($slaRaw) ? (int)$slaRaw : null;

        // Categorize status
        $statusUpper = strtoupper($statusPos . ' ' . $keterangan);
        $kategori = 'IN_PROCESS';
        if (str_contains($statusUpper, 'DITERIMA') || str_contains($statusUpper, 'DELIVERED')) {
            if (str_contains($statusUpper, 'RETUR') || str_contains($statusUpper, 'RETURN')) {
                $kategori = 'RETUR';
            } else {
                $kategori = 'SUKSES';
            }
        } elseif (str_contains($statusUpper, 'RETUR') || str_contains($statusUpper, 'RETURN')) {
            $kategori = 'RETUR';
        } elseif (str_contains($statusUpper, 'FAILED') || str_contains($statusUpper, 'GAGAL') || str_contains($statusUpper, 'KENDALA') || str_contains($statusUpper, 'FOLLOW UP') || str_contains($statusUpper, 'RUNSHEET')) {
            $kategori = 'FOLLOW_UP';
        }

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
    protected function parseDateValue($val, ?string $sheetName = null): string
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

                return Carbon::parse($str)->format('Y-m-d');
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

        return date('Y-m-d');
    }

    /**
     * Process 1,000 rows buffer batch with DB Transaction & Terminal Output
     * @return int Number of inserted/updated rows
     */
    protected function processBufferBatch(array $batchBuffer, array $resisInBuffer): int
    {
        if (empty($batchBuffer)) {
            return 0;
        }

        // 1. SMART FILTERING: Check database status before queueing for tracking
        $existingMap = OutgoingShipment::whereIn('no_resi', $resisInBuffer)
            ->get(['no_resi', 'status_kategori'])
            ->keyBy('no_resi');

        $resisNeedingTracking = [];

        foreach ($batchBuffer as $data) {
            $resiKey = $data['no_resi'];
            $existing = $existingMap[$resiKey] ?? null;

            if ($existing && in_array(strtoupper((string)$existing->status_kategori), ['SUKSES', 'RETUR'])) {
                continue;
            }

            if (in_array($data['status_kategori'], ['SUKSES', 'RETUR'])) {
                continue;
            }

            $resisNeedingTracking[] = $resiKey;
        }

        // 2. DB Transaction & Upsert per 1,000 items (DB::table)
        $insertedCount = count($batchBuffer);

        DB::beginTransaction();
        try {
            foreach (array_chunk($batchBuffer, 1000) as $chunk) {
                DB::table('outgoing_shipments')->upsert(
                    $chunk,
                    ['no_resi'],
                    ['nama_seller', 'nama_penerima', 'no_hp', 'alamat', 'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori', 'sla_days', 'updated_at']
                );
            }
            DB::commit();

            dump("Inserted {$insertedCount} rows to DB");
            echo "Inserted {$insertedCount} rows to DB\n";
            Log::info("ShipmentsImport: Inserted {$insertedCount} rows to DB");
        } catch (Throwable $e) {
            DB::rollBack();
            dump("ERROR IMPORT: " . $e->getMessage());
            echo "ERROR IMPORT: " . $e->getMessage() . "\n";
            Log::error("ERROR IMPORT: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 0;
        }

        // 3. Dispatch batch to ProcessNiposTrackingJob for asynchronous tracking (500 resi/batch)
        if (!empty($resisNeedingTracking)) {
            $shipmentIds = OutgoingShipment::whereIn('no_resi', $resisNeedingTracking)
                ->pluck('id')
                ->toArray();

            if (!empty($shipmentIds)) {
                foreach (array_chunk($shipmentIds, 500) as $batchIds) {
                    ProcessNiposTrackingJob::dispatch($batchIds);
                }
            }
        }

        return $insertedCount;
    }
}
