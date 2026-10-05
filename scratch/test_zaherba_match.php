<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PostOffice;
use App\Http\Controllers\DashboardController;

$addrs = [
    'Jl. Berigjen slamet riadi VIII/ 181 RT 05/RW 03 Kelurahan : ORO ORO DOWO',
    'DESA PASTINA, RT 07/RW 04 Patokan Rumah dekat gedung pertemuan desa pastina',
    'Kenagarian Situjuh Banda dalam (disherlock customer)',
];

foreach ($addrs as $a) {
    echo "Addr: $a\n";
    $po = PostOffice::matchByDestinationOrAddress(null, $a);
    echo "  matchByDestinationOrAddress: " . ($po ? $po->name : 'NULL') . "\n";
    $derived = DashboardController::deriveKantorPosFromAddress($a);
    echo "  deriveKantorPosFromAddress: " . ($derived ?: 'EMPTY') . "\n";
}
