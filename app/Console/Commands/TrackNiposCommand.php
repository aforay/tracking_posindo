<?php

namespace App\Console\Commands;

use App\Jobs\ReverseSyncGoogleSheetsJob;
use App\Models\OutgoingShipment;
use App\Services\NiposFastTracker;
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
    protected $description = 'Track resi directly against NIPos API using NiposFastTracker and update local database';

    /**
     * Execute the console command.
     */
    public function handle(NiposFastTracker $fastTracker, TrackingBotService $botService): int
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
            $query->needsTracking($force);

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
        $this->info("Memulai pelacakan NIPos via NiposFastTracker (chunk 40) untuk {$totalToTrack} data resi...");
        Log::info("TrackNiposCommand: Starting tracking for {$totalToTrack} shipments via NiposFastTracker.");

        $tableRows = [];
        $syncPayload = [];
        $updatedCount = 0;

        $bar = $this->output->createProgressBar($totalToTrack);
        $bar->start();

        cache()->put('bot_running', true, 3600);
        cache()->put('bot_progress', ['current' => 0, 'total' => $totalToTrack], 3600);

        // Kumpulkan resi per chunk berisi 40 resi per request
        foreach ($shipments->chunk(40) as $chunk) {
            $chunkResis = $chunk->pluck('no_resi')->toArray();
            $results = $fastTracker->trackMany($chunkResis, 40);

            DB::transaction(function () use ($chunk, $results, $botService, &$updatedCount, &$syncPayload, &$tableRows, $bar) {
                $now = now()->toDateTimeString();
                $updatedIds = [];
                $casesStatusPos = [];
                $casesKeterangan = [];
                $casesKategori = [];
                $casesColor = [];
                $casesSlaDays = [];
                $casesTrackedAt = [];

                foreach ($chunk as $shipment) {
                    $resi = $shipment->no_resi;
                    $oldStatus = $shipment->status_pos ?: 'BELUM DI-TRACK';

                    if (isset($results[$resi])) {
                        $res = $results[$resi];
                        // Simpan string teks mentah dari kolom STATUS AKHIR apa adanya ke database tanpa klasifikasi if-else
                        $rawStatusAkhir = $res['status_akhir'] ?? '';
                        $statusPos = !empty($rawStatusAkhir) ? $rawStatusAkhir : ($shipment->status_pos ?: 'ON PROCESS');
                        $keterangan = $statusPos;

                        $category = $botService->categorizeStatus($statusPos, $keterangan);
                        $color = $botService->determineColorCode($category);

                        // If already RETUR / ORANGE and not delivered to customer, stay RETUR (ORANGE)
                        if (($shipment->color_code === 'ORANGE' || $shipment->status_kategori === 'RETUR' || $shipment->isReturn()) && $category !== 'SUKSES') {
                            $category = 'RETUR';
                            $color = 'ORANGE';
                        }

                        // Ekstraksi SLA asli dari hasil NIPOS
                        $rawSla = $res['sla'] ?? null;
                        $tglKirim = $shipment->tanggal_kirim ?: ($res['tanggal_kolekting'] ?? null);
                        $slaDays = $botService->extractSlaDays((string)$rawSla, $tglKirim, $category);

                        $updatedIds[] = $shipment->id;
                        $casesStatusPos[] = "WHEN id = {$shipment->id} THEN " . DB::getPdo()->quote($statusPos);
                        $casesKeterangan[] = "WHEN id = {$shipment->id} THEN " . DB::getPdo()->quote($keterangan);
                        $casesKategori[] = "WHEN id = {$shipment->id} THEN " . DB::getPdo()->quote($category);
                        $casesColor[] = "WHEN id = {$shipment->id} THEN " . DB::getPdo()->quote($color);
                        $casesSlaDays[] = "WHEN id = {$shipment->id} THEN " . (int)$slaDays;
                        $casesTrackedAt[] = "WHEN id = {$shipment->id} THEN " . DB::getPdo()->quote($now);

                        $updatedCount++;

                        $syncPayload[] = [
                            'resi' => $shipment->no_resi,
                            'status_pos' => $statusPos,
                            'keterangan' => $keterangan,
                            'status_kategori' => $category,
                            'color_code' => $color,
                            'sla_days' => $slaDays,
                        ];

                        $tableRows[] = [
                            $shipment->id,
                            $resi,
                            $oldStatus,
                            $statusPos,
                            $category,
                            $color,
                            $slaDays,
                            substr($keterangan, 0, 30),
                        ];
                    } else {
                        $tableRows[] = [
                            $shipment->id,
                            $resi,
                            $oldStatus,
                            'TIDAK DITEMUKAN / TIMEOUT',
                            $shipment->status_kategori,
                            $shipment->color_code ?: 'PUTIH',
                            $shipment->sla_days ?: 2,
                            '-',
                        ];
                    }

                    $bar->advance();
                }

                // Batch update ke MySQL agar cepat dan tidak N+1
                if (!empty($updatedIds)) {
                    $idList = implode(',', $updatedIds);
                    DB::statement("
                        UPDATE outgoing_shipments SET
                            status_pos = CASE " . implode(' ', $casesStatusPos) . " END,
                            keterangan = CASE " . implode(' ', $casesKeterangan) . " END,
                            status_kategori = CASE " . implode(' ', $casesKategori) . " END,
                            color_code = CASE " . implode(' ', $casesColor) . " END,
                            sla_days = CASE " . implode(' ', $casesSlaDays) . " END,
                            last_tracked_at = CASE " . implode(' ', $casesTrackedAt) . " END
                        WHERE id IN ({$idList})
                    ");
                }
            });

            cache()->put('bot_progress', ['current' => $updatedCount, 'total' => $totalToTrack], 3600);
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['ID', 'No Resi', 'Status Lama', 'Status NIPos Baru', 'Kategori', 'Warna', 'SLA', 'Keterangan'],
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
