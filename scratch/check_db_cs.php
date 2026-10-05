<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$ones = \App\Models\OutgoingShipment::where('nama_cs', '1')->count();
$numeric = \App\Models\OutgoingShipment::whereRaw('nama_cs REGEXP "^[0-9]+$"')->count();
echo "Rows with nama_cs = '1': {$ones}\n";
echo "Rows with numeric nama_cs: {$numeric}\n";

$distinctCs = \App\Models\OutgoingShipment::select('nama_cs', \Illuminate\Support\Facades\DB::raw('count(*) as cnt'))
    ->groupBy('nama_cs')
    ->orderBy('cnt', 'desc')
    ->limit(20)
    ->get();
echo "Top 20 CS in DB:\n" . json_encode($distinctCs, JSON_PRETTY_PRINT) . "\n";
