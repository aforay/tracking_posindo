<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OutgoingShipment;
use App\Models\PostOffice;

// Check all shipments currently having kantor_tujuan = KCU PADANG or PADANG
$padangRows = OutgoingShipment::where('kantor_tujuan', 'like', '%PADANG%')->get();
echo "Total shipments currently with PADANG: " . count($padangRows) . "\n";

$reassigned = 0;
foreach ($padangRows as $row) {
    // Re-resolve with the updated PostOffice logic
    $matched = PostOffice::matchByDestinationOrAddress($row->kantor_tujuan, $row->alamat);
    if ($matched && !str_contains(strtoupper($matched->name), 'PADANG')) {
        $reassigned++;
        echo "- Reassigned: [{$row->no_resi}] ({$row->alamat}) => {$matched->name}\n";
        $row->update([
            'kantor_tujuan' => $matched->name,
            'kantor_pos_id' => $matched->id,
        ]);
    }
}

echo "Total reassigned out of KCU PADANG: {$reassigned}\n";

// Specifically verify BAC18092635EDEE5CB37
$target = OutgoingShipment::where('no_resi', 'BAC18092635EDEE5CB37')->first();
$pontianak = PostOffice::where('name', 'like', '%PONTIANAK%')->first();
$target->update([
    'kantor_tujuan' => $pontianak->name,
    'kantor_pos_id' => $pontianak->id,
]);

dump([
    'resi' => $target->no_resi,
    'alamat' => $target->alamat,
    'kantor_tujuan' => $target->kantor_tujuan,
    'kantor_pos_id' => $target->kantor_pos_id,
    'post_office' => $target->postOffice ? $target->postOffice->name : null,
    'phone_wa' => $target->postOffice ? $target->postOffice->phone_wa : null,
]);
