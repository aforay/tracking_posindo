<?php

namespace App\Console\Commands;

use App\Services\NiposFastTracker;
use Illuminate\Console\Command;

class QuickTestNiposCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nipos:quick-test {resi : Nomor resi yang akan diuji coba pelacakannya}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quick test track single resi via NiposFastTracker and display raw status_akhir';

    /**
     * Execute the console command.
     */
    public function handle(NiposFastTracker $fastTracker): int
    {
        $resi = trim((string)$this->argument('resi'));

        $this->info("Menghubungi endpoint internal NIPos untuk resi: {$resi}...");

        $startTime = microtime(true);
        $result = $fastTracker->trackSingle($resi);
        $duration = round(microtime(true) - $startTime, 2);

        if (!$result) {
            $this->error("Gagal mendapatkan data atau resi tidak ditemukan di NIPos ({$duration}s).");
            return Command::FAILURE;
        }

        $this->info("Berhasil mendapatkan data dari NIPos dalam {$duration}s:");
        $this->line("--------------------------------------------------");
        $this->line("No Resi           : " . ($result['resi'] ?? $resi));
        $this->line("Status Akhir      : " . ($result['status_akhir'] ?? '-'));
        $this->line("Posisi Akhir (KC) : " . ($result['posisi_akhir'] ?? '-'));
        $this->line("Kantor Kirim      : " . ($result['kantor_kirim'] ?? '-'));
        $this->line("Penerima          : " . ($result['penerima'] ?? '-'));
        $this->line("SLA               : " . ($result['sla'] ?? '-'));
        $this->line("Index Kolom Resi  : " . ($result['barcode_index'] ?? '-'));
        $this->line("Index Kolom Status: " . ($result['status_akhir_index'] ?? '-'));
        $this->line("Index Kolom SLA   : " . ($result['sla_index'] ?? '-'));
        $this->line("--------------------------------------------------");

        return Command::SUCCESS;
    }
}
