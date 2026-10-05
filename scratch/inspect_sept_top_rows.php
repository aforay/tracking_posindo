<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$spreadsheetId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';
$sheetName = 'SEPTEMBER 2026 (FP ALIQA)';
$csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);

$resp = Illuminate\Support\Facades\Http::timeout(60)->get($csvUrl);
$csvBody = $resp->body();

$stream = fopen('php://temp', 'r+');
fwrite($stream, $csvBody);
rewind($stream);

for ($r = 1; $r <= 25; $r++) {
    $row = fgetcsv($stream, 0, ',', '"', '\\');
    if ($row === false) break;
    $filtered = [];
    foreach ($row as $k => $v) {
        if (!empty(trim((string)$v))) {
            $filtered[$k] = $v;
        }
    }
    echo "Row $r: " . json_encode($filtered) . "\n";
}
