<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OutgoingShipment;

$zaherbaTotal = OutgoingShipment::where('nama_seller', 'like', '%Zaherba%')->count();
$zaherbaNullKT = OutgoingShipment::where('nama_seller', 'like', '%Zaherba%')->whereNull('kantor_tujuan')->count();
$zaherbaEmptyKT = OutgoingShipment::where('nama_seller', 'like', '%Zaherba%')->where('kantor_tujuan', '')->count();
$zaherbaHasKT = OutgoingShipment::where('nama_seller', 'like', '%Zaherba%')->whereNotNull('kantor_tujuan')->where('kantor_tujuan', '!=', '')->count();

echo "Zaherba Total: $zaherbaTotal\n";
echo "Zaherba Null KT: $zaherbaNullKT\n";
echo "Zaherba Empty KT: $zaherbaEmptyKT\n";
echo "Zaherba Has KT: $zaherbaHasKT\n";

$sampleNull = OutgoingShipment::where('nama_seller', 'like', '%Zaherba%')
    ->where(function($q) { $q->whereNull('kantor_tujuan')->orWhere('kantor_tujuan', ''); })
    ->limit(5)
    ->get(['no_resi', 'tanggal_kirim', 'status_pos', 'kantor_tujuan', 'alamat']);

echo "Sample with Null KT:\n" . json_encode($sampleNull, JSON_PRETTY_PRINT) . "\n";
