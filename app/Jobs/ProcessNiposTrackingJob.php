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

    public int $timeout = 600;
    public int $tries = 2;

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
                  ->limit(5000);
            }

            if (empty($this->shipmentIds)) {
                $query->whereNotIn('status_kategori', ['SUKSES', 'RETUR']);
            }

            $shipments = $query->get();

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
            $syncPayload = [];

            foreach ($shipments as $shipment) {
                try {
                    $resi = $shipment->no_resi;
                    if (isset($results[$resi])) {
                        $res = $results[$resi];
                        $statusPos = $res['status_pos'] ?? ($res['status'] ?? 'DELIVERED');
                        $keterangan = $res['keterangan'] ?? ($res['penerima'] ?? 'DITERIMA YANG BERSANGKUTAN');

                        $shipment->status_pos = $statusPos;
                        $shipment->keterangan = $keterangan;

                        if (method_exists($botService, 'categorizeStatus')) {
                            $shipment->status_kategori = $res['status_kategori'] ?? $botService->categorizeStatus($shipment->status_pos, $shipment->keterangan);
                        } else {
                            $shipment->status_kategori = $res['status_kategori'] ?? ($res['kategori'] ?? 'IN_PROCESS');
                        }

                        $statusUpper = strtoupper($statusPos);
                        if (str_contains($statusUpper, 'DELIVERED') && !str_contains($statusUpper, 'RETURN')) {
                            $shipment->status_pos = 'DELIVERED';
                            $shipment->status_kategori = 'SUKSES';
                            $shipment->color_code = 'BIRU';
                        } elseif (str_contains($statusUpper, 'RETURN') || str_contains($statusUpper, 'RETUR')) {
                            $shipment->status_kategori = 'RETUR';
                            $shipment->color_code = 'ORANGE';
                        } elseif (str_contains($statusUpper, 'FAILED') || str_contains($statusUpper, 'GAGAL')) {
                            $shipment->status_kategori = 'FOLLOW_UP';
                            if (empty($shipment->color_code) || $shipment->color_code === 'PUTIH') {
                                $shipment->color_code = 'KUNING';
                            }
                        }

                        $shipment->sla_days = $res['sla_days'] ?? ($res['sla'] ?? ($shipment->sla_days ?: 2));
                        $shipment->last_tracked_at = now();
                        $shipment->save();
                        $updatedCount++;

                        Log::info("ProcessNiposTrackingJob [DB SAVED]: Resi {$resi} updated to status_pos='{$shipment->status_pos}', ket='{$shipment->keterangan}', kategori='{$shipment->status_kategori}', color='{$shipment->color_code}'");

                        $syncPayload[] = [
                            'resi' => $shipment->no_resi,
                            'status_pos' => $shipment->status_pos,
                            'keterangan' => $shipment->keterangan,
                            'status_kategori' => $shipment->status_kategori,
                            'color_code' => $shipment->color_code ?: 'PUTIH',
                            'sla_days' => $shipment->sla_days,
                        ];
                    } else {
                        Log::warning("ProcessNiposTrackingJob: No tracking result returned from NIPOS for resi {$resi}");
                    }
                } catch (Throwable $e) {
                    // Log individual shipment error and skip to next resi without failing the job
                    Log::error("ProcessNiposTrackingJob [ERROR]: Skipped resi ID {$shipment->id} ({$shipment->no_resi}) due to error: " . $e->getMessage());
                    continue;
                }
            }

            Log::info("ProcessNiposTrackingJob finished updating {$updatedCount} of " . count($shipments) . " shipments.");

            // Auto-Update Back to Google Sheets (Reverse Sync via Background Queue Worker)
            if (!empty($syncPayload)) {
                Log::info("ProcessNiposTrackingJob: Dispatching ReverseSyncGoogleSheetsJob for " . count($syncPayload) . " updated shipments.");
                \App\Jobs\ReverseSyncGoogleSheetsJob::dispatch($syncPayload);
                event(new \App\Events\NiposTrackingUpdatedEvent($syncPayload));
            }
        } catch (Throwable $e) {
            Log::error("ProcessNiposTrackingJob global catch: " . $e->getMessage());
        }
    }
}
