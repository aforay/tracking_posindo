<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pendingSoekarno = \App\Models\OutgoingShipment::where(function($q) {
        $q->where('kantor_tujuan', 'like', '%SOEKARNO%')
          ->orWhere('last_location', 'like', '%SOEKARNO%');
    })
    ->where('color_code', '!=', 'BIRU')
    ->where('color_code', '!=', 'ORANGE')
    ->select('id', 'no_resi', 'kantor_tujuan', 'last_location', 'status_pos', 'alamat')
    ->get();

echo "Pending/in-transit with SOEKARNO: " . $pendingSoekarno->count() . "\n";
echo json_encode($pendingSoekarno, JSON_PRETTY_PRINT) . "\n";
