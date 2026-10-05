<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OutgoingShipment;

$septAliqaFuPos = OutgoingShipment::whereMonth('tanggal_kirim', 9)
    ->where('nama_seller', 'like', '%Aliqa%')
    ->where('color_code', 'BIRU_TUA')
    ->get();

echo "Total Aliqa Sept BIRU_TUA: " . $septAliqaFuPos->count() . "\n";
foreach ($septAliqaFuPos as $s) {
    echo "ID: {$s->id} | Resi: {$s->no_resi} | Tgl: {$s->tanggal_kirim} | FU Date: {$s->fu_pos_date} | Status: {$s->status_pos} | Ket: {$s->keterangan} | Created: {$s->created_at} | Updated: {$s->updated_at}\n";
}
