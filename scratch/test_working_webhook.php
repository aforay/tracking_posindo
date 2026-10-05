<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$webhookUrl = 'https://script.google.com/macros/s/AKfycbyp3WEIaPy3Nu9XSPivCYb4yha55rj-_3k-MFl0XJC-6GLazpppX4kJS2TEVSnpqM9k/exec';
$spreadsheetIdAliqa = \App\Models\SystemSetting::get('google_sheet_id_aliqa', '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac');
$sheetName = 'SEPTEMBER 2026 (FP ALIQA)';

$payload = [
    'action' => 'pull_sheet_colors',
    'spreadsheet_id' => $spreadsheetIdAliqa,
    'sheet' => $sheetName,
    'sheet_name' => $sheetName,
];
echo "Sending payload to: $webhookUrl\n";
echo "Payload: " . json_encode($payload) . "\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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
echo "Response preview (first 500 chars):\n" . mb_substr($body, 0, 500) . "\n";

$data = json_decode($body, true);
if (isset($data['colors'])) {
    echo "Total colors returned: " . count($data['colors']) . "\n";
    $colorCounts = [];
    $fuPosResis = [];
    foreach ($data['colors'] as $c) {
        $col = strtoupper($c['color'] ?? 'UNKNOWN');
        $colorCounts[$col] = ($colorCounts[$col] ?? 0) + 1;
        if ($col === 'BIRU_TUA') {
            $fuPosResis[] = $c['resi'] ?? '';
        }
    }
    echo "Color counts:\n" . json_encode($colorCounts, JSON_PRETTY_PRINT) . "\n";
    echo "FU POS count: " . count($fuPosResis) . "\n";
    echo "Sample FU POS resis:\n" . json_encode(array_slice($fuPosResis, 0, 20), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Raw response: " . $body . "\n";
}
