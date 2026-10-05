<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PostOffice;
use App\Models\OutgoingShipment;

$po = PostOffice::firstOrCreate(
    ['name' => 'SPP JAKARTA TIMUR 13400'],
    [
        'code' => '13400',
        'city' => 'Jakarta Timur',
        'province' => 'DKI Jakarta',
        'phone_wa' => '6281293608434',
        'pic_name' => 'CS Antaran KC Jaktim',
        'notes' => 'SPP Jakarta Timur / KC Jaktim'
    ]
);

echo "PostOffice ID: " . $po->id . " Name: " . $po->name . " Phone: " . $po->phone_wa . "\n";

// Update outgoing shipment BAC230926325CC947923
$shipment = OutgoingShipment::where('no_resi', 'BAC230926325CC947923')->first();
if ($shipment) {
    $shipment->kantor_tujuan = 'SPP JAKARTA TIMUR 13400';
    $shipment->last_location = 'SPP JAKARTA TIMUR 13400';
    $shipment->kantor_pos_id = $po->id;
    $shipment->status_pos = 'RETUR BARANG (INLOCATION)';
    $shipment->keterangan = 'Retur Barang - (KIRIMAN DITOLAK YANG BERSANGKUTAN)';
    $shipment->status_kategori = 'RETUR';
    $shipment->color_code = 'ORANGE';
    $shipment->save();
    echo "Updated Shipment ID " . $shipment->id . " with kantor_tujuan = " . $shipment->kantor_tujuan . "\n";
}
