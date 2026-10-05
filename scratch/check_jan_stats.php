<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$janTotal = \App\Models\OutgoingShipment::where('nama_seller', 'Mitra Zaherba')->whereBetween('tanggal_kirim', ['2026-01-01', '2026-01-31'])->count();
$janWithCs = \App\Models\OutgoingShipment::where('nama_seller', 'Mitra Zaherba')->whereBetween('tanggal_kirim', ['2026-01-01', '2026-01-31'])->whereNotNull('nama_cs')->count();
$janNullCs = \App\Models\OutgoingShipment::where('nama_seller', 'Mitra Zaherba')->whereBetween('tanggal_kirim', ['2026-01-01', '2026-01-31'])->whereNull('nama_cs')->count();

echo "Jan Total: {$janTotal}\n";
echo "Jan With CS: {$janWithCs}\n";
echo "Jan Null CS: {$janNullCs}\n";

$sampleNull = \App\Models\OutgoingShipment::where('nama_seller', 'Mitra Zaherba')->whereBetween('tanggal_kirim', ['2026-01-01', '2026-01-31'])->whereNull('nama_cs')->limit(5)->pluck('no_resi')->toArray();
echo "Sample Jan Null Resis: " . json_encode($sampleNull) . "\n";
