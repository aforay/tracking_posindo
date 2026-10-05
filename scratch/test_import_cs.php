<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$aliqaId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';
$zaherbaId = '1AdRkyrZ6_GIKPdk0sn2Bf4g19BhgTUHcm2niznvvv5c';

$importer = new \App\Imports\ShipmentsImport('Aliqa');

function testParse($spreadsheetId, $sheetName, $seller) {
    global $importer;
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
    $resp = \Illuminate\Support\Facades\Http::timeout(30)->get($csvUrl);
    if (!$resp->successful()) return;
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $resp->body());
    rewind($stream);
    
    $ref = new ReflectionClass($importer);
    $mHeader = $ref->getMethod('buildHeaderMap');
    $mHeader->setAccessible(true);
    $mParse = $ref->getMethod('parseRowArray');
    $mParse->setAccessible(true);
    
    $headerMap = [];
    $count = 0;
    echo "=== TEST PARSE: {$sheetName} ({$seller}) ===\n";
    while (($row = fgetcsv($stream)) !== false) {
        $count++;
        if (empty($headerMap)) {
            $possibleMap = $mHeader->invoke($importer, $row);
            if (!empty($possibleMap)) {
                $headerMap = $possibleMap;
                echo "Header Map: " . json_encode($headerMap) . "\n";
                continue;
            }
        }
        $parsed = $mParse->invoke($importer, $row, $headerMap, now()->toDateTimeString(), $sheetName);
        if ($parsed && $count <= 6) {
            echo "Row {$count} Resi: {$parsed['no_resi']} | CS: '{$parsed['nama_cs']}' | Penerima: {$parsed['nama_penerima']}\n";
        }
    }
    fclose($stream);
}

testParse($aliqaId, 'SEPTEMBER 2026 (FP ALIQA)', 'Aliqa');
testParse($aliqaId, 'OKTOBER 2026 (FP ALIQA)', 'Aliqa');
testParse($zaherbaId, 'SEPTEMBER (ZAHERBA)', 'Zaherba');
testParse($zaherbaId, 'OKTOBER (ZAHERBA)', 'Zaherba');
