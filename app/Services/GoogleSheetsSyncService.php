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
     * @return array Sync metrics summary
     */
    public function sync(?string $spreadsheetIdOrUrl = null, string $defaultSeller = 'Aliqa'): array
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        // 1. Resolve Spreadsheet ID
        $spreadsheetId = SystemSetting::extractSpreadsheetId($spreadsheetIdOrUrl);
        if (empty($spreadsheetId)) {
            $savedUrl = SystemSetting::get('google_sheet_url') ?: SystemSetting::get('google_sheet_id');
            $spreadsheetId = SystemSetting::extractSpreadsheetId($savedUrl);
        }

        // Fallback default sample Google Sheet ID if not configured
        if (empty($spreadsheetId)) {
            $spreadsheetId = '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw';
        }

        $startMsg = "GoogleSheetsSyncService: Starting sync for Spreadsheet ID [{$spreadsheetId}]";
        dump($startMsg);
        echo $startMsg . "\n";
        Log::info($startMsg);

        // 2. Discover Sheet Names (Januari - Agustus)
        $sheetNames = $this->discoverSheetNames($spreadsheetId);
        dump("Discovered " . count($sheetNames) . " sheets to sync: ", $sheetNames);
        echo "Discovered " . count($sheetNames) . " sheets to sync: " . json_encode($sheetNames) . "\n";

        $totalProcessed = 0;
        $totalInserted = 0;
        $processedSheets = [];

        // 3. Iterate and stream data from each Sheet
        foreach ($sheetNames as $sheetName) {
            $msg = "Membaca Google Sheet: {$sheetName}";
            dump($msg);
            echo $msg . "\n";
            Log::info("GoogleSheetsSyncService: Fetching CSV for sheet -> {$sheetName}");

            try {
                $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
                
                $response = Http::timeout(30)
                    ->retry(2, 500)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'text/csv,text/plain,*/*',
                    ])
                    ->get($csvUrl);

                if ($response->failed() || empty(trim((string)$response->body()))) {
                    Log::warning("GoogleSheetsSyncService: Sheet [{$sheetName}] empty or not accessible via CSV export.");
                    continue;
                }

                $csvBody = (string)$response->body();
                
                // Parse CSV stream line by line
                $metrics = $this->parseCsvContent($csvBody, $sheetName, $defaultSeller);
                
                if ($metrics['processed'] > 0) {
                    $totalProcessed += $metrics['processed'];
                    $totalInserted += $metrics['inserted'];
                    $processedSheets[] = $sheetName;

                    $sheetDoneMsg = "Sheet [{$sheetName}] finished: {$metrics['processed']} rows processed, {$metrics['inserted']} rows inserted/updated.";
                    dump($sheetDoneMsg);
                    echo $sheetDoneMsg . "\n";
                }
            } catch (Throwable $e) {
                $errMsg = "Error syncing sheet [{$sheetName}]: " . $e->getMessage();
                dump("ERROR IMPORT: " . $errMsg);
                echo "ERROR IMPORT: " . $errMsg . "\n";
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

        dump("GoogleSheetsSyncService COMPLETED: ", $doneSummary);
        Log::info("GoogleSheetsSyncService finished: " . json_encode($doneSummary));

        return $doneSummary;
    }

    /**
     * Discover sheet tab names from Google Sheet HTML page or fallback list
     */
    protected function discoverSheetNames(string $spreadsheetId): array
    {
        $discovered = [];

        try {
            $htmlUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/htmlview";
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
            Log::warning("GoogleSheetsSyncService: HTML sheet discovery failed: " . $e->getMessage());
        }

        // Standard monthly sheet names fallback list (including suffixes)
        $defaultMonthSheets = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER',
            'JANUARI (ZAHERBA)', 'FEBRUARI (ZAHERBA)', 'MARET (ZAHERBA)', 'APRIL (ZAHERBA)', 'MEI (ZAHERBA)', 'JUNI (ZAHERBA)', 'JULI (ZAHERBA)', 'AGUSTUS (ZAHERBA)', 'SEPTEMBER (ZAHERBA)', 'OKTOBER (ZAHERBA)', 'NOVEMBER (ZAHERBA)', 'DESEMBER (ZAHERBA)',
            'Sheet1', 'Sheet 1', 'Master Data'
        ];

        foreach ($defaultMonthSheets as $fallbackSheet) {
            if (!in_array($fallbackSheet, $discovered)) {
                $discovered[] = $fallbackSheet;
            }
        }

        return array_values(array_unique($discovered));
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
                    dump("Header terdeteksi pada Sheet [{$sheetName}]: ", $rowArray);
                    continue;
                }
            }

            $processedCount++;
            $parsed = $this->callProtectedMethod($this->importer, 'parseRowArray', [$rowArray, $headerMap, $now, $sheetName]);
            if ($parsed !== null) {
                if (!empty($defaultSeller) && $defaultSeller !== 'Aliqa') {
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
            'PUTIH' => 'BLM DI FU (IN TRANSIT)',
        ];
        $statusLabel = $statusLabelMap[$statusColor] ?? $statusColor;

        $logMsg = "GoogleSheetsSyncService Two-Way Sync: Updating " . count($resiList) . " resis → {$statusColor} ({$statusLabel}) on Spreadsheet [{$spreadsheetId}]";
        dump($logMsg);
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
