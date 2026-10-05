<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GoogleSheetsSyncService;
use App\Models\SystemSetting;

$syncService = app(GoogleSheetsSyncService::class);
$spreadsheetIdAliqa = SystemSetting::get('google_sheet_id_aliqa');
$webhookUrl = SystemSetting::get('google_sheet_webhook_url_aliqa');

echo "Real Aliqa Spreadsheet ID: $spreadsheetIdAliqa\n";
echo "Real Webhook URL: $webhookUrl\n";

// 1. Discover sheet names
$sheetNames = $syncService->discoverSheetNames($spreadsheetIdAliqa, 'Aliqa');
echo "Discovered sheets:\n" . json_encode($sheetNames, JSON_PRETTY_PRINT) . "\n";

// 2. Test pulling colors for September specifically
$septSheetName = null;
foreach ($sheetNames as $name) {
    if (str_contains(strtoupper($name), 'SEP') || str_contains(strtoupper($name), 'SEPTEMBER')) {
        $septSheetName = $name;
        break;
    }
}

echo "\nTarget September sheet name: " . var_export($septSheetName, true) . "\n";

if ($septSheetName && $webhookUrl) {
    $payload = [
        'action' => 'pull_sheet_colors',
        'spreadsheet_id' => $spreadsheetIdAliqa,
        'sheet' => $septSheetName,
        'sheet_name' => $septSheetName,
    ];
    echo "Sending payload to webhook: " . json_encode($payload) . "\n";

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POSTREDIR, 3); // CURL_REDIR_POST_ALL
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $body = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: $statusCode\n";
    echo "Raw response preview: " . mb_substr($body, 0, 500) . "\n";

    $data = json_decode($body, true);
    if (isset($data['colors'])) {
        echo "Total colors returned by webhook: " . count($data['colors']) . "\n";
        $colorCounts = [];
        $fuPosList = [];
        foreach ($data['colors'] as $c) {
            $col = strtoupper($c['color'] ?? 'UNKNOWN');
            $colorCounts[$col] = ($colorCounts[$col] ?? 0) + 1;
            if ($col === 'BIRU_TUA' || str_contains($col, 'TUA') || str_contains($col, 'POS')) {
                $fuPosList[] = $c;
            }
        }
        echo "Colors breakdown from sheet:\n" . json_encode($colorCounts, JSON_PRETTY_PRINT) . "\n";
        echo "Total FU POS (Biru Tua) in sheet: " . count($fuPosList) . "\n";
        if (count($fuPosList) > 0) {
            echo "First 10 FU POS resis in sheet:\n";
            for ($i = 0; $i < min(10, count($fuPosList)); $i++) {
                echo "  Resi: " . ($fuPosList[$i]['resi'] ?? '') . " | Color: " . ($fuPosList[$i]['color'] ?? '') . "\n";
            }
        }
    }
}
