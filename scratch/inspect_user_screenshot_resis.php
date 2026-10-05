<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\OutgoingShipment;

$r1 = OutgoingShipment::where('no_resi', 'BAC010826569F50B8B06')->first();
$r2 = OutgoingShipment::where('no_resi', 'BAC01082601DD0B113E1')->first();

echo "Resi 1:\n";
print_r($r1 ? $r1->toArray() : 'NOT FOUND');

echo "\nResi 2:\n";
print_r($r2 ? $r2->toArray() : 'NOT FOUND');
