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
use Throwable;

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
     * Execute the job in background queue worker safely.
     */
    public function handle(TrackingBotService $botService): void
    {
        Log::info("ProcessNiposTrackingJob started for " . count($this->shipmentIds) . " shipments.");

        try {
            $query = OutgoingShipment::query();
            if (!empty($this->shipmentIds)) {
                $query->whereIn('id', $this->shipmentIds);
            } else {
                $query->where(function ($q) {
                    $q->whereIn('status_kategori', ['IN_PROCESS', 'FOLLOW_UP'])
                      ->orWhereNull('status_kategori');
                })->whereNotIn('status_kategori', ['SUKSES', 'RETUR'])
                  ->orderBy('last_tracked_at', 'asc')
                  ->limit(500);
            }

            // Smart Filtering: Exclude SUKSES and RETUR shipments from scraping
            $shipments = $query->whereNotIn('status_kategori', ['SUKSES', 'RETUR'])->get();

            if ($shipments->isEmpty()) {
                Log::info("ProcessNiposTrackingJob: No shipments require tracking (All SUKSES/RETUR or empty).");
                return;
            }

            $resiList = $shipments->pluck('no_resi')->toArray();

            // Try-catch around bot service call so a network issue doesn't crash the entire job
            try {
                $results = $botService->trackResiList($resiList, $this->targetUrl);
            } catch (Throwable $e) {
                Log::error("ProcessNiposTrackingJob: Error fetching resi list: " . $e->getMessage());
                $results = [];
            }

            $updatedCount = 0;
            foreach ($shipments as $shipment) {
                try {
                    $resi = $shipment->no_resi;
                    if (isset($results[$resi])) {
                        $res = $results[$resi];
                        $shipment->status_pos = $res['status_pos'] ?? ($res['status'] ?? $shipment->status_pos);
                        $shipment->keterangan = $res['keterangan'] ?? $shipment->keterangan;

                        if (method_exists($botService, 'categorizeStatus')) {
                            $shipment->status_kategori = $res['status_kategori'] ?? $botService->categorizeStatus($shipment->status_pos, $shipment->keterangan);
                        } else {
                            $shipment->status_kategori = $res['status_kategori'] ?? ($res['kategori'] ?? $shipment->status_kategori);
                        }

                        $shipment->sla_days = $res['sla_days'] ?? ($res['sla'] ?? $shipment->sla_days);
                        $shipment->last_tracked_at = now();
                        $shipment->save();
                        $updatedCount++;
                    }
                } catch (Throwable $e) {
                    // Log individual shipment error and skip to next resi without failing the job
                    Log::warning("ProcessNiposTrackingJob: Skipped resi ID {$shipment->id} ({$shipment->no_resi}) due to error: " . $e->getMessage());
                    continue;
                }
            }

            Log::info("ProcessNiposTrackingJob finished updating {$updatedCount} of " . count($shipments) . " shipments.");
        } catch (Throwable $e) {
            Log::error("ProcessNiposTrackingJob global catch: " . $e->getMessage());
        }
    }
}
