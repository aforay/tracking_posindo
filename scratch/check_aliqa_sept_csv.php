<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\SystemSetting;

$spreadsheetId = SystemSetting::get('google_sheet_id_aliqa', '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac');
$sheetName = 'SEPTEMBER 2026 (FP ALIQA)';
$csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);

echo "Fetching CSV: $csvUrl\n";
$resp = Http::timeout(60)->get($csvUrl);
echo "HTTP Status: " . $resp->status() . "\n";
$csvBody = $resp->body();
echo "CSV length: " . strlen($csvBody) . " bytes\n";

$stream = fopen('php://temp', 'r+');
fwrite($stream, $csvBody);
rewind($stream);

$rows = [];
$line = 0;
while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false && $line < 25) {
    $line++;
    $rows[] = $row;
}
echo "Total lines read in sample: " . count($rows) . "\n";
echo "Header row (Line 1): " . json_encode($rows[0] ?? []) . "\n";
echo "Line 2: " . json_encode($rows[1] ?? []) . "\n";
echo "Line 3: " . json_encode($rows[2] ?? []) . "\n";
echo "Line 4: " . json_encode($rows[3] ?? []) . "\n";
