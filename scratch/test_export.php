<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Exports\SellerAliqaExport;
use Maatwebsite\Excel\Facades\Excel;

echo "Testing export...\n";
$start = microtime(true);

$exportFile = storage_path('app/public/test_export.xlsx');
Excel::store(new SellerAliqaExport('Aliqa'), 'public/test_export.xlsx');

$elapsed = round(microtime(true) - $start, 2);
echo "EXPORT COMPLETED in {$elapsed} seconds!\n";
echo "File size: " . filesize($exportFile) . " bytes\n";
