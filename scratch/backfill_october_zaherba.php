<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OutgoingShipment;
use App\Models\PostOffice;

$octoberZaherbaNull = OutgoingShipment::where('nama_seller', 'like', '%Zaherba%')
    ->where('tanggal_kirim', '>=', '2026-10-01')
    ->where(function($q) { $q->whereNull('kantor_tujuan')->orWhere('kantor_tujuan', ''); })
    ->get(['id', 'no_resi', 'alamat', 'kantor_tujuan']);

echo "October Zaherba with null KT: " . $octoberZaherbaNull->count() . "\n";

$updated = 0;
foreach ($octoberZaherbaNull as $s) {
    $matched = PostOffice::matchByDestinationOrAddress(null, $s->alamat);
    if ($matched && !str_starts_with(strtoupper($matched->name), 'DC ')) {
        $s->kantor_pos_id = $matched->id;
        $s->kantor_tujuan = $matched->name;
        $s->last_location = $matched->name;
        $s->saveQuietly();
        $updated++;
        echo "  [{$s->no_resi}] -> {$matched->name}\n";
    }
}

echo "Total updated by address dictionary: $updated\n";
