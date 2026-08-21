<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Imports\ShipmentsImport;
use App\Models\OutgoingShipment;
use Maatwebsite\Excel\Facades\Excel;

echo "Starting import test on POS INPROSES ZAHERBA 2026.xlsx...\n";
$start = microtime(true);

$largeFile = base_path('POS INPROSES ZAHERBA 2026.xlsx');
if (!file_exists($largeFile)) {
    echo "File not found: $largeFile\n";
    exit(1);
}

Excel::import(new ShipmentsImport('Aliqa'), $largeFile);

$elapsed = round(microtime(true) - $start, 2);
$totalCount = OutgoingShipment::count();

echo "IMPORT COMPLETED!\n";
echo "Elapsed Time: {$elapsed} seconds\n";
echo "Total Rows in Database: {$totalCount}\n";
echo "Breakdown by Kategori:\n";
print_r(OutgoingShipment::selectRaw('status_kategori, count(*) as total')->groupBy('status_kategori')->pluck('total', 'status_kategori')->toArray());
