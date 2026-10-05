<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$resi = 'BAC18092635EDEE5CB37';
$shipment = App\Models\OutgoingShipment::where('no_resi', $resi)->first();
dump([
    'resi' => $shipment->no_resi,
    'alamat' => $shipment->alamat,
    'kantor_tujuan' => $shipment->kantor_tujuan,
    'status_pos' => $shipment->status_pos,
    'keterangan' => $shipment->keterangan,
]);

// Test with PostOffice
$matched = App\Models\PostOffice::matchByDestinationOrAddress('KCP PADANG TIKAR 78385', $shipment->alamat);
echo "Current match for 'KCP PADANG TIKAR 78385': " . ($matched ? $matched->name : 'NULL') . "\n";
