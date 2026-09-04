<?php

namespace App\Console\Commands;

use App\Models\OutgoingShipment;
use App\Services\GoogleSheetsSyncService;
use App\Services\TrackingBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalibrateTrackerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:recalibrate 
                            {--seller=all : Filter by seller (e.g. Aliqa, Zaherba, or all)}
                            {--month=8 : Filter by shipment month (1-12 or all)}
                            {--force : Force bypass delivered/retur protection and overwrite with live NIPos status}
                            {--chunk=50 : Batch chunk size for NIPos scraper}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force recalibrate and re-scrape shipments from NIPos using hardened anti-fallthrough parser';

    /**
     * Execute the console command.
     */
    public function handle(TrackingBotService $botService, GoogleSheetsSyncService $syncService): int
    {
        $sellerOpt = $this->option('seller');
        $monthOpt = $this->option('month');
        $force = $this->option('force');
        $chunkSize = max(10, (int)$this->option('chunk'));

        $this->info("=== TRACKO TRACKER RECALIBRATOR ===");
        $this->info("Seller: {$sellerOpt} | Month: {$monthOpt} | Force Overwrite: " . ($force ? 'YES' : 'NO'));

        $query = OutgoingShipment::query();

        // 1. Filter by Seller
        if (!empty($sellerOpt) && strtolower($sellerOpt) !== 'all') {
            $query->where('nama_seller', 'like', '%' . $sellerOpt . '%');
        }

        // 2. Filter by Month
        if (!empty($monthOpt) && strtolower($monthOpt) !== 'all') {
            $mNum = (int)$monthOpt;
            if ($mNum >= 1 && $mNum <= 12) {
                $query->whereMonth('tanggal_kirim', $mNum);
            }
        }

        // 3. Filter target resi (DELIVERED / SUKSES or in-progress)
        $query->where(function ($q) {
            $q->where('status_kategori', 'SUKSES')
              ->orWhere('status_pos', 'like', '%DELIVERED%')
              ->orWhereNull('last_tracked_at');
        });

        $totalCount = $query->count();
        if ($totalCount === 0) {
            $this->warn("Tidak ada data resi yang memenuhi kriteria recalibrate.");
            return Command::SUCCESS;
        }

        $this->info("Menemukan {$totalCount} data resi untuk di-recalibrate...");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $recalibratedCount = 0;
        $correctedToInProcess = 0;
        $correctedToRetur = 0;
        $remainedDelivered = 0;
        $syncPayload = [];

        $query->select([
            'id', 'nama_seller', 'no_resi', 'nama_penerima', 'no_hp', 'alamat',
            'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori',
            'color_code', 'sla_days', 'kantor_tujuan', 'last_location', 'last_tracked_at'
        ])->chunkById($chunkSize, function ($shipments) use ($botService, $force, &$recalibratedCount, &$correctedToInProcess, &$correctedToRetur, &$remainedDelivered, &$syncPayload, $bar) {
            $resiList = $shipments->pluck('no_resi')->toArray();

            try {
                $results = $botService->trackResiList($resiList);
            } catch (Throwable $e) {
                Log::error("RecalibrateTrackerCommand: Error tracking batch: " . $e->getMessage());
                $results = [];
            }

            $now = now();

            DB::transaction(function () use ($shipments, $results, $botService, $force, $now, &$recalibratedCount, &$correctedToInProcess, &$correctedToRetur, &$remainedDelivered, &$syncPayload, $bar) {
                foreach ($shipments as $shipment) {
                    $resi = $shipment->no_resi;
                    if (isset($results[$resi])) {
                        $res = $results[$resi];

                        $newStatusPos = $res['status_pos'] ?? 'IN PROSES';
                        $newKet = $res['keterangan'] ?? 'PROSES PENGIRIMAN POS (TRANSIT)';
                        $newCat = $res['status_kategori'] ?? $botService->categorizeStatus($newStatusPos, $newKet);
                        $newColor = $res['color_code'] ?? $botService->determineColorCode($newCat);
                        $newSla = $res['sla_days'] ?? $botService->extractSlaDays($res['sla'] ?? '', $shipment->tanggal_kirim);

                        // If force is enabled, always overwrite even if previously DELIVERED
                        $shipment->status_pos = $newStatusPos;
                        $shipment->keterangan = $newKet;
                        $shipment->status_kategori = $newCat;
                        $shipment->color_code = $newColor;
                        $shipment->sla_days = $newSla;
                        $shipment->last_tracked_at = $now;

                        if (!empty($res['kantor_tujuan'])) {
                            $shipment->kantor_tujuan = $res['kantor_tujuan'];
                        }
                        if (!empty($res['last_location'])) {
                            $shipment->last_location = $res['last_location'];
                        }

                        $shipment->save();
                        $recalibratedCount++;

                        if ($newCat === 'IN_PROCESS') {
                            $correctedToInProcess++;
                        } elseif ($newCat === 'RETUR') {
                            $correctedToRetur++;
                        } else {
                            $remainedDelivered++;
                        }

                        $syncPayload[] = [
                            'seller' => $shipment->nama_seller,
                            'resi' => $shipment->no_resi,
                            'status_pos' => $newCat === 'IN_PROCESS' ? 'IN PROSES' : $shipment->status_pos,
                            'keterangan' => $shipment->keterangan,
                            'status_kategori' => $shipment->status_kategori,
                            'color_code' => $shipment->color_code,
                            'sla' => (string)$shipment->sla_days,
                            'sla_days' => $shipment->sla_days,
                            'prevent_overwrite_delivered_retur' => !$force,
                        ];
                    }
                    $bar->advance();
                }
            });
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("=== HASIL RECALIBRATE NIPOS ===");
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Resi Diproses', $recalibratedCount],
                ['Terkoreksi kembali ke IN PROSES (Transit)', $correctedToInProcess],
                ['Terkoreksi menjadi RETUR (Orange)', $correctedToRetur],
                ['Tetap DELIVERED (Sukses/Biru)', $remainedDelivered],
            ]
        );

        // Reverse sync to Google Sheets if payload exists
        if (!empty($syncPayload)) {
            $this->info("Mengirimkan " . count($syncPayload) . " hasil recalibrate ke Google Sheets via Webhook...");
            try {
                $syncSummary = $syncService->reverseSyncNiposTracking($syncPayload);
                if ($syncSummary['webhook_success'] ?? false) {
                    $this->info("Google Sheets Webhook Berhasil Disinkronkan!");
                }
            } catch (Throwable $e) {
                $this->warn("Gagal webhook Google Sheets: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
