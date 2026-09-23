<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$t1 = microtime(true);
$res1 = DB::select("SELECT * FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller IN ('Mitra Aliqa', 'Aliqa') ORDER BY tanggal_kirim ASC, id ASC LIMIT 50");
$time1 = (microtime(true) - $t1) * 1000;
echo "Time ORDER BY tanggal_kirim ASC, id ASC: " . round($time1, 2) . " ms\n";

$exp = DB::select("EXPLAIN SELECT * FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller IN ('Mitra Aliqa', 'Aliqa') ORDER BY tanggal_kirim ASC, id ASC LIMIT 50");
print_r($exp);
