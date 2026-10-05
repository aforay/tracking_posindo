<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$resi = 'BAC021026416B8347020';
$s = \App\Models\OutgoingShipment::where('no_resi', $resi)->first();
echo "Shipment:\n" . json_encode($s, JSON_PRETTY_PRINT) . "\n";
