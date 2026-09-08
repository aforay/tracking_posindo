<?php

namespace App\Console\Commands;

use App\Models\OutgoingShipment;
use App\Models\PostOffice;
use App\Services\NiposFastTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncKantorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nipos:sync-kantor
                            {--month= : Month number (1-12) to filter by}
                            {--year=2026 : Year to filter by}
                            {--limit=200 : Maximum number of shipments to process}
                            {--chunk=40 : Chunk size for NIPOS bulk tracking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync genuine Kantor Pos Tujuan (KC/KCU) directly from NIPOS API for shipments with missing or generic kantor_tujuan';

    /**
     * Execute the console command.
     */
    public function handle(NiposFastTracker $fastTracker): int
    {
        $month = $this->option('month');
        $year = (int)$this->option('year');
        $limit = (int)$this->option('limit');
        $chunkSize = max(10, min(50, (int)$this->option('chunk')));

        $genericNames = ['KC TUJUAN', 'KANTOR POS TUJUAN', 'KC POS PENGANTARAN', 'KC PENGANTARAN', 'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA'];

        $query = OutgoingShipment::query()
            ->whereNotNull('no_resi')
            ->where('no_resi', '!=', '')
            ->where(function ($q) use ($genericNames) {
                $q->whereNull('kantor_tujuan')
                  ->orWhere('kantor_tujuan', '')
                  ->orWhereIn('kantor_tujuan', $genericNames);
            });

        if (!empty($month)) {
            $query->whereMonth('tanggal_kirim', (int)$month);
        }
        if (!empty($year)) {
            $query->whereYear('tanggal_kirim', $year);
        }

        $shipments = $query->orderBy('id', 'desc')->limit($limit)->get();
        $total = $shipments->count();

        if ($total === 0) {
            $this->info("Semua kiriman sudah memiliki Kantor Pos (KC/KCU) resmi dari NIPOS.");
            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$total} kiriman yang memerlukan pembacaan KC dari NIPOS...");

        $chunks = $shipments->chunk($chunkSize);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updatedCount = 0;

        foreach ($chunks as $chunk) {
            $resis = $chunk->pluck('no_resi')->filter()->unique()->values()->all();
            if (empty($resis)) {
                $bar->advance($chunk->count());
                continue;
            }

            try {
                $results = $fastTracker->trackMany($resis, $chunkSize);

                foreach ($chunk as $shipment) {
                    $resi = $shipment->no_resi;
                    if (isset($results[$resi])) {
                        $data = $results[$resi];
                        $office = !empty($data['posisi_akhir']) ? trim($data['posisi_akhir']) : (!empty($data['kantor_tujuan']) ? trim($data['kantor_tujuan']) : null);

                        if (!empty($office) && !in_array(strtoupper($office), $genericNames)) {
                            $matched = PostOffice::matchByDestinationOrAddress($office, $shipment->alamat);
                            if ($matched) {
                                $shipment->kantor_pos_id = $matched->id;
                                $shipment->kantor_tujuan = $matched->name;
                                $shipment->last_location = $matched->name;
                            } else {
                                $shipment->kantor_tujuan = $office;
                                $shipment->last_location = $office;
                            }

                            $shipment->saveQuietly();
                            $updatedCount++;
                        }
                    }
                    $bar->advance();
                }
            } catch (\Throwable $e) {
                Log::warning("nipos:sync-kantor error on chunk: " . $e->getMessage());
                $bar->advance($chunk->count());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Selesai! {$updatedCount} dari {$total} kiriman berhasil diperbarui dengan Kantor Pos resmi dari NIPOS.");

        return Command::SUCCESS;
    }
}
