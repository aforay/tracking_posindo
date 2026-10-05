<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$webhookZaherba = \App\Models\SystemSetting::get('google_sheet_webhook_url_zaherba');
$sheetIdZaherba = \App\Models\SystemSetting::get('google_sheet_id_zaherba');

echo "Zaherba Webhook: $webhookZaherba\n";
echo "Zaherba Sheet ID: $sheetIdZaherba\n";

$payload = [
    'action' => 'pull_sheet_colors',
    'spreadsheet_id' => $sheetIdZaherba,
    'sheet' => 'SEPTEMBER (ZAHERBA)',
    'sheet_name' => 'SEPTEMBER (ZAHERBA)',
];

$ch = curl_init($webhookZaherba);
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
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: " . mb_substr($body, 0, 500) . "\n";
