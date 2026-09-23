<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$t1 = microtime(true);
$ids = DB::select("SELECT id FROM outgoing_shipments FORCE INDEX (idx_tgl_id) WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' AND nama_seller = 'Mitra Aliqa' ORDER BY id ASC LIMIT 50");
$idList = array_column($ids, 'id');
$rows = DB::select("SELECT * FROM outgoing_shipments WHERE id IN (" . implode(',', $idList) . ") ORDER BY id ASC");
$time1 = (microtime(true) - $t1) * 1000;

echo "Deferred join time: " . round($time1, 2) . " ms\n";
echo "Rows returned: " . count($rows) . "\n";
