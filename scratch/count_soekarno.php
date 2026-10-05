<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$q = \App\Models\OutgoingShipment::where('kantor_tujuan', 'like', '%SOEKARNO%')
    ->orWhere('last_location', 'like', '%SOEKARNO%');

echo "Total shipments with SOEKARNO in kantor_tujuan or last_location: " . $q->count() . "\n";

$byColor = \App\Models\OutgoingShipment::where('kantor_tujuan', 'like', '%SOEKARNO%')
    ->orWhere('last_location', 'like', '%SOEKARNO%')
    ->selectRaw('color_code, count(*) as cnt')
    ->groupBy('color_code')
    ->pluck('cnt', 'color_code');

echo "By color code: " . json_encode($byColor, JSON_PRETTY_PRINT) . "\n";
