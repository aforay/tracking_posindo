<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$indexes = DB::select('SHOW INDEX FROM outgoing_shipments');
foreach ($indexes as $idx) {
    echo "Key_name: {$idx->Key_name} | Column_name: {$idx->Column_name} | Seq: {$idx->Seq_in_index}\n";
}
