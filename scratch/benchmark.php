<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$t1 = microtime(true);
$res1 = DB::select("EXPLAIN SELECT * FROM outgoing_shipments WHERE MONTH(tanggal_kirim) = 8 AND YEAR(tanggal_kirim) = 2026 AND (nama_seller = 'Mitra Aliqa' OR nama_seller = 'Aliqa') ORDER BY id ASC LIMIT 50 OFFSET 50");
$time1 = (microtime(true) - $t1) * 1000;

echo "--- EXPLAIN WITH MONTH() ---\n";
print_r($res1);
echo "Time: {$time1} ms\n\n";

$t2 = microtime(true);
$res2 = DB::select("EXPLAIN SELECT * FROM outgoing_shipments WHERE (tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31') AND (nama_seller = 'Mitra Aliqa' OR nama_seller = 'Aliqa') ORDER BY id ASC LIMIT 50 OFFSET 50");
$time2 = (microtime(true) - $t2) * 1000;

echo "--- EXPLAIN WITH BETWEEN ---\n";
print_r($res2);
echo "Time: {$time2} ms\n\n";

