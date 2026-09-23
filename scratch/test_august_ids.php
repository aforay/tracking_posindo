<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$res = DB::select("SELECT MIN(id) as min_id, MAX(id) as max_id, COUNT(*) as cnt FROM outgoing_shipments WHERE tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31'");
print_r($res);
