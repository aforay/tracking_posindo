<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$nullsBySeller = \App\Models\OutgoingShipment::whereNull('nama_cs')->orWhere('nama_cs', '')
    ->groupBy('nama_seller')
    ->selectRaw('nama_seller, count(*) as cnt')
    ->get();
echo "Nulls by seller:\n" . json_encode($nullsBySeller, JSON_PRETTY_PRINT) . "\n";

$nullsByMonth = \App\Models\OutgoingShipment::where(function($q) {
        $q->whereNull('nama_cs')->orWhere('nama_cs', '');
    })
    ->where('nama_seller', 'Mitra Zaherba')
    ->selectRaw('DATE_FORMAT(tanggal_kirim, "%Y-%m") as ym, count(*) as cnt')
    ->groupBy('ym')
    ->orderBy('ym')
    ->get();
echo "Zaherba nulls by ym:\n" . json_encode($nullsByMonth, JSON_PRETTY_PRINT) . "\n";
