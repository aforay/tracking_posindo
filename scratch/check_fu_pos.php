<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OutgoingShipment;

$septAliqa = OutgoingShipment::whereMonth('tanggal_kirim', 9)->where('nama_seller', 'like', '%Aliqa%');
echo "Aliqa Sept total: " . (clone $septAliqa)->count() . "\n";
echo "Aliqa by color: " . json_encode((clone $septAliqa)->groupBy('color_code')->selectRaw('color_code, count(*) as cnt')->pluck('cnt', 'color_code')->toArray(), JSON_PRETTY_PRINT) . "\n";

$septZaherba = OutgoingShipment::whereMonth('tanggal_kirim', 9)->where('nama_seller', 'like', '%Zaherba%');
echo "Zaherba Sept total: " . (clone $septZaherba)->count() . "\n";
echo "Zaherba by color: " . json_encode((clone $septZaherba)->groupBy('color_code')->selectRaw('color_code, count(*) as cnt')->pluck('cnt', 'color_code')->toArray(), JSON_PRETTY_PRINT) . "\n";

$septAll = OutgoingShipment::whereMonth('tanggal_kirim', 9);
echo "All Sept total: " . (clone $septAll)->count() . "\n";
echo "All Sept by color: " . json_encode((clone $septAll)->groupBy('color_code')->selectRaw('color_code, count(*) as cnt')->pluck('cnt', 'color_code')->toArray(), JSON_PRETTY_PRINT) . "\n";
