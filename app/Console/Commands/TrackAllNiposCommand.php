<?php

namespace App\Console\Commands;

use App\Jobs\ReverseSyncGoogleSheetsJob;
use App\Models\OutgoingShipment;
use App\Services\TrackingBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackAllNiposCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nipos:track-all
                            {--chunk=50 : Number of pending resis per batch chunk}
                            {--limit=0 : Maximum total resis to track (0 = all non-final)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Track all non-final shipments from NIPos API in safe chunks (50-100 per batch)';

    /**
     * Execute the console command.
     */
    public function handle(TrackingBotService $botService): int
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $chunkSize = max(10, (int)$this->option('chunk'));
        $maxLimit = (int)$this->option('limit');

        $this->info("Memulai pelacakan NIPos otomatis (Chunk size: {$chunkSize})...");
        Log::info("TrackAllNiposCommand: Starting automatic tracking with chunk size {$chunkSize}");

        $query = OutgoingShipment::query()
            ->needsTracking()
            ->orderBy('last_tracked_at', 'asc');

        if ($maxLimit > 0) {
            $query->limit($maxLimit);
        }

        $pendingShipments = $query->get();
        $totalToTrack = $pendingShipments->count();

        if ($totalToTrack === 0) {
            $this->info("Tidak ada resi non-final yang perlu dilacak. Semua resi sudah SUKSES / RETUR.");
            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$totalToTrack} resi belum final. Melacak dalam batch {$chunkSize} resi...");
        $bar = $this->output->createProgressBar($totalToTrack);
        $bar->start();

        $totalUpdated = 0;
        $allSyncPayload = [];

        foreach ($pendingShipments->chunk($chunkSize) as $chunk) {
            $chunkResis = $chunk->pluck('no_resi')->toArray();

            try {
                $results = $botService->trackResiList($chunkResis);
            } catch (Throwable $e) {
                Log::error("TrackAllNiposCommand batch error: " . $e->getMessage());
                $results = [];
            }

            $batchPayload = [];

            DB::transaction(function () use ($chunk, $results, $botService, &$totalUpdated, &$batchPayload, $bar) {
                $now = now();
                foreach ($chunk as $shipment) {
                    $resi = $shipment->no_resi;
                    if (isset($results[$resi])) {
                        $res = $results[$resi];
                        $statusPos = $res['status_pos'] ?? ($res['status'] ?? 'IN PROSES');
                        $keterangan = $res['keterangan'] ?? ($res['penerima'] ?? 'PROSES PENGIRIMAN POS (TRANSIT)');

                        $shipment->status_pos = $statusPos;
                        $shipment->keterangan = $keterangan;

                        $category = $res['status_kategori'] ?? $botService->categorizeStatus($shipment->status_pos, $shipment->keterangan);
                        $color = $res['color_code'] ?? $botService->determineColorCode($category);

                        // If already RETUR / ORANGE and not delivered to customer, stay RETUR (ORANGE)
                        if (($shipment->color_code === 'ORANGE' || $shipment->status_kategori === 'RETUR' || $shipment->isReturn()) && $category !== 'SUKSES') {
                            $category = 'RETUR';
                            $color = 'ORANGE';
                        }

                        $shipment->status_kategori = $category;
                        $shipment->color_code = $color;

                        $shipment->sla_days = $res['sla_days'] ?? ($res['sla'] ?? ($shipment->sla_days ?: 2));
                        $shipment->last_tracked_at = $now;
                        $shipment->save();
                        $totalUpdated++;

                        $batchPayload[] = [
                            'resi' => $shipment->no_resi,
                            'status_pos' => $shipment->status_pos,
                            'keterangan' => $shipment->keterangan,
                            'status_kategori' => $shipment->status_kategori,
                            'color_code' => $shipment->color_code ?: 'PUTIH',
                            'sla_days' => $shipment->sla_days,
                        ];
                    }
                    $bar->advance();
                }
            });

            if (!empty($batchPayload)) {
                $allSyncPayload = array_merge($allSyncPayload, $batchPayload);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Pelacakan selesai! Berhasil memperbarui {$totalUpdated} dari {$totalToTrack} resi.");

        if (!empty($allSyncPayload)) {
            $this->info("Mendispatch Reverse Sync ke Google Sheets untuk " . count($allSyncPayload) . " resi...");
            ReverseSyncGoogleSheetsJob::dispatch($allSyncPayload);
        }

        return Command::SUCCESS;
    }
}
