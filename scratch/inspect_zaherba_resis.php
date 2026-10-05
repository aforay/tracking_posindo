<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OutgoingShipment;

$resis = [
    'BAC01102611804F885B8',
    'BAC01102629DD274FD31',
    'BAC011026117E210C6ED',
    'BAC01102655854FB9CB0',
    'BAC01102658D027AEC79',
];

foreach ($resis as $r) {
    $s = OutgoingShipment::where('no_resi', $r)->first();
    echo "Resi: $r\n";
    if ($s) {
        echo "  kantor_tujuan: " . json_encode($s->kantor_tujuan) . "\n";
        echo "  last_location: " . json_encode($s->last_location) . "\n";
        echo "  kantor_pos_id: " . json_encode($s->kantor_pos_id) . "\n";
        echo "  alamat: " . json_encode($s->alamat) . "\n";
        echo "  status_pos: " . json_encode($s->status_pos) . "\n";
        echo "  keterangan: " . json_encode($s->keterangan) . "\n";
    } else {
        echo "  NOT FOUND\n";
    }
}
