<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Query with IGNORE INDEX (PRIMARY)
$t1 = microtime(true);
$res1 = DB::select("SELECT * FROM outgoing_shipments IGNORE INDEX (PRIMARY) WHERE nama_seller = 'Mitra Aliqa' AND tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' ORDER BY id ASC LIMIT 50");
$time1 = (microtime(true) - $t1) * 1000;
echo "Time WITH IGNORE INDEX (PRIMARY): " . round($time1, 2) . " ms\n";

$exp = DB::select("EXPLAIN SELECT * FROM outgoing_shipments IGNORE INDEX (PRIMARY) WHERE nama_seller = 'Mitra Aliqa' AND tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' ORDER BY id ASC LIMIT 50");
print_r($exp);
