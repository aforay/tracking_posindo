<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$oldZaherbaId = '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw';
$csvUrl = "https://docs.google.com/spreadsheets/d/{$oldZaherbaId}/gviz/tq?tqx=out:csv&sheet=" . urlencode('JANUARI (ZAHERBA)');
$resp = \Illuminate\Support\Facades\Http::timeout(30)->get($csvUrl);
$stream = fopen('php://temp', 'r+');
fwrite($stream, $resp->body());
rewind($stream);

for ($i = 1; $i <= 4; $i++) {
    $row = fgetcsv($stream);
    echo "Row {$i}: " . json_encode(array_slice($row ?: [], 0, 15)) . "\n";
}
fclose($stream);
