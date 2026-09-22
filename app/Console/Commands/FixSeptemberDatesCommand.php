<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSeptemberDatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:fix-september';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kunci dan kembalikan resi BAC310826 yang tergeser ke September kembali ke tab Agustus (2026-08-31)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memeriksa resi akhir Agustus yang tergeser ke September...');

        $affected = DB::table('outgoing_shipments')
            ->where('no_resi', 'like', 'BAC310826%')
            ->where('tanggal_kirim', '2026-09-01')
            ->update([
                'tanggal_kirim' => '2026-08-31',
                'updated_at' => now(),
            ]);

        $this->info("Berhasil mengembalikan {$affected} resi ke tanggal 2026-08-31 (Bulan Agustus).");

        $sisaSept = DB::table('outgoing_shipments')->whereMonth('tanggal_kirim', 9)->count();
        $totalAgt = DB::table('outgoing_shipments')->whereMonth('tanggal_kirim', 8)->count();

        $this->line("Total data bulan Agustus saat ini : {$totalAgt}");
        $this->line("Total data bulan September saat ini: {$sisaSept}");

        return Command::SUCCESS;
    }
}
