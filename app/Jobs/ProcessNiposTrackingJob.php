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
            $now = now();

            \Illuminate\Support\Facades\DB::transaction(function () use ($shipments, $results, $botService, $now, &$updatedCount, &$syncPayload) {
                // FU statuses yang bisa di-override ketika NIPOS konfirmasi final delivery
                $fuColorCodes = ['PUTIH', 'KUNING', 'HIJAU', 'BIRU_TUA'];

                foreach ($shipments as $shipment) {
                    try {
                        $resi = $shipment->no_resi;
                        if (isset($results[$resi])) {
                            $res = $results[$resi];
                            if (!empty($res['is_fallback'])) {
                                // Live NIPOS query timed out or failed - reset last_tracked_at so it can be re-attempted
                                $shipment->last_tracked_at = null;
                                $shipment->saveQuietly();
                                continue;
                            }
                            $statusPos = $res['status_pos'] ?? ($res['status'] ?? 'IN PROSES');
                            $keterangan = $res['keterangan'] ?? ($res['penerima'] ?? 'PROSES PENGIRIMAN POS (TRANSIT)');

                            $shipment->status_pos = $statusPos;
                            $shipment->keterangan = $keterangan;

                            $category = $res['status_kategori'] ?? $botService->categorizeStatus($shipment->status_pos, $shipment->keterangan);
                            $newColorCode = $res['color_code'] ?? $botService->determineColorCode($category);

                            // === FORCE OVERRIDE ===
                            // Jika NIPOS konfirmasi DELIVERED/RETUR, override status FU apapun
                            // (PUTIH/KUNING/HIJAU/BIRU_TUA) ke status final BIRU/ORANGE.
                            // CS tidak bisa mempertahankan status FU jika NIPOS sudah konfirmasi selesai.
                            $prevColorCode = $shipment->color_code;
                            if (in_array($category, ['SUKSES', 'RETUR']) && in_array($prevColorCode, $fuColorCodes)) {
                                $newColorCode = $botService->determineColorCode($category);
                            }

                            $shipment->status_kategori = $category;
                            $shipment->color_code = $newColorCode;

                            $shipment->sla_days = $res['sla_days'] ?? ($res['sla'] ?? ($shipment->sla_days ?: 2));
                            $shipment->last_tracked_at = $now;

                            // Detect and link Kantor Pos Tujuan (Fast in-memory matching)
                            $kantorTujuan = $res['kantor_tujuan'] ?? $shipment->kantor_tujuan;
                            $matchedOffice = \App\Models\PostOffice::matchByDestinationOrAddress($kantorTujuan, $shipment->alamat);
                            if ($matchedOffice) {
                                $shipment->kantor_tujuan = $kantorTujuan ?: $matchedOffice->name;
                                $shipment->kantor_pos_id = $matchedOffice->id;
                            } elseif (!empty($kantorTujuan)) {
                                $shipment->kantor_tujuan = $kantorTujuan;
                            }

                            if (!empty($res['last_location'])) {
                                $shipment->last_location = $res['last_location'];
                            }

                            $shipment->save();
                            $updatedCount++;

                            $isInProc = $shipment->status_kategori === 'IN_PROCESS';
                            $slaStr = $botService->formatRunningSla($shipment->tanggal_kirim, $shipment->status_kategori, $shipment->sla_days);

                            // Label FU yang human-readable untuk kolom di Sheet
                            $statusLabelMap = [
                                'BIRU'     => 'PAKET SUKSES (DELIVERED)',
                                'ORANGE'   => 'PAKET RETUR (RETURN)',
                                'KUNING'   => 'SUDAH DI FU (1x)',
                                'HIJAU'    => 'FU 2 KALI',
                                'BIRU_TUA' => 'FU POS (ESKALASI KC/KCU)',
                                'PUTIH'    => 'BELUM DI FOLLOW UP',
                            ];
                            $colorCode  = $shipment->color_code ?: 'PUTIH';
                            $statusLabel = $statusLabelMap[$colorCode] ?? 'BELUM DI FOLLOW UP';

                            $syncPayload[] = [
                                'resi'             => $shipment->no_resi,
                                'seller'           => $shipment->nama_seller,
                                'status_pos'       => $shipment->status_pos,
                                'keterangan'       => $shipment->keterangan,
                                'status_kategori'  => $shipment->status_kategori,
                                'color_code'       => $colorCode,
                                'status_label'     => $statusLabel,   // untuk kolom FU di Sheet
                                'fu_type'          => $colorCode,     // alias untuk Apps Script
                                'sla'              => $slaStr,
                                'sla_days'         => $slaStr,
                                'updated_at'       => now()->toDateTimeString(),
                                'prevent_overwrite_delivered_retur' => true,
                            ];
                        }
                    } catch (Throwable $e) {
                        Log::error("ProcessNiposTrackingJob [ERROR]: Skipped resi ID {$shipment->id} ({$shipment->no_resi}) due to error: " . $e->getMessage());
                        continue;
                    }
                }
            });

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
