<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$total = \App\Models\OutgoingShipment::count();
$withCs = \App\Models\OutgoingShipment::whereNotNull('nama_cs')->where('nama_cs', '!=', '')->count();
$numeric = \App\Models\OutgoingShipment::whereRaw('nama_cs REGEXP "^[0-9]+$"')->count();

echo "Total Shipments: {$total}\n";
echo "Shipments with Valid CS: {$withCs} (" . round(($withCs / $total) * 100, 1) . "%)\n";
echo "Shipments with numeric/invalid CS: {$numeric}\n";

$bySeller = \App\Models\OutgoingShipment::select('nama_seller')
    ->selectRaw('count(*) as total')
    ->selectRaw('sum(case when nama_cs is not null and nama_cs != "" then 1 else 0 end) as with_cs')
    ->groupBy('nama_seller')
    ->get();
echo "\nBreakdown by Seller:\n" . json_encode($bySeller, JSON_PRETTY_PRINT) . "\n";
