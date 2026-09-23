<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\OutgoingShipment;
use Illuminate\Pagination\LengthAwarePaginator;

DB::enableQueryLog();

$t0 = microtime(true);

$query = OutgoingShipment::where('tanggal_kirim', '>=', '2026-08-01')
    ->where('tanggal_kirim', '<=', '2026-08-31')
    ->whereIn('nama_seller', ['Mitra Aliqa', 'Aliqa'])
    ->orderBy('tanggal_kirim', 'asc')
    ->orderBy('id', 'asc');

$items = $query->forPage(1, 50)->get();
$paginated = new LengthAwarePaginator($items, 9446, 50, 1);

$tEnd = microtime(true);
$queries = DB::getQueryLog();
$totalQueryTime = array_sum(array_column($queries, 'time'));

echo "Manual Paginator Execution Time: " . round(($tEnd - $t0) * 1000, 2) . " ms\n";
echo "Query Time: " . round($totalQueryTime, 2) . " ms\n";
echo "Number of queries: " . count($queries) . "\n";
