<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OutgoingShipment;
use App\Models\PostOffice;
use App\Services\NiposFastTracker;

$shipments = OutgoingShipment::where(function($q) {
        $q->where('kantor_tujuan', 'like', '%SOEKARNO%')
          ->orWhere('last_location', 'like', '%SOEKARNO%');
    })
    ->get(['id', 'no_resi', 'kantor_tujuan', 'last_location', 'alamat']);

echo "Found " . $shipments->count() . " shipments to resolve.\n";

$tracker = app(NiposFastTracker::class);
$chunks = $shipments->chunk(35);
$totalUpdated = 0;

foreach ($chunks as $chunkIndex => $chunk) {
    $resis = $chunk->pluck('no_resi')->filter()->values()->toArray();
    echo "Processing chunk $chunkIndex (" . count($resis) . " resis)...\n";
    
    $results = $tracker->trackChunk($resis);
    
    foreach ($chunk as $s) {
        if (!isset($results[$s->no_resi])) continue;
        
        $data = $results[$s->no_resi];
        $kt = !empty($data['kantor_tujuan']) ? trim($data['kantor_tujuan']) : null;
        $ll = !empty($data['last_location']) ? trim($data['last_location']) : null;
        $status = !empty($data['status_akhir']) ? trim($data['status_akhir']) : null;
        
        $dirty = false;
        if (!empty($kt) && !str_contains(strtoupper($kt), 'SOEKARNO')) {
            $s->kantor_tujuan = $kt;
            $dirty = true;
        }
        if (!empty($ll) && !str_contains(strtoupper($ll), 'SOEKARNO')) {
            $s->last_location = $ll;
            $dirty = true;
        }
        if (!empty($status)) {
            $s->status_pos = $status;
            $dirty = true;
        }
        
        // Match PostOffice
        $destForMatch = $s->kantor_tujuan ?: $s->last_location;
        if (!empty($destForMatch) && !str_contains(strtoupper($destForMatch), 'SOEKARNO')) {
            $po = PostOffice::matchByDestinationOrAddress($destForMatch, $s->alamat);
            if ($po && !str_starts_with(strtoupper($po->name), 'DC ')) {
                $s->kantor_pos_id = $po->id;
                $dirty = true;
            }
        }
        
        if ($dirty) {
            $s->saveQuietly();
            $totalUpdated++;
            echo "  Updated {$s->no_resi} -> KT: {$s->kantor_tujuan}, PO_ID: {$s->kantor_pos_id}\n";
        }
    }
}

echo "Done! Total updated: $totalUpdated\n";
