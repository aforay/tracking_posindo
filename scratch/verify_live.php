<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Models\PostOffice::clearCache();

$cases = [
    ['KCP PADANG TIKAR 78385', 'Desa Batu ampar dusun sungai limau Kubu Raya'],
    ['KCP RASAU JAYA 78382', 'Rasau Jaya Kubu Raya'],
    ['KCU PADANG 25000', 'Jl Bagindo Aziz Chan Padang'],
    ['KCP PADANG TUALANG 20853', 'Padang Tualang Langkat'],
    ['KCP PADANG RATU 34176', 'Padang Ratu Lampung Tengah'],
    ['KCP MUARAANCALONG 75556', 'Muara Bengkal'],
    ['KCP BINTUNI 98364', 'Babo Bintuni'],
];

foreach ($cases as [$target, $addr]) {
    $matched = App\Models\PostOffice::matchByDestinationOrAddress($target, $addr);
    echo "$target | $addr => " . ($matched ? $matched->name : 'NULL') . "\n";
}
