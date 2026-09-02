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
     * @param int|string|null $targetMonth Target specific month number (1-12) or ALL
     * @return array Sync metrics summary
     */
    public function sync(?string $spreadsheetIdOrUrl = null, string $defaultSeller = 'Aliqa', ?string $targetSheet = null, $targetMonth = null): array
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
        } elseif (!empty($targetMonth) && (string)$targetMonth !== 'ALL' && (string)$targetMonth !== '0') {
            $mNum = (int)$targetMonth;
            if ($mNum >= 1 && $mNum <= 12) {
                $monthKeywords = [
                    1 => ['JAN', 'JANUARI'], 2 => ['FEB', 'FEBRUARI'], 3 => ['MAR', 'MARET'],
                    4 => ['APR', 'APRIL'], 5 => ['MEI', 'MAY'], 6 => ['JUN', 'JUNI'],
                    7 => ['JUL', 'JULI'], 8 => ['AGT', 'AGUS', 'AGUSTUS', 'AUG'], 9 => ['SEP', 'SEPTEMBER'],
                    10 => ['OKT', 'OKTOBER', 'OCT'], 11 => ['NOV', 'NOVEMBER'], 12 => ['DES', 'DESEMBER', 'DEC'],
                ];
                $keywords = $monthKeywords[$mNum] ?? [];
                $filtered = array_filter($sheetNames, function ($sName) use ($keywords) {
                    $sUpper = strtoupper($sName);
                    foreach ($keywords as $kw) {
                        if (str_contains($sUpper, $kw)) return true;
                    }
                    return false;
                });
                if (!empty($filtered)) {
                    $sheetNames = array_values($filtered);
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
                    $response = Http::timeout(20)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            'Accept' => 'text/csv,text/plain,*/*',
                        ])
                        ->get($csvUrl);
                } catch (Throwable $e) {
                    Log::warning("GoogleSheetsSyncService: Primary fetch failed for ID [{$spreadsheetId}]: " . $e->getMessage());
                }

                if ((!$response || $response->failed() || empty(trim((string)$response->body()))) && ($spreadsheetId === '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg' || $isAliqaSeller)) {
                    $fallbackId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';
                    Log::warning("GoogleSheetsSyncService: Primary Sheet ID [{$spreadsheetId}] inaccessible. Trying fallback ID [{$fallbackId}] for sheet [{$sheetName}]...");
                    $csvUrl = "https://docs.google.com/spreadsheets/d/{$fallbackId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
                    $response = Http::timeout(20)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            'Accept' => 'text/csv,text/plain,*/*',
                        ])
                        ->get($csvUrl);
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

        \Illuminate\Support\Facades\Cache::put('sync_progress', [
            'is_syncing' => false,
            'current_sheet' => 'Selesai',
            'current_sheet_index' => count($processedSheets),
            'total_sheets' => count($processedSheets),
            'processed_rows' => $totalProcessed,
            'inserted_rows' => $totalInserted,
            'percentage' => 100,
            'message' => "Sinkronisasi selesai! {$totalProcessed} baris data berhasil disinkronkan.",
            'updated_at' => now()->toDateTimeString(),
        ], 3600);

        Log::info("GoogleSheetsSyncService finished: " . json_encode($doneSummary));

        return $doneSummary;
    }

    /**
     * Sync a single Google Sheet tab
     */
    public function syncSingleSheet(string $spreadsheetId, string $sheetName, string $defaultSeller = 'Aliqa'): array
    {
        $sheetSeller = $defaultSeller;
        if (str_contains(strtoupper($sheetName), 'ZAHERBA')) {
            $sheetSeller = 'Mitra Zaherba';
        } elseif (str_contains(strtoupper($sheetName), 'ALIQA')) {
            $sheetSeller = 'Mitra Aliqa';
        }

        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);

        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/csv,text/plain,*/*',
            ])
            ->get($csvUrl);

        if ($response->failed() || empty(trim((string)$response->body()))) {
            Log::warning("GoogleSheetsSyncService: Sheet [{$sheetName}] empty or not accessible.");
            return ['processed' => 0, 'inserted' => 0];
        }

        $csvBody = (string)$response->body();
        return $this->parseCsvContent($csvBody, $sheetName, $sheetSeller);
    }

    /**
     * Discover sheet tab names from Google Sheet HTML page or fallback list
     */
    public function discoverSheetNames(string $spreadsheetId, string $defaultSeller = 'Aliqa'): array
    {
        $discovered = [];
        $targetIds = [$spreadsheetId];
        if ($spreadsheetId === '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg' || str_contains(strtoupper($defaultSeller), 'ALIQA')) {
            $targetIds[] = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';
        }

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

        $processedCount = 0;
        $insertedCount = 0;

        while (($rowArray = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            if (empty(array_filter($rowArray))) {
                continue;
            }

            // Check if current row is a header row
            if (!$headerFound) {
                $possibleMap = $this->callProtectedMethod($this->importer, 'buildHeaderMap', [$rowArray]);
                if (!empty($possibleMap)) {
                    $headerMap = $possibleMap;
                    $headerFound = true;
                    Log::info("GoogleSheetsSyncService: Header detected on sheet [{$sheetName}]: " . json_encode($rowArray));
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

        return [
            'processed' => $processedCount,
            'inserted' => $insertedCount,
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

        $savedUrl = SystemSetting::get('google_sheet_url') ?: SystemSetting::get('google_sheet_id');
        $spreadsheetId = SystemSetting::extractSpreadsheetId($savedUrl) ?: '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw';
        $webhookUrl = $customWebhookUrl !== null ? $customWebhookUrl : (SystemSetting::get('google_sheet_webhook_url') ?: env('GOOGLE_SHEET_WEBHOOK_URL'));

        $logMsg = "GoogleSheetsSyncService: Reverse Syncing " . count($trackingItems) . " NIPos tracking updates back to Google Sheet [{$spreadsheetId}]";
        Log::info($logMsg);

        $webhookSuccess = false;
        $responseBody = null;
        $statusCode = null;

        if (!empty($webhookUrl)) {
            try {
                $payload = [
                    'action' => 'reverse_sync_nipos',
                    'spreadsheet_id' => $spreadsheetId,
                    'total_items' => count($trackingItems),
                    'items' => array_values($trackingItems),
                    'resis' => array_values(array_filter(array_column($trackingItems, 'resi'))),
                    'updated_at' => now()->toDateTimeString(),
                ];

                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $body = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $responseBody = json_decode($body, true);
                $webhookSuccess = ($statusCode === 200 && isset($responseBody['status']) && $responseBody['status'] === 'success');

                Log::info("GoogleSheetsSyncService Reverse Sync Webhook: " . ($webhookSuccess ? 'SUCCESS' : "HTTP {$statusCode}"), [
                    'items_count' => count($trackingItems),
                    'response' => $responseBody ?: substr((string)$body, 0, 300),
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
    public function updateResiStatus(array $resiList, string $statusColor, ?string $note = null, ?string $escalationDate = null): array
    {
        $statusColor = strtoupper(trim($statusColor));
        $savedUrl = SystemSetting::get('google_sheet_url') ?: SystemSetting::get('google_sheet_id');
        $spreadsheetId = SystemSetting::extractSpreadsheetId($savedUrl) ?: '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw';
        $webhookUrl = SystemSetting::get('google_sheet_webhook_url') ?: env('GOOGLE_SHEET_WEBHOOK_URL');

        $statusLabelMap = [
            'BIRU' => 'PAKET SUKSES (DELIVERED)',
            'ORANGE' => 'PAKET RETUR (RETURN)',
            'KUNING' => 'SUDAH DI FU',
            'HIJAU' => 'FU 2 KALI',
            'BIRU_TUA' => 'FU POS',
            'PUTIH' => 'IN PROSES',
        ];
        $statusLabel = $statusLabelMap[$statusColor] ?? $statusColor;

        $logMsg = "GoogleSheetsSyncService Two-Way Sync: Updating " . count($resiList) . " resis → {$statusColor} ({$statusLabel}) on Spreadsheet [{$spreadsheetId}]";
        Log::info($logMsg);

        $webhookSuccess = false;

        // Mode A: Apps Script Webhook / Custom Webhook POST endpoint
        if (!empty($webhookUrl)) {
            try {
                $payload = [
                    'action' => 'update_status',
                    'spreadsheet_id' => $spreadsheetId,
                    'resis' => array_values($resiList),
                    'resi_list' => array_values($resiList),
                    'status_color' => $statusColor,
                    'color_code' => $statusColor,
                    'status_label' => $statusLabel,
                    'note' => $note ?: '',
                    'escalation_date' => $escalationDate ?: '',
                    'updated_at' => now()->toDateTimeString(),
                ];

                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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
