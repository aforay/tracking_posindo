<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$code = file_get_contents('app/Models/PostOffice.php');

// Let's modify PostOffice in memory to test:
// 1. Move $aliases to step 3 (before city search)
// 2. Add PADANG TIKAR => PONTIANAK
$modified = $code;
$modified = str_replace('class PostOffice extends Model', 'class PostOfficePadangTest extends \App\Models\PostOffice', $modified);
$modified = preg_replace('/namespace\s+App\\\Models;/', '', $modified);

// Let's see what happens if $aliases has PADANG TIKAR
// and is checked before generic city loop
eval('?>' . $modified);

$cases = [
    ['KCP PADANG TIKAR 78385', 'Desa Batu ampar dusun sungai limau Kubu Raya'],
    ['KCP RASAU JAYA 78382', 'Rasau Jaya Kubu Raya'],
    ['KCU PADANG 25000', 'Jl Bagindo Aziz Chan Padang'],
    ['KCP PADANG TUALANG 20853', 'Padang Tualang Langkat'],
    ['KCP PADANG RATU 34176', 'Padang Ratu Lampung Tengah'],
];

foreach ($cases as [$target, $addr]) {
    $matched = PostOfficePadangTest::matchByDestinationOrAddress($target, $addr);
    echo "$target | $addr => " . ($matched ? $matched->name : 'NULL') . "\n";
}
