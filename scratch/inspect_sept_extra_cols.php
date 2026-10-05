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

$header = fgetcsv($stream, 0, ',', '"', '\\');
echo "Header count: " . count($header) . "\n";
foreach ($header as $i => $h) {
    if (!empty($h)) echo "  Col $i: '$h'\n";
}

$sampleExtraCols = [];
$nonEmptyColsAfter17 = [];
$totalRows = 0;
while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
    $totalRows++;
    for ($c = 18; $c < count($row); $c++) {
        $val = trim((string)$row[$c]);
        if (!empty($val)) {
            $nonEmptyColsAfter17[$c] = ($nonEmptyColsAfter17[$c] ?? 0) + 1;
            if (count($sampleExtraCols[$c] ?? []) < 5) {
                $sampleExtraCols[$c][] = "Row $totalRows (Resi: " . ($row[2] ?? '') . "): " . $val;
            }
        }
    }
}

echo "\nTotal rows in CSV: $totalRows\n";
echo "Non-empty values in extra columns:\n";
foreach ($nonEmptyColsAfter17 as $col => $cnt) {
    echo "  Col $col: $cnt non-empty cells\n";
    foreach ($sampleExtraCols[$col] as $s) {
        echo "    $s\n";
    }
}
