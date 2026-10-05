<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$aliqaId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';
$zaherbaId = '1AdRkyrZ6_GIKPdk0sn2Bf4g19BhgTUHcm2niznvvv5c';

$sync = new \App\Services\GoogleSheetsSyncService();
$importer = new \App\Imports\ShipmentsImport('Aliqa');

$ref = new ReflectionClass($importer);
$mHeader = $ref->getMethod('buildHeaderMap');
$mHeader->setAccessible(true);
$mParse = $ref->getMethod('parseRowArray');
$mParse->setAccessible(true);

function processSheetForCs($spreadsheetId, $sheetName, $seller) {
    global $importer, $mHeader, $mParse;
    
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
    $resp = \Illuminate\Support\Facades\Http::timeout(60)->get($csvUrl);
    if (!$resp->successful()) {
        echo "Failed to fetch {$sheetName}\n";
        return 0;
    }
    
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
        if ($parsed && !empty($parsed['no_resi']) && !empty($parsed['nama_cs']) && !is_numeric($parsed['nama_cs'])) {
            $csMap[$parsed['no_resi']] = $parsed['nama_cs'];
        }
    }
    fclose($stream);
    
    if (empty($csMap)) {
        echo "  [{$sheetName}] No CS found\n";
        return 0;
    }
    
    // Bulk update in chunks
    $totalUpdated = 0;
    foreach (array_chunk($csMap, 1000, true) as $chunk) {
        $cases = [];
        $params = [];
        $resis = [];
        
        foreach ($chunk as $resi => $cs) {
            $resis[] = $resi;
            $cases[] = "WHEN ? THEN ?";
            $params[] = $resi;
            $params[] = $cs;
        }
        
        $placeholders = implode(',', array_fill(0, count($resis), '?'));
        $sql = "UPDATE outgoing_shipments SET nama_cs = CASE no_resi " . implode(' ', $cases) . " END WHERE no_resi IN ({$placeholders}) AND (nama_cs IS NULL OR nama_cs = '' OR nama_cs = '1' OR nama_cs REGEXP '^[0-9]+$')";
        
        $mergedParams = array_merge($params, $resis);
        $affected = \Illuminate\Support\Facades\DB::update($sql, $mergedParams);
        $totalUpdated += $affected;
    }
    
    echo "  [{$sheetName}] Extracted " . count($csMap) . " resis with CS. Updated {$totalUpdated} records in DB.\n";
    return $totalUpdated;
}

echo "=== UPDATING CS FOR ALIQA ===\n";
$aliqaSheets = $sync->discoverSheetNames($aliqaId, 'Aliqa');
$totalAliqa = 0;
foreach ($aliqaSheets as $sheet) {
    $totalAliqa += processSheetForCs($aliqaId, $sheet, 'Aliqa');
}
echo "Total Aliqa records updated: {$totalAliqa}\n\n";

echo "=== UPDATING CS FOR ZAHERBA ===\n";
$zaherbaSheets = $sync->discoverSheetNames($zaherbaId, 'Zaherba');
$totalZaherba = 0;
foreach ($zaherbaSheets as $sheet) {
    $totalZaherba += processSheetForCs($zaherbaId, $sheet, 'Zaherba');
}
echo "Total Zaherba records updated: {$totalZaherba}\n\n";
