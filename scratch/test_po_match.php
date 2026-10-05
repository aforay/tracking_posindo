<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$addr = 'Perumahan gusung baru blok C no. 09 desa gusung kec. Deleng pokhisen kab. Aceh Tenggara - Kutacane';
$matched = \App\Models\PostOffice::matchByDestinationOrAddress(null, $addr);
echo "Match by address only:\n" . json_encode($matched, JSON_PRETTY_PRINT) . "\n";

$derived = \App\Http\Controllers\DashboardController::deriveKantorPosFromAddress($addr);
echo "Derived from address:\n" . json_encode($derived, JSON_PRETTY_PRINT) . "\n";
