<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$zaherbaId = '1AdRkyrZ6_GIKPdk0sn2Bf4g19BhgTUHcm2niznvvv5c';
$aliqaId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';

$sync = new \App\Services\GoogleSheetsSyncService();
$importer = new \App\Imports\ShipmentsImport('Aliqa');

echo "Zaherba sheets: " . json_encode($sync->discoverSheetNames($zaherbaId, 'Zaherba')) . "\n";
echo "Aliqa sheets: " . json_encode($sync->discoverSheetNames($aliqaId, 'Aliqa')) . "\n";

function inspectCsv($spreadsheetId, $sheetName) {
    global $importer;
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
    $resp = \Illuminate\Support\Facades\Http::timeout(30)->get($csvUrl);
    if (!$resp->successful()) {
        echo "Failed to fetch {$sheetName}: " . $resp->status() . "\n";
        return;
    }
    $csv = $resp->body();
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $csv);
    rewind($stream);
    
    echo "=== SHEET: {$sheetName} (ID: {$spreadsheetId}) ===\n";
    $count = 0;
    while (($row = fgetcsv($stream)) !== false) {
        $count++;
        if ($count <= 5) {
            echo "Row {$count}: " . json_encode($row) . "\n";
            $ref = new ReflectionClass($importer);
            $m = $ref->getMethod('buildHeaderMap');
            $m->setAccessible(true);
            $map = $m->invoke($importer, $row);
            if (!empty($map)) {
                echo "  -> Detected Header Map at Row {$count}: " . json_encode($map) . "\n";
            }
        }
    }
    fclose($stream);
}

inspectCsv($aliqaId, 'OKTOBER');
inspectCsv($aliqaId, 'SEPTEMBER');
inspectCsv($zaherbaId, 'OKTOBER');
inspectCsv($zaherbaId, 'SEPTEMBER');
