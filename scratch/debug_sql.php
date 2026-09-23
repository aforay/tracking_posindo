<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\DB::listen(function($query) {
    echo "SQL: " . $query->sql . "\n";
    echo "BINDINGS: " . json_encode($query->bindings) . "\n\n";
});

$req = \Illuminate\Http\Request::create('/shipments', 'GET', [
    'search' => 'MALIK',
    'seller' => 'Mitra Aliqa',
    'month' => 'ALL'
]);

$direct = \Illuminate\Support\Facades\DB::select("select id, nama_seller, nama_penerima, tanggal_kirim, no_resi from outgoing_shipments where nama_penerima LIKE '%MALIK%'");
echo "Direct query with %MALIK%: " . count($direct) . "\n";
print_r($direct);

$directCase = \Illuminate\Support\Facades\DB::select("select id, nama_seller, nama_penerima, tanggal_kirim, no_resi from outgoing_shipments where LOWER(nama_penerima) LIKE '%malik%'");
echo "Direct query with LOWER: " . count($directCase) . "\n";
print_r($directCase);

