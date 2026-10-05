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

$sheets = ["JANUARI (ZAHERBA)", "FEBRUARI (ZAHERBA)", "MARET (ZAHERBA)", "APRIL (ZAHERBA)"];

$totalUpdated = 0;
foreach ($sheets as $sheetName) {
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$oldZaherbaId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
    $resp = \Illuminate\Support\Facades\Http::timeout(60)->get($csvUrl);
    if (!$resp->successful()) continue;
    
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $resp->body());
    rewind($stream);
    
    $headerMap = [];
    $csMap = [];
    $now = now()->toDateTimeString();
    
    while (($row = fgetcsv($stream)) !== false) {
        if (empty($headerMap)) {
            $possibleMap = $mHeader->invoke($importer, $row);
            if (!empty($possibleMap)) {
                $headerMap = $possibleMap;
                continue;
            }
        }
        $parsed = $mParse->invoke($importer, $row, $headerMap, $now, $sheetName);
        if ($parsed && !empty($parsed['no_resi']) && !empty($parsed['nama_cs']) && !is_numeric($parsed['nama_cs']) && strlen($parsed['nama_cs']) <= 40) {
            $csMap[$parsed['no_resi']] = $parsed['nama_cs'];
        }
    }
    fclose($stream);
    
    $sheetUpdated = 0;
    foreach (array_chunk($csMap, 500, true) as $chunk) {
        $cases = [];
        $params = [];
        $resis = [];
        
        foreach ($chunk as $resi => $cs) {
            $resis[] = $resi;
            $cases[] = "WHEN ? THEN ?";
            $params[] = $resi;
            $params[] = substr($cs, 0, 40);
        }
        
        $placeholders = implode(',', array_fill(0, count($resis), '?'));
        $sql = "UPDATE outgoing_shipments SET nama_cs = CASE no_resi " . implode(' ', $cases) . " END WHERE no_resi IN ({$placeholders}) AND (nama_cs IS NULL OR nama_cs = '' OR nama_cs = '1' OR nama_cs REGEXP '^[0-9]+$')";
        
        $mergedParams = array_merge($params, $resis);
        $affected = \Illuminate\Support\Facades\DB::update($sql, $mergedParams);
        $sheetUpdated += $affected;
    }
    echo "[{$sheetName}] Extracted " . count($csMap) . " with CS. Updated {$sheetUpdated} in DB.\n";
    $totalUpdated += $sheetUpdated;
}

echo "Total Old Zaherba Updated: {$totalUpdated}\n";
