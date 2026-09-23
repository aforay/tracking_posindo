<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = "EXPLAIN SELECT * FROM outgoing_shipments 
WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' 
AND (nama_seller = 'Mitra Aliqa' OR nama_seller = 'Aliqa' OR nama_seller = 'Mitra Aliqa' OR nama_seller LIKE '%Aliqa%') 
ORDER BY id ASC LIMIT 50";

$res = DB::select($sql);
print_r($res);
