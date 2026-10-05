<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OutgoingShipment;
use App\Models\PostOffice;
use App\Services\TrackingBotService;

$bot = app(TrackingBotService::class);
$returShipments = OutgoingShipment::where('status_kategori', 'RETUR')
    ->orWhere('color_code', 'ORANGE')
    ->orWhere('status_pos', 'like', '%RETUR%')
    ->get();

echo "Total retur shipments: " . $returShipments->count() . "\n";
$updated = 0;
foreach ($returShipments as $s) {
    $origKT = $s->kantor_tujuan;
    // Check if status_pos or last_location has SPP
    if (preg_match('/\b(SPP|KC|KCU)\s+([A-Za-z0-9\s\.\,\-\/]+?)(?=(?:\s+(?:oleh|dan|telah|dengan|tujuan|Tanggal|Petugas|\d{2}:\d{2}|\[|<))|[\n\r]|$)/i', (string)$s->status_pos, $spm)) {
        $cand = trim($spm[1] . ' ' . $spm[2]);
        $candUpper = strtoupper(preg_replace('/\s+/', ' ', $cand));
        if (str_starts_with($candUpper, 'SPP ') && $s->kantor_tujuan !== $candUpper) {
            $s->kantor_tujuan = $candUpper;
            $s->last_location = $candUpper;
            $office = PostOffice::where('name', $candUpper)->first();
            if ($office) {
                $s->kantor_pos_id = $office->id;
            }
            $s->save();
            $updated++;
            echo "Updated resi " . $s->no_resi . " from '$origKT' to '$candUpper'\n";
        }
    }
}
echo "Total updated: $updated\n";
