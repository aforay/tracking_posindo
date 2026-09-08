<?php

namespace App\Jobs;

use App\Models\OutgoingShipment;
use App\Models\PostOffice;
use App\Services\TrackingBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
     * Execute the job in background queue worker safely with Bulk Upsert & In-Memory Lookup.
     */
    public function handle(TrackingBotService $botService): void
    {
        Log::info("ProcessNiposTrackingJob started for " . count($this->shipmentIds) . " shipments.");

        try {
            $query = OutgoingShipment::query();
            if (!empty($this->shipmentIds)) {
                $query->whereIn('id', $this->shipmentIds);
            } else {
                $query->needsTracking()
                      ->orderBy('last_tracked_at', 'asc')
                      ->limit(5000);
            }

            // Pre-select required fields to minimize memory consumption
            $shipments = $query->select([
                'id', 'nama_seller', 'no_resi', 'nama_penerima', 'no_hp', 'alamat',
                'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori',
                'color_code', 'fu_pos_date', 'noted', 'sla_days', 'kantor_tujuan',
                'kantor_pos_id', 'last_location', 'last_tracked_at', 'created_at'
            ])->get();

            if ($shipments->isEmpty()) {
                Log::info("ProcessNiposTrackingJob: No shipments require tracking (All SUKSES/RETUR or empty).");
                return;
            }

            // Warm up in-memory PostOffice cache once before the loop
            PostOffice::getCachedOffices();

            $resiList = $shipments->pluck('no_resi')->toArray();

            try {
                $results = $botService->trackResiList($resiList, $this->targetUrl);
            } catch (Throwable $e) {
                Log::error("ProcessNiposTrackingJob: Error fetching resi list: " . $e->getMessage());
                $results = [];
            }

            $now = now();
            $nowStr = $now->toDateTimeString();
            $fuColorCodes = ['PUTIH', 'KUNING', 'HIJAU', 'BIRU_TUA'];

            $updateBatch = [];
            $fallbackIds = [];
            $syncPayload = [];

            foreach ($shipments as $shipment) {
                try {
                    $resi = $shipment->no_resi;
                    if (!isset($results[$resi])) {
                        continue;
                    }

                    $res = $results[$resi];
                    if (!empty($res['is_fallback'])) {
                        // Live NIPOS query timed out or failed - collect ID to reset last_tracked_at
                        $fallbackIds[] = $shipment->id;
                        continue;
                    }

                    $statusPos = $res['status_pos'] ?? ($res['status'] ?? 'IN PROSES');
                    $keterangan = $res['keterangan'] ?? ($res['penerima'] ?? 'PROSES PENGIRIMAN POS (TRANSIT)');

                    $category = $res['status_kategori'] ?? $botService->categorizeStatus($statusPos, $keterangan);
                    $newColorCode = $res['color_code'] ?? $botService->determineColorCode($category);

                    // === FORCE OVERRIDE ===
                    $prevColorCode = $shipment->color_code;
                    if (in_array($category, ['SUKSES', 'RETUR']) && in_array($prevColorCode, $fuColorCodes)) {
                        $newColorCode = $botService->determineColorCode($category);
                    }

                    // === RETUR PERSISTENCE ===
                    if (($prevColorCode === 'ORANGE' || $shipment->status_kategori === 'RETUR' || $shipment->isReturn()) && $category !== 'SUKSES') {
                        $category = 'RETUR';
                        $newColorCode = 'ORANGE';
                    }

                    $rawSla = $res['sla_days'] ?? ($res['sla'] ?? ($shipment->sla_days ?: 2));
                    $tglKirim = $shipment->tanggal_kirim ? (is_string($shipment->tanggal_kirim) ? substr($shipment->tanggal_kirim, 0, 10) : $shipment->tanggal_kirim->format('Y-m-d')) : ($res['tanggal_kolekting'] ?? null);
                    $slaDays = $botService->extractSlaDays((string)$rawSla, $tglKirim, $category);

                    // Prioritize real Kantor Pos / Posisi Akhir directly from NIPOS
                    $genericNames = ['KC PENGANTARAN', 'KC TUJUAN', 'POS PENGANTARAN', 'KC POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA'];
                    $resTujuan = !empty($res['kantor_tujuan']) ? trim((string)$res['kantor_tujuan']) : (!empty($res['posisi_akhir']) ? trim((string)$res['posisi_akhir']) : null);
                    if ($resTujuan && in_array(strtoupper($resTujuan), $genericNames)) {
                        $resTujuan = null;
                    }
                    $currentTujuan = $shipment->kantor_tujuan;
                    if ($currentTujuan && in_array(strtoupper(trim((string)$currentTujuan)), $genericNames)) {
                        $currentTujuan = null;
                    }
                    $kantorTujuan = $resTujuan ?: $currentTujuan;
                    $kantorPosId = $shipment->kantor_pos_id;
                    $matchedOffice = PostOffice::matchByDestinationOrAddress($kantorTujuan, $shipment->alamat);
                    if ($matchedOffice) {
                        $kantorTujuan = $matchedOffice->name;
                        $kantorPosId = $matchedOffice->id;
                    }

                    $lastLocation = $resTujuan ?: ($res['last_location'] ?? $shipment->last_location);
                    $createdAtStr = $shipment->created_at ? (is_string($shipment->created_at) ? $shipment->created_at : $shipment->created_at->toDateTimeString()) : $nowStr;

                    $updateBatch[] = [
                        'nama_seller'     => $shipment->nama_seller,
                        'no_resi'         => $shipment->no_resi,
                        'nama_penerima'   => $shipment->nama_penerima,
                        'no_hp'           => $shipment->no_hp,
                        'alamat'          => $shipment->alamat,
                        'tanggal_kirim'   => $tglKirim,
                        'status_pos'      => $statusPos,
                        'keterangan'      => $keterangan,
                        'status_kategori' => $category,
                        'color_code'      => $newColorCode,
                        'fu_pos_date'     => $shipment->fu_pos_date,
                        'noted'           => $shipment->noted,
                        'sla_days'        => $slaDays,
                        'kantor_tujuan'   => $kantorTujuan,
                        'kantor_pos_id'   => $kantorPosId,
                        'last_location'   => $lastLocation,
                        'last_tracked_at' => $nowStr,
                        'created_at'      => $createdAtStr,
                        'updated_at'      => $nowStr,
                    ];

                    $slaStr = $botService->formatRunningSla($tglKirim, $category, $slaDays);
                    $statusLabelMap = [
                        'BIRU'     => 'PAKET SUKSES (DELIVERED)',
                        'ORANGE'   => 'PAKET RETUR (RETURN)',
                        'KUNING'   => 'SUDAH DI FU (1x)',
                        'HIJAU'    => 'FU 2 KALI',
                        'BIRU_TUA' => 'FU POS (ESKALASI KC/KCU)',
                        'PUTIH'    => 'BELUM DI FOLLOW UP',
                    ];
                    $statusLabel = $statusLabelMap[$newColorCode] ?? 'BELUM DI FOLLOW UP';

                    $syncPayload[] = [
                        'resi'             => $shipment->no_resi,
                        'seller'           => $shipment->nama_seller,
                        'status_pos'       => $statusPos,
                        'keterangan'       => $keterangan,
                        'status_kategori'  => $category,
                        'color_code'       => $newColorCode,
                        'status_label'     => $statusLabel,
                        'fu_type'          => $newColorCode,
                        'sla'              => $slaStr,
                        'sla_days'         => $slaStr,
                        'updated_at'       => $nowStr,
                        'prevent_overwrite_delivered_retur' => true,
                    ];
                } catch (Throwable $e) {
                    Log::error("ProcessNiposTrackingJob [ERROR]: Skipped resi ID {$shipment->id} ({$shipment->no_resi}) due to error: " . $e->getMessage());
                    continue;
                }
            }

            // Bulk Batch Update / Upsert in Transaction (500 items per chunk)
            $updatedCount = count($updateBatch);
            DB::transaction(function () use ($updateBatch, $fallbackIds) {
                if (!empty($fallbackIds)) {
                    DB::table('outgoing_shipments')->whereIn('id', $fallbackIds)->update(['last_tracked_at' => null]);
                }

                if (!empty($updateBatch)) {
                    foreach (array_chunk($updateBatch, 500) as $chunk) {
                        DB::table('outgoing_shipments')->upsert(
                            $chunk,
                            ['no_resi'],
                            [
                                'nama_seller', 'nama_penerima', 'no_hp', 'alamat', 'tanggal_kirim',
                                'status_pos', 'keterangan', 'status_kategori', 'color_code',
                                'fu_pos_date', 'noted', 'sla_days', 'kantor_tujuan', 'kantor_pos_id',
                                'last_location', 'last_tracked_at', 'updated_at'
                            ]
                        );
                    }
                }
            });

            Log::info("ProcessNiposTrackingJob finished bulk updating {$updatedCount} of " . count($shipments) . " shipments.");

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
