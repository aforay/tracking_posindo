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

$rowNum = 0;
$fuPosFound = [];
$catatanAdminFuPos = [];
while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
    $rowNum++;
    if ($rowNum === 1) continue;
    
    foreach ($row as $colIdx => $val) {
        $vUpper = strtoupper(trim((string)$val));
        if (str_contains($vUpper, 'FU POS') || str_contains($vUpper, 'FUPOS') || str_contains($vUpper, 'ESKALASI')) {
            $fuPosFound[] = [
                'row' => $rowNum,
                'col' => $colIdx,
                'resi' => $row[2] ?? '',
                'val' => $val,
            ];
        }
    }
}

echo "Total cells containing 'FU POS' or 'ESKALASI' across all 11,291 rows: " . count($fuPosFound) . "\n";
echo "Details:\n" . json_encode($fuPosFound, JSON_PRETTY_PRINT) . "\n";
