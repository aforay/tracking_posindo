<?php

namespace App\Jobs;

use App\Models\OutgoingShipment;
use App\Services\TrackingBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNiposTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $shipmentIds;
    protected ?string $targetUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(array $shipmentIds = [], ?string $targetUrl = null)
    {
        $this->shipmentIds = $shipmentIds;
        $this->targetUrl = $targetUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(TrackingBotService $botService): void
    {
        Log::info("ProcessNiposTrackingJob started for " . count($this->shipmentIds) . " shipments.");

        $query = OutgoingShipment::query();
        if (!empty($this->shipmentIds)) {
            $query->whereIn('id', $this->shipmentIds);
        } else {
            // Track untracked or in-process/follow-up shipments
            $query->whereIn('status_kategori', ['IN_PROCESS', 'FOLLOW_UP'])
                ->orderBy('last_tracked_at', 'asc')
                ->limit(500);
        }

        $shipments = $query->get();
        if ($shipments->isEmpty()) {
            Log::info("ProcessNiposTrackingJob: No shipments to track.");
            return;
        }

        $resiList = $shipments->pluck('no_resi')->toArray();
        $results = $botService->trackResiList($resiList, $this->targetUrl);

        foreach ($shipments as $shipment) {
            $resi = $shipment->no_resi;
            if (isset($results[$resi])) {
                $res = $results[$resi];
                $shipment->status_pos = $res['status_pos'] ?? $shipment->status_pos;
                $shipment->keterangan = $res['keterangan'] ?? $shipment->keterangan;
                $shipment->status_kategori = $res['status_kategori'] ?? $botService->categorizeStatus($shipment->status_pos, $shipment->keterangan);
                $shipment->sla_days = $res['sla_days'] ?? $shipment->sla_days;
                $shipment->last_tracked_at = now();
                $shipment->save();
            }
        }

        Log::info("ProcessNiposTrackingJob finished updating " . count($shipments) . " shipments.");
    }
}
