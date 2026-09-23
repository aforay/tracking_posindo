<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

DB::enableQueryLog();

$t0 = microtime(true);

// 1. Month stats cached
$monthData = Cache::remember('test_month_stats_aliqa_2026', 60, function () {
    return DB::select("select MONTH(tanggal_kirim) as m, COUNT(*) as total from outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-01-01' AND '2026-12-31' GROUP BY MONTH(tanggal_kirim)");
});

// 2. Main query with optimized sort
$rows = DB::select("SELECT * FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller IN ('Mitra Aliqa', 'Aliqa') ORDER BY tanggal_kirim ASC, id ASC LIMIT 50");

// 3. Stats cached
$statsData = Cache::remember('test_stats_aliqa_august_2026', 15, function () {
    return DB::select("SELECT COUNT(*) as total FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller IN ('Mitra Aliqa', 'Aliqa')");
});

$tEnd = microtime(true);
$queries = DB::getQueryLog();
$totalQueryTime = array_sum(array_column($queries, 'time'));

echo "Total Execution Time: " . round(($tEnd - $t0) * 1000, 2) . " ms\n";
echo "Total Query Time: " . round($totalQueryTime, 2) . " ms\n";
