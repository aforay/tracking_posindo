<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$po = \App\Models\PostOffice::find(3);
echo "PostOffice 3: " . json_encode($po) . "\n";

$sppMedan = \App\Models\PostOffice::where('name', 'like', '%MEDAN%')->orWhere('code', '20900')->get();
echo "Medan PostOffices: " . json_encode($sppMedan) . "\n";
