<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$resi = 'BAC021026416B8347020';
$fastTracker = new \App\Services\NiposFastTracker();

$res = $fastTracker->trackChunk([$resi]);
echo "FastTracker output: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";

if (!empty($res[$resi]['kantor_tujuan'])) {
    $tujuan = $res[$resi]['kantor_tujuan'];
    $s = \App\Models\OutgoingShipment::where('no_resi', $resi)->first();
    $matched = \App\Models\PostOffice::matchByDestinationOrAddress($tujuan, $s->alamat);
    
    $s->kantor_tujuan = $tujuan;
    $s->last_location = $tujuan;
    if ($matched) {
        $s->kantor_pos_id = $matched->id;
    }
    $s->save();
    
    echo "Updated shipment in DB:\n";
    echo "kantor_tujuan: " . $s->kantor_tujuan . "\n";
    echo "last_location: " . $s->last_location . "\n";
    echo "kantor_pos_id: " . $s->kantor_pos_id . " (" . ($matched ? $matched->name : 'none') . ")\n";
}
