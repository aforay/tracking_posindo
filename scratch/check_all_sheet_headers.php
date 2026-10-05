<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$aliqaId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';
$zaherbaId = '1AdRkyrZ6_GIKPdk0sn2Bf4g19BhgTUHcm2niznvvv5c';

$sync = new \App\Services\GoogleSheetsSyncService();

function checkAllHeaders($spreadsheetId, $seller) {
    global $sync;
    $sheetNames = $sync->discoverSheetNames($spreadsheetId, $seller);
    echo "=== ALL SHEETS FOR {$seller} ===\n";
    foreach ($sheetNames as $sheetName) {
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
        $resp = \Illuminate\Support\Facades\Http::timeout(30)->get($csvUrl);
        if (!$resp->successful()) continue;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $resp->body());
        rewind($stream);
        $row1 = fgetcsv($stream);
        $row2 = fgetcsv($stream);
        echo "Sheet: {$sheetName}\n";
        echo "  Header: " . json_encode(array_slice($row1 ?: [], 0, 18)) . "\n";
        echo "  Row 2:  " . json_encode(array_slice($row2 ?: [], 0, 18)) . "\n";
        fclose($stream);
    }
}

checkAllHeaders($aliqaId, 'Aliqa');
checkAllHeaders($zaherbaId, 'Zaherba');
