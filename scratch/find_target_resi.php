<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$zaherbaId = env('GOOGLE_SHEET_ID_ZAHERBA', '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw');
$aliqaId = env('GOOGLE_SHEET_ID_ALIQA', '1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg');

$savedZaherbaUrl = \App\Models\SystemSetting::get('google_sheet_url_zaherba');
$savedAliqaUrl = \App\Models\SystemSetting::get('google_sheet_url_aliqa');
$savedUrl = \App\Models\SystemSetting::get('google_sheet_url');

echo "Saved Zaherba: {$savedZaherbaUrl}\n";
echo "Saved Aliqa: {$savedAliqaUrl}\n";
echo "Saved Default: {$savedUrl}\n";

$targetResi = 'BAC30092611B79A6CB0A';
$shipment = \App\Models\OutgoingShipment::where('no_resi', $targetResi)->first();
echo "Shipment details:\n" . json_encode($shipment, JSON_PRETTY_PRINT) . "\n";
