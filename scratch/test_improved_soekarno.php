<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tracker = app(\App\Services\NiposFastTracker::class);
$sampleResis = [
    'BAC021026262A974C88A', // Gunung Sarik / Padang
    'BAC021026131C056920D', // Simalungun / Medan
    'BAC021026437AAC93B23', // Banda Aceh
];

$results = $tracker->trackChunk($sampleResis);
foreach ($sampleResis as $resi) {
    echo "Resi: $resi\n";
    echo "  Status: " . ($results[$resi]['status_akhir'] ?? 'N/A') . "\n";
    echo "  Posisi: " . ($results[$resi]['posisi_akhir'] ?? 'N/A') . "\n";
    echo "  Kantor Tujuan: " . ($results[$resi]['kantor_tujuan'] ?? 'N/A') . "\n";
    echo "  Last Loc: " . ($results[$resi]['last_location'] ?? 'N/A') . "\n";
}
