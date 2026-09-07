<?php

namespace App\Console\Commands;

use App\Services\NiposApiService;
use Illuminate\Console\Command;

class TestNiposApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nipos:test-api {resi : Nomor resi yang akan dilacak}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uji coba tracking 1 resi menggunakan NiposApiService AJAX';

    /**
     * Execute the console command.
     */
    public function handle(NiposApiService $apiService): int
    {
        $resi = trim($this->argument('resi'));
        $this->info("Melacak resi: {$resi} menggunakan NiposApiService...");

        $result = $apiService->trackSingle($resi);

        if (!$result) {
            $this->error("Tidak ada data yang ditemukan atau request gagal untuk resi {$resi}.");
            return Command::FAILURE;
        }

        $this->info("Berhasil mendapatkan data dari NIPos:");
        $this->line("--------------------------------------------------");
        $this->line("No Resi            : " . ($result['no_resi'] ?? '-'));
        $this->line("Status Akhir NIPos : " . ($result['status_akhir_nipos'] ?? '-'));
        $this->line("Keterangan         : " . ($result['keterangan'] ?? '-'));
        $this->line("Posisi Akhir       : " . ($result['posisi_akhir'] ?? '-'));
        $this->line("Penerima           : " . ($result['penerima'] ?? '-'));
        $this->line("Tanggal Update     : " . ($result['tanggal_update'] ?? '-'));
        $this->line("Petugas Update     : " . ($result['petugas_update'] ?? '-'));
        $this->line("SLA                : " . ($result['sla'] ?? '-'));
        $this->line("--------------------------------------------------");

        $this->comment("Raw Result Array:");
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
