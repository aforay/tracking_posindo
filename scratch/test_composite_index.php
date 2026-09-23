<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Create index
try {
    DB::statement("CREATE INDEX idx_seller_date_id ON outgoing_shipments (nama_seller, tanggal_kirim, id)");
    echo "Index created successfully!\n";
} catch (\Exception $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

$t = microtime(true);
$res = DB::select("SELECT * FROM outgoing_shipments WHERE nama_seller = 'Mitra Aliqa' AND tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' ORDER BY id ASC LIMIT 50");
$time = (microtime(true) - $t) * 1000;
echo "Time with idx_seller_date_id: " . round($time, 2) . " ms\n";

$exp = DB::select("EXPLAIN SELECT * FROM outgoing_shipments WHERE nama_seller = 'Mitra Aliqa' AND tanggal_kirim BETWEEN '2026-08-01' AND '2026-08-31' ORDER BY id ASC LIMIT 50");
print_r($exp);
