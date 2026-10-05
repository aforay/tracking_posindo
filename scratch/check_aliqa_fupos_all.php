<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OutgoingShipment;

$allSeptAliqa = OutgoingShipment::whereMonth('tanggal_kirim', 9)
    ->where('nama_seller', 'like', '%Aliqa%');

echo "Total Aliqa Sept: " . (clone $allSeptAliqa)->count() . "\n";
echo "Total with fu_pos_date not null: " . (clone $allSeptAliqa)->whereNotNull('fu_pos_date')->count() . "\n";
echo "Breakdown of fu_pos_date not null by color_code:\n";
$byColor = (clone $allSeptAliqa)->whereNotNull('fu_pos_date')
    ->groupBy('color_code')->selectRaw('color_code, count(*) as cnt')->pluck('cnt', 'color_code');
echo json_encode($byColor, JSON_PRETTY_PRINT) . "\n";

echo "Breakdown of fu_pos_date not null by status_kategori:\n";
$byKat = (clone $allSeptAliqa)->whereNotNull('fu_pos_date')
    ->groupBy('status_kategori')->selectRaw('status_kategori, count(*) as cnt')->pluck('cnt', 'status_kategori');
echo json_encode($byKat, JSON_PRETTY_PRINT) . "\n";

echo "Breakdown of fu_pos_date not null by status_pos:\n";
$byStatus = (clone $allSeptAliqa)->whereNotNull('fu_pos_date')
    ->groupBy('status_pos')->selectRaw('status_pos, count(*) as cnt')->pluck('cnt', 'status_pos');
echo json_encode($byStatus, JSON_PRETTY_PRINT) . "\n";
