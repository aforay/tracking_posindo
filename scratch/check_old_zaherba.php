<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$oldZaherbaId = '1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw';
$sync = new \App\Services\GoogleSheetsSyncService();

echo "Old Zaherba sheets: " . json_encode($sync->discoverSheetNames($oldZaherbaId, 'Zaherba')) . "\n";
