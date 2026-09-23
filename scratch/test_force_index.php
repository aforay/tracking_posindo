<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Test with FORCE INDEX (outgoing_shipments_tanggal_kirim_index)
$t1 = microtime(true);
$res1 = DB::select("SELECT * FROM outgoing_shipments FORCE INDEX (outgoing_shipments_tanggal_kirim_index) WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller IN ('Mitra Aliqa', 'Aliqa') ORDER BY id ASC LIMIT 50");
$time1 = (microtime(true) - $t1) * 1000;
echo "Time WITH FORCE INDEX (tanggal_kirim): " . round($time1, 2) . " ms\n";

// Test with FORCE INDEX (outgoing_shipments_nama_seller_tanggal_kirim_index)
$t2 = microtime(true);
$res2 = DB::select("SELECT * FROM outgoing_shipments FORCE INDEX (outgoing_shipments_nama_seller_tanggal_kirim_index) WHERE nama_seller IN ('Mitra Aliqa', 'Aliqa') AND tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' ORDER BY id ASC LIMIT 50");
$time2 = (microtime(true) - $t2) * 1000;
echo "Time WITH FORCE INDEX (nama_seller_tanggal_kirim): " . round($time2, 2) . " ms\n";

$exp = DB::select("EXPLAIN SELECT * FROM outgoing_shipments FORCE INDEX (outgoing_shipments_nama_seller_tanggal_kirim_index) WHERE nama_seller IN ('Mitra Aliqa', 'Aliqa') AND tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' ORDER BY id ASC LIMIT 50");
print_r($exp);
