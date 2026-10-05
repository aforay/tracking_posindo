<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$oldZaherbaId = '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw';
$importer = new \App\Imports\ShipmentsImport('Zaherba');
$ref = new ReflectionClass($importer);
$mHeader = $ref->getMethod('buildHeaderMap');
$mHeader->setAccessible(true);
$mParse = $ref->getMethod('parseRowArray');
$mParse->setAccessible(true);

$csvUrl = "https://docs.google.com/spreadsheets/d/{$oldZaherbaId}/gviz/tq?tqx=out:csv&sheet=" . urlencode('JANUARI (ZAHERBA)');
$resp = \Illuminate\Support\Facades\Http::timeout(60)->get($csvUrl);
$stream = fopen('php://temp', 'r+');
fwrite($stream, $resp->body());
rewind($stream);
$headerMap = [];
$line = 0;
while (($row = fgetcsv($stream)) !== false) {
    $line++;
    if (empty($headerMap)) {
        $possibleMap = $mHeader->invoke($importer, $row);
        if (!empty($possibleMap)) {
            $headerMap = $possibleMap;
            continue;
        }
    }
    $parsed = $mParse->invoke($importer, $row, $headerMap, now()->toDateTimeString(), 'JANUARI (ZAHERBA)');
    if ($parsed && strlen($parsed['nama_cs'] ?? '') > 50) {
        echo "Line {$line} (len=" . strlen($parsed['nama_cs']) . "): " . $parsed['nama_cs'] . "\n";
    }
}
fclose($stream);
