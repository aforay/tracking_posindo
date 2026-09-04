<?php

namespace App\Console\Commands;

use App\Jobs\ReverseSyncGoogleSheetsJob;
use App\Models\OutgoingShipment;
use App\Services\TrackingBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackNiposCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nipos:track 
                            {--resi= : Resi number(s) to track, separated by comma}
                            {--limit=50 : Number of pending resis to track}
                            {--all : Track all pending non-delivered resis}
                            {--force : Force re-track even if already marked as SUKSES or RETUR}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Track resi directly against NIPos API and update local database with reverse sync';

    /**
     * Execute the console command.
     */
    public function handle(TrackingBotService $botService): int
    {
        $resiOption = $this->option('resi');
        $limit = (int)$this->option('limit');
        $trackAll = $this->option('all');
        $force = $this->option('force');

        $query = OutgoingShipment::query();

        if (!empty($resiOption)) {
            $resis = array_filter(array_map('trim', explode(',', $resiOption)));
            $query->whereIn('no_resi', $resis);
            $this->info("Fetching specific " . count($resis) . " resis: " . implode(', ', $resis));
        } else {
            if (!$force) {
                $query->where(function ($q) {
                    $q->whereIn('status_kategori', ['IN_PROCESS', 'FOLLOW_UP'])
                      ->orWhereNull('status_kategori')
                      ->orWhere('status_pos', '!=', 'DELIVERED');
                })->whereNotIn('status_kategori', ['SUKSES', 'RETUR']);
            }

            if (!$trackAll) {
                $query->limit($limit);
            }
        }

        $shipments = $query->orderBy('last_tracked_at', 'asc')->get();

        if ($shipments->isEmpty()) {
            $this->warn("Tidak ada resi yang memenuhi kriteria untuk dilacak.");
            return Command::SUCCESS;
        }

        $totalToTrack = $shipments->count();
        $this->info("Memulai pelacakan NIPos untuk {$totalToTrack} data resi...");
        Log::info("TrackNiposCommand: Starting tracking for {$totalToTrack} shipments.");

        $tableRows = [];
        $syncPayload = [];
        $updatedCount = 0;

        $bar = $this->output->createProgressBar($totalToTrack);
        $bar->start();

        cache()->put('bot_running', true, 3600);
        cache()->put('bot_progress', ['current' => 0, 'total' => $totalToTrack], 3600);

        foreach ($shipments->chunk(100) as $chunk) {
            $chunkResis = $chunk->pluck('no_resi')->toArray();
            $results = $botService->trackResiList($chunkResis);

            DB::transaction(function () use ($chunk, $results, $botService, &$updatedCount, &$syncPayload, &$tableRows, $totalToTrack, $bar) {
                $now = now();
                foreach ($chunk as $shipment) {
                    $resi = $shipment->no_resi;
                    $oldStatus = $shipment->status_pos ?: 'BELUM DI-TRACK';

                    if (isset($results[$resi])) {
                        $res = $results[$resi];
                        $statusPos = $res['status_pos'] ?? ($res['status'] ?? 'IN PROSES');
                        $keterangan = $res['keterangan'] ?? ($res['penerima'] ?? 'PROSES PENGIRIMAN POS (TRANSIT)');

                        $shipment->status_pos = $statusPos;
                        $shipment->keterangan = $keterangan;

                        $category = $res['status_kategori'] ?? $botService->categorizeStatus($shipment->status_pos, $shipment->keterangan);
                        $shipment->status_kategori = $category;
                        $shipment->color_code = $res['color_code'] ?? $botService->determineColorCode($category);

                        $shipment->sla_days = $res['sla_days'] ?? ($res['sla'] ?? ($shipment->sla_days ?: 2));
                        $shipment->last_tracked_at = $now;
                        $shipment->save();
                        $updatedCount++;

                        $syncPayload[] = [
                            'resi' => $shipment->no_resi,
                            'status_pos' => $shipment->status_pos,
                            'keterangan' => $shipment->keterangan,
                            'status_kategori' => $shipment->status_kategori,
                            'color_code' => $shipment->color_code ?: 'PUTIH',
                            'sla_days' => $shipment->sla_days,
                        ];

                        $tableRows[] = [
                            $shipment->id,
                            $resi,
                            $oldStatus,
                            $shipment->status_pos,
                            $shipment->status_kategori,
                            $shipment->color_code,
                            substr($shipment->keterangan, 0, 30),
                        ];
                    } else {
                        $tableRows[] = [
                            $shipment->id,
                            $resi,
                            $oldStatus,
                            'TIDAK DITEMUKAN / TIMEOUT',
                            $shipment->status_kategori,
                            $shipment->color_code ?: 'PUTIH',
                            '-',
                        ];
                    }

                    $bar->advance();
                }
            });

            cache()->put('bot_progress', ['current' => $updatedCount, 'total' => $totalToTrack], 3600);
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['ID', 'No Resi', 'Status Lama', 'Status NIPos Baru', 'Kategori', 'Warna', 'Keterangan'],
            $tableRows
        );

        $this->info("Berhasil memperbarui {$updatedCount} dari " . $shipments->count() . " data resi di database!");

        if (!empty($syncPayload)) {
            $this->info("Mendispatch Reverse Sync ke Google Sheets via background queue worker...");
            ReverseSyncGoogleSheetsJob::dispatch($syncPayload);
        }

        cache()->put('bot_progress', ['current' => $totalToTrack, 'total' => $totalToTrack], 3600);
        cache()->forget('bot_running');

        return Command::SUCCESS;
    }
}
