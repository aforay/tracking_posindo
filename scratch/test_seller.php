<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// What distinct nama_seller actually exist in the database?
$sellers = DB::select("SELECT DISTINCT nama_seller, COUNT(*) as cnt FROM outgoing_shipments GROUP BY nama_seller");
print_r($sellers);
