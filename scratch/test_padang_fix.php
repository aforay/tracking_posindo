<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test the alias check first
$code = file_get_contents('app/Models/PostOffice.php');

// Let's test moving step 4 ($aliases) before step 3 (city search)
// and adding PADANG TIKAR => PONTIANAK
$target = 'KCP PADANG TIKAR 78385';
$addr = "Desa Batu ampar dusun sungai limau,.RT/RW 001/001, Dusun sungai limau , Masuk gertak jembatan dolhadi";

echo "Testing target: '$target'\n";
$matched = App\Models\PostOffice::matchByDestinationOrAddress($target, $addr);
echo "Current match: " . ($matched ? $matched->name : 'NULL') . "\n";
