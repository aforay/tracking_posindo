<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$kt = \App\Models\OutgoingShipment::where('kantor_tujuan', 'like', '%SOEKARNO%')->count();
$ll = \App\Models\OutgoingShipment::where('last_location', 'like', '%SOEKARNO%')->count();

echo "kantor_tujuan with SOEKARNO: {$kt}\n";
echo "last_location with SOEKARNO: {$ll}\n";

$targetShipment = \App\Models\OutgoingShipment::where('no_resi', 'BAC021026416B8347020')->first();
echo "\nTarget shipment (BAC021026416B8347020):\n";
echo "kantor_tujuan: " . $targetShipment->kantor_tujuan . "\n";
echo "last_location: " . $targetShipment->last_location . "\n";
echo "status_pos: " . $targetShipment->status_pos . "\n";
echo "alamat: " . $targetShipment->alamat . "\n";
