<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$aliqaId = '1wKS0ZklbpTeLHN0APu2aIh7DBka3g4O15KNSJ0Wcdac';
$zaherbaId = '1AdRkyrZ6_GIKPdk0sn2Bf4g19BhgTUHcm2niznvvv5c';

function findResiInSheets($spreadsheetId, $sheetNames, $targetResi) {
    foreach ($sheetNames as $sheetName) {
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
        $resp = \Illuminate\Support\Facades\Http::timeout(30)->get($csvUrl);
        if (!$resp->successful()) continue;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $resp->body());
        rewind($stream);
        $line = 0;
        $header = null;
        while (($row = fgetcsv($stream)) !== false) {
            $line++;
            if ($line === 1) $header = $row;
            if (in_array($targetResi, $row)) {
                echo "FOUND in {$sheetName} Line {$line}:\n";
                echo "Header: " . json_encode($header) . "\n";
                echo "Row: " . json_encode($row) . "\n";
                fclose($stream);
                return;
            }
        }
        fclose($stream);
    }
}

findResiInSheets($aliqaId, ["SEPTEMBER 2026 (FP ALIQA)", "OKTOBER 2026 (FP ALIQA)"], 'BAC30092611B79A6CB0A');
findResiInSheets($zaherbaId, ["SEPTEMBER (ZAHERBA)", "OKTOBER (ZAHERBA)"], 'BAC30092611B79A6CB0A');
