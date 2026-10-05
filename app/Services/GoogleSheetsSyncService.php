<?php

namespace App\Services;

use App\Imports\ShipmentsImport;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleSheetsSyncService
{
    protected ShipmentsImport $importer;

    public function __construct()
    {
        $this->importer = new ShipmentsImport('Aliqa');
    }

    /**
     * Synchronize Outgoing Shipments from a Public Google Spreadsheet
     *
     * @param string|null $spreadsheetIdOrUrl Custom Google Sheet URL or ID
     * @param string $defaultSeller Default seller name if blank
     * @param string|null $targetSheet Target specific sheet name (e.g. "AGUSTUS (ZAHERBA)")
     * @param int|string|array|null $targetMonth Target specific month number (1-12), comma list, array, or ALL
     * @param bool $withColors Whether to pull colors from Google Sheets once at the end
     * @return array Sync metrics summary
     */
    public function sync(?string $spreadsheetIdOrUrl = null, string $defaultSeller = 'Aliqa', ?string $targetSheet = null, $targetMonth = null, bool $withColors = true): array
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $isAliqaSeller = str_contains(strtoupper($defaultSeller), 'ALIQA') || str_contains(strtoupper((string)$spreadsheetIdOrUrl), 'ALIQA') || str_contains(strtoupper((string)$targetSheet), 'ALIQA');

        // 1. Resolve Spreadsheet ID
        $spreadsheetId = SystemSetting::extractSpreadsheetId($spreadsheetIdOrUrl);
        if (empty($spreadsheetId) && (empty($spreadsheetIdOrUrl) || str_contains(strtoupper((string)$spreadsheetIdOrUrl), 'ALIQA') || str_contains(strtoupper((string)$spreadsheetIdOrUrl), 'ZAHERBA'))) {
            $spreadsheetId = $isAliqaSeller
                ? env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg')
                : env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw');
        }

        if (empty($spreadsheetId)) {
            $savedUrl = SystemSetting::get('google_sheet_url') ?: SystemSetting::get('google_sheet_id');
            $spreadsheetId = SystemSetting::extractSpreadsheetId($savedUrl);
        }

        // Fallback default sample Google Sheet ID if not configured
        if (empty($spreadsheetId)) {
            $spreadsheetId = $isAliqaSeller
                ? env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg')
                : env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw');
        }

        $startMsg = "GoogleSheetsSyncService: Starting sync for Spreadsheet ID [{$spreadsheetId}] (Seller: {$defaultSeller})";
        Log::info($startMsg);

        // Auto-Isolasi Data: Jika spreadsheet ID berbeda atau sinkronisasi semua sheet, hapus data lama seller agar tidak tercampur
        $sellerName = $isAliqaSeller ? 'Mitra Aliqa' : 'Mitra Zaherba';
        $shortName = $isAliqaSeller ? 'Aliqa' : 'Zaherba';
        $sellerKey = $isAliqaSeller ? 'aliqa' : 'zaherba';

        $lastSyncedId = SystemSetting::get("last_synced_spreadsheet_id_{$sellerKey}");
        $isDifferentSheet = ($lastSyncedId && $lastSyncedId !== $spreadsheetId);
        // Auto-Isolasi Data: HANYA jika spreadsheet ID berbeda (link spreadsheet diganti baru), hapus data lama seller agar tidak tercampur
        if ($isDifferentSheet) {
            $deletedCount = \App\Models\OutgoingShipment::fastPurgeSellers([$sellerName, $shortName]);
            Log::info("GoogleSheetsSyncService Auto-Isolasi: Dibersihkan {$deletedCount} data lama {$sellerName} karena link spreadsheet berganti.");
        }
        SystemSetting::set("last_synced_spreadsheet_id_{$sellerKey}", $spreadsheetId);

        // 2. Discover Sheet Names (Januari - Desember)
        $sheetNames = $this->discoverSheetNames($spreadsheetId, $defaultSeller);

        // Filter sheetNames if targetSheet or targetMonth is specified
        if (!empty($targetSheet) && strtoupper((string)$targetSheet) !== 'ALL') {
            $tSheetUpper = strtoupper(trim($targetSheet));
            $filtered = array_filter($sheetNames, function ($sName) use ($tSheetUpper) {
                return str_contains(strtoupper($sName), $tSheetUpper) || strtoupper($sName) === $tSheetUpper;
            });
            if (!empty($filtered)) {
                $sheetNames = array_values($filtered);
            }
        } elseif (!empty($targetMonth) && strtoupper((string)(is_array($targetMonth) ? implode(',', $targetMonth) : $targetMonth)) !== 'ALL' && (string)(is_array($targetMonth) ? implode(',', $targetMonth) : $targetMonth) !== '0') {
            $monthKeywords = [
                1 => ['JAN', 'JANUARI'], 2 => ['FEB', 'FEBRUARI'], 3 => ['MAR', 'MARET'],
                4 => ['APR', 'APRIL'], 5 => ['MEI', 'MAY'], 6 => ['JUN', 'JUNI'],
                7 => ['JUL', 'JULI'], 8 => ['AGT', 'AGUS', 'AGUSTUS', 'AUG'], 9 => ['SEP', 'SEPTEMBER'],
                10 => ['OKT', 'OKTOBER', 'OCT'], 11 => ['NOV', 'NOVEMBER'], 12 => ['DES', 'DESEMBER', 'DEC'],
            ];

            $mList = is_array($targetMonth) ? $targetMonth : explode(',', (string)$targetMonth);
            $mList = array_map('trim', $mList);

            if (!in_array('ALL', array_map('strtoupper', $mList))) {
                $allKeywords = [];
                foreach ($mList as $mItem) {
                    $mNum = (int)$mItem;
                    if ($mNum >= 1 && $mNum <= 12 && isset($monthKeywords[$mNum])) {
                        $allKeywords = array_merge($allKeywords, $monthKeywords[$mNum]);
                    }
                }

                if (!empty($allKeywords)) {
                    $filtered = array_filter($sheetNames, function ($sName) use ($allKeywords) {
                        $sUpper = strtoupper($sName);
                        foreach ($allKeywords as $kw) {
                            if (str_contains($sUpper, $kw)) return true;
                        }
                        return false;
                    });
                    $filteredValues = array_values($filtered);
                    if (!empty($filteredValues)) {
                        $sheetNames = $filteredValues;
                    }
                }
            }
        }

        Log::info("Filtered sheet list (" . count($sheetNames) . " sheets): " . json_encode($sheetNames));

        \Illuminate\Support\Facades\Cache::put('sync_progress', [
            'is_syncing' => true,
            'current_sheet' => 'Memulai...',
            'current_sheet_index' => 0,
            'total_sheets' => count($sheetNames),
            'processed_rows' => 0,
            'inserted_rows' => 0,
            'percentage' => 5,
            'message' => 'Memulai sinkronisasi Google Sheets...',
            'updated_at' => now()->toDateTimeString(),
        ], 3600);

        $totalProcessed = 0;
        $totalInserted = 0;
        $processedSheets = [];
        $totalSheetCount = max(1, count($sheetNames));

        // 3. Iterate and stream data from each Sheet
        foreach ($sheetNames as $sheetIndex => $sheetName) {
            $sheetSeller = $defaultSeller;
            if (str_contains(strtoupper($sheetName), 'ZAHERBA')) {
                $sheetSeller = 'Mitra Zaherba';
            } elseif (str_contains(strtoupper($sheetName), 'ALIQA')) {
                $sheetSeller = 'Mitra Aliqa';
            }

            $currentPct = round((($sheetIndex) / $totalSheetCount) * 90) + 5;
            \Illuminate\Support\Facades\Cache::put('sync_progress', [
                'is_syncing' => true,
                'current_sheet' => $sheetName,
                'current_sheet_index' => $sheetIndex + 1,
                'total_sheets' => $totalSheetCount,
                'processed_rows' => $totalProcessed,
                'inserted_rows' => $totalInserted,
                'percentage' => $currentPct,
                'message' => "Membaca sheet: {$sheetName} (Tab " . ($sheetIndex + 1) . "/{$totalSheetCount})...",
                'updated_at' => now()->toDateTimeString(),
            ], 3600);

            Log::info("GoogleSheetsSyncService: Fetching CSV for sheet -> {$sheetName} (Seller: {$sheetSeller})");

            try {
                $response = null;
                $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
                
                try {
                    $response = Http::timeout(120)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            'Accept' => 'text/csv,text/plain,*/*',
                        ])
                        ->get($csvUrl);
                } catch (Throwable $e) {
                    Log::warning("GoogleSheetsSyncService: Primary fetch failed for ID [{$spreadsheetId}]: " . $e->getMessage());
                }

                if (!$response || $response->failed() || empty(trim((string)$response->body()))) {
                    Log::warning("GoogleSheetsSyncService: Sheet [{$sheetName}] empty or not accessible via CSV export.");
                    continue;
                }

                $csvBody = (string)$response->body();
                
                // Parse CSV stream line by line
                $metrics = $this->parseCsvContent($csvBody, $sheetName, $sheetSeller);
                
                if ($metrics['processed'] > 0) {
                    $totalProcessed += $metrics['processed'];
                    $totalInserted += $metrics['inserted'];
                    $processedSheets[] = $sheetName;

                    $sheetDoneMsg = "Sheet [{$sheetName}] finished: {$metrics['processed']} rows processed, {$metrics['inserted']} rows inserted/updated.";
                    Log::info($sheetDoneMsg);
                }
            } catch (Throwable $e) {
                $errMsg = "Error syncing sheet [{$sheetName}]: " . $e->getMessage();
                Log::error($errMsg);
            }
        }

        $doneSummary = [
            'status' => 'success',
            'spreadsheet_id' => $spreadsheetId,
            'total_sheets' => count($processedSheets),
            'sheets' => $processedSheets,
            'total_rows_processed' => $totalProcessed,
            'total_rows_inserted' => $totalInserted,
        ];

        $totalColors = 0;
        if ($withColors) {
            \Illuminate\Support\Facades\Cache::put('sync_progress', [
                'is_syncing' => true,
                'current_sheet' => 'Menyinkronkan Warna...',
                'current_sheet_index' => count($processedSheets),
                'total_sheets' => count($processedSheets),
                'processed_rows' => $totalProcessed,
                'inserted_rows' => $totalInserted,
                'percentage' => 95,
                'message' => 'Menyinkronkan warna status FU dari Google Sheets...',
                'updated_at' => now()->toDateTimeString(),
            ], 3600);

            try {
                $colorRes = $this->pullColorsFromSheet($spreadsheetId, $targetSheet ?: null, $defaultSeller);
                $totalColors = $colorRes['updated_count'] ?? 0;
                Log::info("GoogleSheetsSyncService: Finished pulling colors. Updated: {$totalColors}");
            } catch (Throwable $e) {
                Log::warning("GoogleSheetsSyncService: pullColorsFromSheet error: " . $e->getMessage());
            }
        }

        $doneSummary['colors_updated'] = $totalColors;

        \Illuminate\Support\Facades\Cache::put('sync_progress', [
            'is_syncing' => false,
            'current_sheet' => 'Selesai',
            'current_sheet_index' => count($processedSheets),
            'total_sheets' => count($processedSheets),
            'processed_rows' => $totalProcessed,
            'inserted_rows' => $totalInserted,
            'percentage' => 100,
            'message' => "Sinkronisasi selesai! {$totalProcessed} baris data" . ($totalColors > 0 ? " dan {$totalColors} warna status FU" : "") . " berhasil disinkronkan.",
            'updated_at' => now()->toDateTimeString(),
        ], 3600);

        // Bust months counts and stats caches so UI immediately updates
        foreach (['Mitra Aliqa', 'Mitra Zaherba', 'Aliqa', 'Zaherba'] as $s) {
            foreach ([date('Y'), date('Y') - 1, date('Y') + 1] as $y) {
                \Illuminate\Support\Facades\Cache::forget('months_counts_' . md5("{$s}_{$y}"));
            }
        }

        Log::info("GoogleSheetsSyncService finished: " . json_encode($doneSummary));

        return $doneSummary;
    }

    /**
     * Sync a single Google Sheet tab
     */
    public function syncSingleSheet(string $spreadsheetId, string $sheetName, string $defaultSeller = 'Aliqa', bool $withColors = false): array
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $sheetSeller = $defaultSeller;
        if (str_contains(strtoupper($sheetName), 'ZAHERBA')) {
            $sheetSeller = 'Mitra Zaherba';
        } elseif (str_contains(strtoupper($sheetName), 'ALIQA')) {
            $sheetSeller = 'Mitra Aliqa';
        }

        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);

        $response = Http::timeout(60)
            ->retry(2, 500)
            ->withOptions([
                'curl' => [
                    CURLOPT_ENCODING => '', // GZIP / Deflate streaming for fast download
                ],
            ])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/csv,text/plain,*/*',
                'Accept-Encoding' => 'gzip, deflate',
            ])
            ->get($csvUrl);

        if ($response->failed() || empty(trim((string)$response->body()))) {
            Log::warning("GoogleSheetsSyncService: Sheet [{$sheetName}] empty or not accessible.");
            return ['processed' => 0, 'inserted' => 0];
        }

        $csvBody = (string)$response->body();
        $metrics = $this->parseCsvContent($csvBody, $sheetName, $sheetSeller);

        // Tarik dan sinkronkan pewarnaan sel/baris dari spreadsheet HANYA jika diminta (withColors = true)
        if ($withColors) {
            try {
                $colorRes = $this->pullColorsFromSheet($spreadsheetId, $sheetName, $sheetSeller);
                $metrics['colors_updated'] = $colorRes['updated_count'] ?? 0;
            } catch (\Throwable $e) {
                Log::warning("Auto pull colors failed during syncSingleSheet: " . $e->getMessage());
            }
        } else {
            $metrics['colors_updated'] = 0;
        }

        return $metrics;
    }

    /**
     * Discover sheet tab names from Google Sheet HTML page or fallback list
     */
    public function discoverSheetNames(string $spreadsheetId, string $defaultSeller = 'Aliqa'): array
    {
        $discovered = [];
        $targetIds = [$spreadsheetId];

        foreach (array_unique($targetIds) as $sId) {
            try {
                $htmlUrl = "https://docs.google.com/spreadsheets/d/{$sId}/htmlview";
                $response = Http::timeout(15)->get($htmlUrl);

                if ($response->successful()) {
                    $html = (string)$response->body();

                    // Match sheet name JSON patterns in Google Sheets HTML (supports {name: "..."} and {"name": "..."})
                    if (preg_match_all('/(?:"?name"?|"?sheetName"?)\s*:\s*"([^"]+)"/i', $html, $matches)) {
                        foreach ($matches[1] as $name) {
                            $cleanName = trim(strip_tags($name));
                            if (!empty($cleanName) && !in_array($cleanName, $discovered)) {
                                $discovered[] = $cleanName;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning("GoogleSheetsSyncService: HTML sheet discovery failed for {$sId}: " . $e->getMessage());
            }

            if (!empty($discovered)) {
                return array_values(array_unique($discovered));
            }
        }

        // Standard monthly sheet names fallback list (only if HTML discovery returned nothing)
        if (str_contains(strtoupper($defaultSeller), 'ALIQA')) {
            return [
                'JANUARI 2026 (FP ALIQA)', 'FEBRUARI 2026 (FP ALIQA)', 'MARET 2026 (FP ALIQA)', 'APRIL 2026 (FP ALIQA)',
                'MEI 2026 (FP ALIQA)', 'JUNI 2026 (FP ALIQA).', 'JULI 2026 (FP ALIQA)', 'AGUSTUS 2026 (FP ALIQA)',
                'SEPTEMBER 2026 (FP ALIQA)', 'OKTOBER 2026 (FP ALIQA)', 'NOVEMBER 2026 (FP ALIQA)', 'DESEMBER 2026 (FP ALIQA)'
            ];
        }

        return [
            'JANUARI (ZAHERBA)', 'FEBRUARI (ZAHERBA)', 'MARET (ZAHERBA)', 'APRIL (ZAHERBA)',
            'MEI (ZAHERBA)', 'JUNI (ZAHERBA)', 'JULI (ZAHERBA)', 'AGUSTUS (ZAHERBA)',
            'SEPTEMBER (ZAHERBA)', 'OKTOBER (ZAHERBA)', 'NOVEMBER (ZAHERBA)', 'DESEMBER (ZAHERBA)'
        ];
    }

    /**
     * Parse raw CSV content line-by-line using ShipmentsImport rules
     */
    protected function parseCsvContent(string $csvContent, string $sheetName, string $defaultSeller): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);

        $batchBuffer = [];
        $resisInBuffer = [];
        $now = now()->toDateTimeString();
        $headerMap = [];
        $headerFound = false;

        $lineNum = 0;
        $processedCount = 0;
        $insertedCount = 0;
        $skippedRowsLog = [];

        while (($rowArray = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            $lineNum++;
            if (empty(array_filter($rowArray))) {
                continue;
            }

            // Check if current row is a header row
            if (!$headerFound) {
                $possibleMap = $this->callProtectedMethod($this->importer, 'buildHeaderMap', [$rowArray]);
                if (!empty($possibleMap)) {
                    $headerMap = $possibleMap;
                    $headerFound = true;
                    Log::info("GoogleSheetsSyncService: Header detected at Line {$lineNum} on sheet [{$sheetName}]: " . json_encode($rowArray));
                    continue;
                }
            }

            $processedCount++;
            $parsed = $this->callProtectedMethod($this->importer, 'parseRowArray', [$rowArray, $headerMap, $now, $sheetName]);
            if ($parsed !== null) {
                if (str_contains(strtoupper($defaultSeller), 'ALIQA') || str_contains(strtoupper($sheetName), 'ALIQA')) {
                    $parsed['nama_seller'] = 'Mitra Aliqa';
                } elseif (str_contains(strtoupper($defaultSeller), 'ZAHERBA') || str_contains(strtoupper($sheetName), 'ZAHERBA')) {
                    $parsed['nama_seller'] = 'Mitra Zaherba';
                } elseif (!empty($defaultSeller)) {
                    $parsed['nama_seller'] = $defaultSeller;
                }
                $batchBuffer[] = $parsed;
                $resisInBuffer[] = $parsed['no_resi'];
            } else {
                $rowSnippet = implode(' | ', array_slice(array_filter($rowArray), 0, 4));
                $skippedRowsLog[] = [
                    'line' => $lineNum,
                    'preview' => $rowSnippet,
                ];
                Log::warning("GoogleSheetsSyncService [SKIPPED ROW]: Sheet [{$sheetName}] Line {$lineNum} skipped (No valid resi found): \"{$rowSnippet}\"");
            }

            // Upsert in 1,000-row chunks
            if (count($batchBuffer) >= 1000) {
                $inserted = $this->callProtectedMethod($this->importer, 'processBufferBatch', [$batchBuffer, $resisInBuffer]);
                $insertedCount += $inserted;
                $batchBuffer = [];
                $resisInBuffer = [];
            }
        }

        if (!empty($batchBuffer)) {
            $inserted = $this->callProtectedMethod($this->importer, 'processBufferBatch', [$batchBuffer, $resisInBuffer]);
            $insertedCount += $inserted;
        }

        fclose($stream);

        Log::info("GoogleSheetsSyncService Summary for [{$sheetName}]: Total Rows={$processedCount}, Valid Unique Resis Inserted/Updated={$insertedCount}, Skipped Rows=" . count($skippedRowsLog));

        return [
            'processed' => $processedCount,
            'inserted' => $insertedCount,
            'skipped_count' => count($skippedRowsLog),
            'skipped_rows' => $skippedRowsLog,
        ];
    }

    /**
     * Auto-Update Back to Google Sheets (Reverse Sync):
     * Sends updated NIPOS tracking status & keterangan back to Google Spreadsheet via Apps Script Webhook
     *
     * @param array $trackingItems Array of tracked resis with status_pos, keterangan, color_code, sla_days
     * @return array Result metrics
     */
    public function reverseSyncNiposTracking(array $trackingItems, ?string $customWebhookUrl = null): array
    {
        if (empty($trackingItems)) {
            return ['success' => false, 'message' => 'No tracking items provided'];
        }

        // ATURAN KERAS: RUN BOT NIPOS JANGAN SAMPAI MERUBAH SPREADSHEET!
        // Update NIPOS hanya dilakukan di Web Tracko saja. Spreadsheet cuma berubah saat push FU POS.
        Log::info("GoogleSheetsSyncService: Ignored reverse sync to spreadsheet for " . count($trackingItems) . " resis. Bot NIPOS updates are strictly kept on Web Tracko.");
        return [
            'success' => true,
            'message' => 'Bot NIPOS tracking updates are strictly kept on Web Tracko. Spreadsheet untouched.',
            'webhook_sent' => false,
            'webhook_success' => true,
            'items_count' => count($trackingItems),
            'updated_count' => count($trackingItems),
            'spreadsheet_id' => '',
        ];

        $sellerName = $trackingItems[0]['seller'] ?? '';
        $isZaherba = str_contains(strtoupper((string)$sellerName), 'ZAHERBA');
        $sellerKey = $isZaherba ? 'zaherba' : 'aliqa';

        $savedUrl = SystemSetting::get("google_sheet_url_{$sellerKey}") ?: (SystemSetting::get('google_sheet_url') ?: SystemSetting::get('google_sheet_id'));
        $defaultId = $isZaherba ? env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw') : env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg');
        $spreadsheetId = SystemSetting::extractSpreadsheetId($savedUrl) ?: $defaultId;

        $webhookUrl = $customWebhookUrl !== null ? $customWebhookUrl : (
            SystemSetting::get("google_sheet_webhook_url_{$sellerKey}") ?: (SystemSetting::get('google_sheet_webhook_url') ?: env('GOOGLE_SHEET_WEBHOOK_URL'))
        );

        $logMsg = "GoogleSheetsSyncService ({$sellerKey}): Reverse Syncing " . count($trackingItems) . " NIPos tracking updates back to Google Sheet [{$spreadsheetId}]";
        Log::info($logMsg);

        $webhookSuccess = false;
        $responseBody = null;
        $statusCode = null;

        if (!empty($webhookUrl)) {
            try {
                $targetSheet = $trackingItems[0]['sheet_name'] ?? ($trackingItems[0]['sheet'] ?? '');
                $payload = [
                    'action' => 'reverse_sync_nipos',
                    'spreadsheet_id' => $spreadsheetId,
                    'sheet' => $targetSheet,
                    'sheet_name' => $targetSheet,
                    'total_items' => count($trackingItems),
                    'items' => array_values($trackingItems),
                    'resis' => array_values(array_filter(array_column($trackingItems, 'resi'))),
                    'updated_at' => now()->toDateTimeString(),
                ];

                if (app()->environment('testing')) {
                    $webhookSuccess = true;
                    $statusCode = 200;
                    $responseBody = ['status' => 'success', 'message' => 'Testing mock reverse sync'];
                } else {
                    @set_time_limit(0);
                    $ch = curl_init($webhookUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                    $body = curl_exec($ch);
                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $responseBody = json_decode($body, true);
                    $webhookSuccess = ($statusCode === 200 && isset($responseBody['status']) && $responseBody['status'] === 'success');
                }

                Log::info("GoogleSheetsSyncService Reverse Sync Webhook: " . ($webhookSuccess ? 'SUCCESS' : "HTTP {$statusCode}"), [
                    'items_count' => count($trackingItems),
                    'response' => $responseBody ?: substr((string)($body ?? ''), 0, 300),
                ]);
            } catch (Throwable $e) {
                Log::warning("GoogleSheetsSyncService Reverse Sync exception: " . $e->getMessage());
            }
        } else {
            Log::info("GoogleSheetsSyncService: Reverse Sync skipped - No Google Sheet Webhook URL configured.");
        }

        return [
            'success' => true,
            'webhook_sent' => !empty($webhookUrl),
            'webhook_success' => $webhookSuccess,
            'status_code' => $statusCode,
            'spreadsheet_id' => $spreadsheetId,
            'items_count' => count($trackingItems),
            'timestamp' => now()->toDateTimeString(),
            'response' => $responseBody,
        ];
    }

    /**
     * Update status/color for specific resis in Google Spreadsheet (Two-Way Sync)
     *
     * @param array $resiList List of resis to update
     * @param string $statusColor Target color code (BIRU, ORANGE, KUNING, HIJAU, BIRU_TUA, PUTIH)
     * @param string|null $note Optional follow-up note
     * @param string|null $escalationDate Optional escalation date
     * @return array Result metrics
     */
    public function updateResiStatus(array $resiList, string $statusColor, ?string $note = null, ?string $escalationDate = null, ?string $fuTimestamp = null): array
    {
        $firstShipment = !empty($resiList) ? \App\Models\OutgoingShipment::where('no_resi', $resiList[0])->first() : null;
        $isZaherba = $firstShipment && str_contains(strtoupper((string)($firstShipment->nama_seller ?? '')), 'ZAHERBA');
        $sellerKey = $isZaherba ? 'zaherba' : 'aliqa';

        $savedUrl = SystemSetting::get("google_sheet_url_{$sellerKey}") ?: (SystemSetting::get('google_sheet_url') ?: SystemSetting::get('google_sheet_id'));
        $defaultId = $isZaherba ? env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw') : env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg');
        $spreadsheetId = SystemSetting::extractSpreadsheetId($savedUrl) ?: $defaultId;

        $webhookUrl = SystemSetting::get("google_sheet_webhook_url_{$sellerKey}") ?: (
            SystemSetting::get('google_sheet_webhook_url') ?: env('GOOGLE_SHEET_WEBHOOK_URL')
        );

        $statusLabelMap = [
            'BIRU'     => 'PAKET SUKSES (DELIVERED)',
            'ORANGE'   => 'PAKET RETUR (RETURN)',
            'KUNING'   => 'SUDAH DI FU (1x)',
            'HIJAU'    => 'FU 2 KALI',
            'BIRU_TUA' => 'FU POS (ESKALASI KC/KCU)',
            'PUTIH'    => 'BELUM DI FOLLOW UP',
        ];
        $statusLabel = $statusLabelMap[$statusColor] ?? $statusColor;

        // Di Spreadsheet, warna RETUR (ORANGE) dan DELIVERED (BIRU) adalah wewenang mutlak Seller!
        // Admin dari Web Tracking HANYA diizinkan merubah status FU POS (BIRU_TUA).
        if ($statusColor === 'BIRU' || $statusColor === 'ORANGE') {
            Log::info("GoogleSheetsSyncService: Ignored updating color {$statusColor} to Spreadsheet for " . count($resiList) . " resis (Color RETUR & DELIVERED are managed exclusively by Seller).");
            return [
                'success' => true,
                'message' => "Warna {$statusColor} di Spreadsheet adalah wewenang Seller dan tidak diubah oleh Admin.",
                'resi_count' => count($resiList),
                'spreadsheet_id' => $spreadsheetId,
            ];
        }

        $logMsg = "GoogleSheetsSyncService Two-Way Sync ({$sellerKey}): Updating " . count($resiList) . " resis → {$statusColor} ({$statusLabel}) on Spreadsheet [{$spreadsheetId}]";
        Log::info($logMsg);

        $webhookSuccess = false;

        // Mode A: Apps Script Webhook / Custom Webhook POST endpoint
        if (!empty($webhookUrl)) {
            try {
                $targetSheet = '';
                if (!empty($resiList)) {
                    $firstShipment = \App\Models\OutgoingShipment::where('no_resi', $resiList[0])->first();
                    if ($firstShipment && $firstShipment->tanggal_kirim) {
                        $mNum = (int)date('n', strtotime($firstShipment->tanggal_kirim));
                        $isAliqa = str_contains(strtoupper($firstShipment->nama_seller ?? ''), 'ALIQA');
                        $monthSheetMapAliqa = [
                            1 => 'JANUARI 2026 (FP ALIQA)', 2 => 'FEBRUARI 2026 (FP ALIQA)', 3 => 'MARET 2026 (FP ALIQA)',
                            4 => 'APRIL 2026 (FP ALIQA)', 5 => 'MEI 2026 (FP ALIQA)', 6 => 'JUNI 2026 (FP ALIQA).',
                            7 => 'JULI 2026 (FP ALIQA)', 8 => 'AGUSTUS 2026 (FP ALIQA)', 9 => 'SEPTEMBER 2026 (FP ALIQA)',
                            10 => 'OKTOBER 2026 (FP ALIQA)', 11 => 'NOVEMBER 2026 (FP ALIQA)', 12 => 'DESEMBER 2026 (FP ALIQA)',
                        ];
                        $monthSheetMapZaherba = [
                            1 => 'JANUARI (ZAHERBA)', 2 => 'FEBRUARI (ZAHERBA)', 3 => 'MARET (ZAHERBA)',
                            4 => 'APRIL (ZAHERBA)', 5 => 'MEI (ZAHERBA)', 6 => 'JUNI (ZAHERBA)',
                            7 => 'JULI (ZAHERBA)', 8 => 'AGUSTUS (ZAHERBA)', 9 => 'SEPTEMBER (ZAHERBA)',
                            10 => 'OKTOBER (ZAHERBA)', 11 => 'NOVEMBER (ZAHERBA)', 12 => 'DESEMBER (ZAHERBA)',
                        ];
                        $targetSheet = !$isZaherba ? ($monthSheetMapAliqa[$mNum] ?? '') : ($monthSheetMapZaherba[$mNum] ?? '');
                    }
                }

                $payload = [
                    'action'          => 'update_fu_status',   // action khusus FU untuk Apps Script
                    'spreadsheet_id'  => $spreadsheetId,
                    'sheet'           => $targetSheet,
                    'sheet_name'      => $targetSheet,
                    'resis'           => array_values($resiList),
                    'resi_list'       => array_values($resiList),
                    'status_color'    => $statusColor,
                    'color_code'      => $statusColor,
                    'fu_type'         => $statusColor,          // alias untuk Apps Script
                    'status_label'    => $statusLabel,
                    'note'            => $note ?: '',
                    'escalation_date' => $escalationDate ?: '',
                    'fu_timestamp'    => $fuTimestamp ?: now()->toDateTimeString(),
                    'updated_at'      => now()->toDateTimeString(),
                ];

                @set_time_limit(0);
                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($ch, CURLOPT_TIMEOUT, 90);
                $body = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $jsonRes = json_decode($body, true);
                $webhookSuccess = ($statusCode === 200 && isset($jsonRes['status']) && $jsonRes['status'] === 'success');

                Log::info("GoogleSheetsSyncService Webhook POST status: " . ($webhookSuccess ? 'SUCCESS' : "HTTP {$statusCode}"), [
                    'response' => $jsonRes ?: substr((string)$body, 0, 300),
                ]);
            } catch (Throwable $e) {
                Log::warning("GoogleSheetsSyncService Webhook POST exception: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'webhook_sent' => !empty($webhookUrl),
            'webhook_success' => $webhookSuccess,
            'spreadsheet_id' => $spreadsheetId,
            'resi_count' => count($resiList),
            'resis' => $resiList,
            'color_code' => $statusColor,
            'status_label' => $statusLabel,
            'note' => $note,
            'escalation_date' => $escalationDate,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Synchronize active dashboard filter to Google Spreadsheet via Webhook (Two-Way Filter Sync)
     */
    public function syncFilter(?string $seller = 'ALL', ?string $month = 'ALL', ?string $color = null, ?string $search = null): array
    {
        $savedUrl = SystemSetting::get('google_sheet_url') ?: SystemSetting::get('google_sheet_id');
        $spreadsheetId = SystemSetting::extractSpreadsheetId($savedUrl) ?: '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw';
        $webhookUrl = SystemSetting::get('google_sheet_webhook_url') ?: env('GOOGLE_SHEET_WEBHOOK_URL');

        if (empty($webhookUrl)) {
            return ['success' => false, 'message' => 'Google Sheet Webhook URL not configured'];
        }

        $cleanSeller = ($seller && $seller !== 'Semua Seller') ? $seller : 'ALL';
        $cleanMonth = $month ?: 'ALL';
        $cleanColor = $color ?: 'ALL';

        $payload = [
            'action' => 'apply_filter',
            'spreadsheet_id' => $spreadsheetId,
            'seller' => $cleanSeller,
            'month' => $cleanMonth,
            'color_code' => $cleanColor,
            'status_color' => $cleanColor,
            'search' => $search ?: '',
            'updated_at' => now()->toDateTimeString(),
        ];

        try {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            $body = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $jsonRes = json_decode($body, true);
            $success = ($statusCode === 200 && isset($jsonRes['status']) && $jsonRes['status'] === 'success');

            Log::info("GoogleSheetsSyncService Webhook Filter Sync: " . ($success ? 'SUCCESS' : "HTTP {$statusCode}"), [
                'payload' => $payload,
                'response' => $jsonRes ?: substr((string)$body, 0, 200),
            ]);

            return [
                'success' => $success,
                'status_code' => $statusCode,
                'response' => $jsonRes,
            ];
        } catch (Throwable $e) {
            Log::warning("GoogleSheetsSyncService Webhook Filter Sync exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pull cell background colors directly from Google Sheets via Webhook and synchronize to OutgoingShipment.
     */
    public function pullColorsFromSheet(string $spreadsheetId, ?string $sheetName = null, string $seller = 'Mitra Aliqa'): array
    {
        @set_time_limit(180);

        $isZaherba = str_contains(strtoupper($seller), 'ZAHERBA') || str_contains(strtoupper((string)$sheetName), 'ZAHERBA');
        $sellerKey = $isZaherba ? 'zaherba' : 'aliqa';

        $webhookUrl = SystemSetting::get("google_sheet_webhook_url_{$sellerKey}")
            ?: (SystemSetting::get('google_sheet_webhook_url') ?: env('GOOGLE_SHEET_WEBHOOK_URL'));

        if (empty($webhookUrl)) {
            return [
                'success' => false,
                'message' => 'URL Google Sheet Webhook belum dikonfigurasi.',
                'updated_count' => 0,
            ];
        }

        $payload = [
            'action' => 'pull_sheet_colors',
            'spreadsheet_id' => $spreadsheetId,
            'sheet' => $sheetName ?: 'ALL',
            'sheet_name' => $sheetName ?: 'ALL',
        ];

        try {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $body = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($body, true);
            if ($statusCode !== 200 || !isset($data['status']) || $data['status'] !== 'success') {
                Log::warning("pullColorsFromSheet failed: HTTP {$statusCode}, Body: " . substr((string)$body, 0, 300));
                return [
                    'success' => false,
                    'message' => 'Webhook Apps Script tidak merespons sukses.',
                    'response' => $data,
                    'updated_count' => 0,
                ];
            }

            $colors = $data['colors'] ?? [];
            if (empty($colors)) {
                return [
                    'success' => true,
                    'message' => 'Tidak ada warna khusus (selain putih) yang ditemukan di sheet.',
                    'updated_count' => 0,
                ];
            }

            $updatedCount = 0;
            $grouped = [];
            foreach ($colors as $item) {
                if (!empty($item['resi']) && !empty($item['color'])) {
                    $r = trim((string)$item['resi']);
                    $rUpper = strtoupper($r);
                    if (strlen($r) < 8 || in_array($rUpper, ['BELUM DI PROSES', 'RESI', 'NO RESI', 'BARCODE', 'AWB'])) {
                        continue;
                    }
                    $grouped[strtoupper($item['color'])][] = $r;
                }
            }

            foreach ($grouped as $color => $resis) {
                // Sesuai aturan: Data final (SUKSES & RETUR) TIDAK DIAMBIL dari Spreadsheet
                // karena data tracking bot NIPOS dari Pos Indonesia lebih valid.
                // Spreadsheet HANYA digunakan untuk data follow up: BIRU_TUA (FU POS), KUNING (SUDAH FU), PUTIH (BELUM FU).
                if ($color === 'BIRU' || $color === 'ORANGE') {
                    Log::info("pullColorsFromSheet: Lewati warna {$color} dari spreadsheet karena data final dikelola oleh Bot Tracking NIPOS.");
                    continue;
                }

                foreach (array_chunk($resis, 500) as $chunk) {
                    // Ambil nomor resi yang sudah FINAL di sistem kita agar TIDAK PERNAH ditimpa
                    $finalResis = \App\Models\OutgoingShipment::whereIn('no_resi', $chunk)
                        ->where(function($q) {
                            // 1. Final Sukses
                            $q->where(function($sub) {
                                $sub->where('status_kategori', 'SUKSES')
                                    ->orWhere('color_code', 'BIRU')
                                    ->orWhere(function($s) {
                                        $s->where('status_pos', 'DELIVERED')
                                          ->where('status_pos', 'NOT LIKE', '%RETURN%');
                                    });
                            })
                            // 2. Atau Final Retur
                            ->orWhere(function($sub) {
                                $sub->where('status_kategori', 'RETUR')
                                    ->orWhere('color_code', 'ORANGE')
                                    ->orWhere('status_pos', 'LIKE', '%RETURN%')
                                    ->orWhere('status_pos', 'LIKE', '%RETUR%')
                                    ->orWhere('status_pos', 'LIKE', '%DITOLAK%')
                                    ->orWhere('keterangan', 'LIKE', '%RETURN%')
                                    ->orWhere('keterangan', 'LIKE', '%RETUR%')
                                    ->orWhere('keterangan', 'LIKE', '%DITOLAK%');
                            });
                        })
                        ->pluck('no_resi')->toArray();

                    // HANYA resi non-final yang boleh diperbarui warna/status FU-nya dari Spreadsheet
                    $nonFinalChunk = array_values(array_diff($chunk, $finalResis));
                    if (empty($nonFinalChunk)) {
                        continue;
                    }

                    if ($color === 'BIRU_TUA') {
                        $affFuPos = \App\Models\OutgoingShipment::whereIn('no_resi', $nonFinalChunk)
                            ->update([
                                'color_code' => 'BIRU_TUA',
                                'fu_pos_date' => \Illuminate\Support\Facades\DB::raw('COALESCE(fu_pos_date, NOW())'),
                                'status_kategori' => 'FOLLOW_UP',
                            ]);
                        $updatedCount += $affFuPos;
                    } elseif ($color === 'KUNING') {
                        $affected = \App\Models\OutgoingShipment::whereIn('no_resi', $nonFinalChunk)
                            ->update([
                                'color_code' => 'KUNING',
                                'status_kategori' => 'FOLLOW_UP',
                            ]);
                        $updatedCount += $affected;
                    } elseif ($color === 'HIJAU') {
                        $updateData = ['status_kategori' => 'FOLLOW_UP'];
                        if ($sellerKey === 'aliqa') {
                            $updateData['color_code'] = 'PUTIH';
                            $updateData['status_kategori'] = 'IN_PROCESS';
                        } else {
                            $updateData['color_code'] = 'HIJAU';
                        }
                        $affected = \App\Models\OutgoingShipment::whereIn('no_resi', $nonFinalChunk)
                            ->update($updateData);
                        $updatedCount += $affected;
                    } elseif ($color === 'PUTIH') {
                        $affected = \App\Models\OutgoingShipment::whereIn('no_resi', $nonFinalChunk)
                            ->update([
                                'color_code' => 'PUTIH',
                                'status_kategori' => 'IN_PROCESS',
                            ]);
                        $updatedCount += $affected;
                    }
                }
            }

            \Illuminate\Support\Facades\Cache::flush();

            Log::info("pullColorsFromSheet ({$sellerKey}): Synchronized {$updatedCount} resi colors from Sheet [{$sheetName}].");

            return [
                'success' => true,
                'total_found' => count($colors),
                'updated_count' => $updatedCount,
                'message' => "Berhasil menyinkronkan {$updatedCount} warna resi dari Google Sheets!",
            ];
        } catch (\Throwable $e) {
            Log::error("pullColorsFromSheet error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'updated_count' => 0,
            ];
        }
    }

    /**
     * Helper to invoke protected methods on ShipmentsImport
     */
    protected function callProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
