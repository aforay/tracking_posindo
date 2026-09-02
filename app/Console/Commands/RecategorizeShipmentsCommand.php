<?php

namespace App\Console\Commands;

use App\Models\OutgoingShipment;
use App\Services\TrackingBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecategorizeShipmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nipos:recategorize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-categorize all shipments in MySQL database with RETUR-first priority rules';

    /**
     * Execute the console command.
     */
    public function handle(TrackingBotService $botService): int
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $this->info("Memulai proses re-kategorisasi seluruh data di database MySQL...");
        Log::info("RecategorizeShipmentsCommand: Starting database re-categorization");

        $totalCount = OutgoingShipment::count();
        if ($totalCount === 0) {
            $this->warn("Database kosong. Tidak ada data untuk dire-kategorisasi.");
            return Command::SUCCESS;
        }

        $this->info("Total data di database: {$totalCount} baris.");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $updatedCount = 0;
        $returCount = 0;
        $suksesCount = 0;
        $followUpCount = 0;
        $inProcessCount = 0;

        OutgoingShipment::chunk(1000, function ($shipments) use ($botService, &$updatedCount, &$returCount, &$suksesCount, &$followUpCount, &$inProcessCount, $bar) {
            DB::transaction(function () use ($shipments, $botService, &$updatedCount, &$returCount, &$suksesCount, &$followUpCount, &$inProcessCount, $bar) {
                foreach ($shipments as $shipment) {
                    $newCategory = $botService->categorizeStatus($shipment->status_pos, $shipment->keterangan);
                    $newColor = $botService->determineColorCode($newCategory);

                    if ($shipment->status_kategori !== $newCategory || $shipment->color_code !== $newColor) {
                        $shipment->status_kategori = $newCategory;
                        $shipment->color_code = $newColor;
                        $shipment->save();
                        $updatedCount++;
                    }

                    match ($newCategory) {
                        'RETUR' => $returCount++,
                        'SUKSES' => $suksesCount++,
                        'FOLLOW_UP' => $followUpCount++,
                        default => $inProcessCount++,
                    };

                    $bar->advance();
                }
            });
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("=========================================");
        $this->info("Re-kategorisasi Selesai!");
        $this->info("Total Baris Diperbarui: {$updatedCount}");
        $this->info("Total RETUR: {$returCount}");
        $this->info("Total SUKSES: {$suksesCount}");
        $this->info("Total FOLLOW_UP: {$followUpCount}");
        $this->info("Total IN_PROCESS: {$inProcessCount}");
        $this->info("=========================================");

        // January Stats Summary
        $janTotal = OutgoingShipment::whereMonth('tanggal_kirim', 1)->count();
        $janRetur = OutgoingShipment::whereMonth('tanggal_kirim', 1)->where('status_kategori', 'RETUR')->count();
        $janSukses = OutgoingShipment::whereMonth('tanggal_kirim', 1)->where('status_kategori', 'SUKSES')->count();

        $this->info("Statistik Bulan Januari saat ini:");
        $this->info("Total Januari: {$janTotal}");
        $this->info("Retur Januari: {$janRetur}");
        $this->info("Sukses Januari: {$janSukses}");
        $this->info("=========================================");

        return Command::SUCCESS;
    }
}
