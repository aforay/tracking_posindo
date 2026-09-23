<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Query with LIKE '%Aliqa%'
$t1 = microtime(true);
$res1 = DB::select("SELECT * FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND (nama_seller = 'Mitra Aliqa' OR nama_seller = 'Aliqa' OR nama_seller LIKE '%Aliqa%') ORDER BY id ASC LIMIT 50");
$time1 = (microtime(true) - $t1) * 1000;
echo "Time WITH LIKE '%Aliqa%': " . round($time1, 2) . " ms\n";

// Query with IN ('Mitra Aliqa', 'Aliqa')
$t2 = microtime(true);
$res2 = DB::select("SELECT * FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller IN ('Mitra Aliqa', 'Aliqa') ORDER BY id ASC LIMIT 50");
$time2 = (microtime(true) - $t2) * 1000;
echo "Time WITH IN ('Mitra Aliqa', 'Aliqa'): " . round($time2, 2) . " ms\n";

// EXPLAIN of the IN query
$exp = DB::select("EXPLAIN SELECT * FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller IN ('Mitra Aliqa', 'Aliqa') ORDER BY id ASC LIMIT 50");
print_r($exp);
