<?php

namespace App\Console\Commands;

use App\Models\OutgoingShipment;
use App\Services\GoogleSheetsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PushSheetUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sheets:push-updates
                            {--month= : Specific month number (1-12) to process, or ALL}
                            {--sheet= : Specific sheet tab name to process (e.g. "AGUSTUS (ZAHERBA)")}
                            {--seller= : Filter specific seller (e.g. "Aliqa" or "Zaherba")}
                            {--chunk=300 : Number of tracking items per batch request (300-500 recommended)}
                            {--all : Force push all shipments without month filtering}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push updated NIPos tracking status & SLA from MySQL database back to Google Sheets via Webhook';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSheetsSyncService $syncService): int
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $chunkSize = max(50, (int)$this->option('chunk'));
        $monthOpt = $this->option('month');
        $sheetOpt = $this->option('sheet');
        $sellerOpt = $this->option('seller');
        $forceAll = $this->option('all');

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $monthSheetMapZaherba = [
            1 => 'JANUARI (ZAHERBA)',
            2 => 'FEBRUARI (ZAHERBA)',
            3 => 'MARET (ZAHERBA)',
            4 => 'APRIL (ZAHERBA)',
            5 => 'MEI (ZAHERBA)',
            6 => 'JUNI (ZAHERBA)',
            7 => 'JULI (ZAHERBA)',
            8 => 'AGUSTUS (ZAHERBA)',
            9 => 'SEPTEMBER (ZAHERBA)',
            10 => 'OKTOBER (ZAHERBA)',
            11 => 'NOVEMBER (ZAHERBA)',
            12 => 'DESEMBER (ZAHERBA)',
        ];

        $monthSheetMapAliqa = [
            1 => 'JANUARI 2026 (FP ALIQA)',
            2 => 'FEBRUARI 2026 (FP ALIQA)',
            3 => 'MARET 2026 (FP ALIQA)',
            4 => 'APRIL 2026 (FP ALIQA)',
            5 => 'MEI 2026 (FP ALIQA)',
            6 => 'JUNI 2026 (FP ALIQA).',
            7 => 'JULI 2026 (FP ALIQA)',
            8 => 'AGUSTUS 2026 (FP ALIQA)',
            9 => 'SEPTEMBER 2026 (FP ALIQA)',
            10 => 'OKTOBER 2026 (FP ALIQA)',
            11 => 'NOVEMBER 2026 (FP ALIQA)',
            12 => 'DESEMBER 2026 (FP ALIQA)',
        ];

        $query = OutgoingShipment::query();

        if (!empty($sellerOpt) && strtoupper((string)$sellerOpt) !== 'ALL') {
            $query->where('nama_seller', 'LIKE', "%{$sellerOpt}%");
        }

        // 1. Month / Sheet Filter Handling
        $targetMonth = null;
        if (!empty($sheetOpt)) {
            $sheetUpper = strtoupper($sheetOpt);
            foreach ($monthNames as $mNum => $mName) {
                $mUpper = strtoupper($mName);
                $shortUpper = strtoupper(substr($mName, 0, 3));
                if (str_contains($sheetUpper, $mUpper) || str_contains($sheetUpper, $shortUpper)) {
                    $targetMonth = $mNum;
                    break;
                }
            }
        }

        if ($targetMonth === null && !empty($monthOpt) && strtoupper((string)$monthOpt) !== 'ALL') {
            $targetMonth = (int)$monthOpt;
        } elseif ($targetMonth === null && !$forceAll && empty($monthOpt) && empty($sheetOpt)) {
            // Default to current running month
            $targetMonth = (int)date('n');
        }

        if ($targetMonth !== null && $targetMonth >= 1 && $targetMonth <= 12) {
            $query->whereMonth('tanggal_kirim', $targetMonth);
            $monthLabel = $monthNames[$targetMonth] ?? "Bulan {$targetMonth}";
        } else {
            $monthLabel = "Seluruh Bulan";
        }

        // 2. Filter only shipments that have been tracked or have definitive category (SUKSES/RETUR/FOLLOW_UP)
        if (!$forceAll) {
            $query->where(function ($q) {
                $q->whereNotNull('last_tracked_at')
                  ->orWhereIn('status_kategori', ['SUKSES', 'RETUR', 'FOLLOW_UP']);
            });
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info("Tidak ada data resi yang perlu di-push ke Google Sheets untuk {$monthLabel}.");
            return Command::SUCCESS;
        }

        $this->info("Memproses {$totalCount} resi untuk {$monthLabel} (Chunk size: {$chunkSize})...");
        Log::info("PushSheetUpdatesCommand: Starting reverse sync for {$monthLabel} ({$totalCount} items, chunk {$chunkSize})");

        $totalPushed = 0;
        $successBatches = 0;
        $totalRowsUpdatedInGas = 0;

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        // Process in DB chunks to preserve memory using chunkById and minimal selected columns
        $query->select(['id', 'nama_seller', 'no_resi', 'tanggal_kirim', 'status_pos', 'keterangan', 'status_kategori', 'sla_days', 'last_tracked_at'])
              ->chunkById($chunkSize, function ($shipments) use ($syncService, $monthSheetMapZaherba, $monthSheetMapAliqa, $sellerOpt, $targetMonth, &$totalPushed, &$successBatches, &$totalRowsUpdatedInGas, $bar) {
                  $batchPayload = [];
                  foreach ($shipments as $shipment) {
                      $isDelivered = $shipment->status_kategori === 'SUKSES';
                      $isRetur = $shipment->status_kategori === 'RETUR';
                      $isInProcess = !$isDelivered && !$isRetur;

                      $isAliqa = str_contains(strtoupper($shipment->nama_seller ?? ''), 'ALIQA') || str_contains(strtoupper($sellerOpt ?? ''), 'ALIQA');

                      $mNum = $shipment->tanggal_kirim ? (int)date('n', strtotime($shipment->tanggal_kirim)) : ($targetMonth ?: 8);
                      $sheetName = $isAliqa
                          ? ($monthSheetMapAliqa[$mNum] ?? 'AGUSTUS 2026 (FP ALIQA)')
                          : ($monthSheetMapZaherba[$mNum] ?? 'AGUSTUS (ZAHERBA)');

                      $botService = app(\App\Services\TrackingBotService::class);
                      $slaStr = $botService->formatRunningSla($shipment->tanggal_kirim, $shipment->status_kategori ?: 'IN_PROCESS', $shipment->sla_days ?: 2);

                      if ($isInProcess) {
                          $statusPosText = 'IN PROSES';
                          $keteranganText = $shipment->keterangan ?: ($shipment->status_pos ?: 'PROSES PENGIRIMAN POS');
                          $colorCode = 'PUTIH';
                          $statusKategori = 'IN_PROCESS';
                      } elseif ($isRetur) {
                          $statusPosText = $shipment->status_pos ?: 'DELIVERED (RETURN DELIVERY)';
                          $keteranganText = $shipment->keterangan ?: 'DITERIMA PENGIRIM (MITRA)';
                          $colorCode = 'ORANGE';
                          $statusKategori = 'RETUR';
                      } else {
                          $statusPosText = 'DELIVERED';
                          $keteranganText = $shipment->keterangan ?: 'DITERIMA YANG BERSANGKUTAN';
                          $colorCode = 'BIRU';
                          $statusKategori = 'SUKSES';
                      }

                      $batchPayload[] = [
                          'seller' => $shipment->nama_seller ?: ($isAliqa ? 'Aliqa' : 'Zaherba'),
                          'resi' => $shipment->no_resi,
                          'sheet_name' => $sheetName,
                          'sheet' => $sheetName,
                          'status_pos' => $statusPosText,
                          'keterangan' => $keteranganText,
                          'status_kategori' => $statusKategori,
                          'color_code' => $colorCode,
                          'sla' => $slaStr,
                          'sla_days' => $slaStr,
                          'prevent_overwrite_delivered_retur' => true,
                      ];
                  }

                  try {
                      $summary = $syncService->reverseSyncNiposTracking($batchPayload);
                      if ($summary['webhook_success'] ?? false) {
                          $successBatches++;
                      }
                      $gasUpdated = $summary['response']['updated_count'] ?? 0;
                      $totalRowsUpdatedInGas += $gasUpdated;
                      $totalPushed += count($batchPayload);
                  } catch (\Throwable $e) {
                      Log::error("PushSheetUpdatesCommand batch error: " . $e->getMessage());
                  }

                  $bar->advance(count($shipments));
              });

        $bar->finish();
        $this->newLine(2);
        $this->info("=========================================");
        $this->info("Push Status ke Google Sheets Selesai!");
        $this->info("Bulan Diproses: {$monthLabel}");
        $this->info("Total Resi Di-push: {$totalPushed} / {$totalCount}");
        $this->info("Total Batch Sukses HTTP 200: {$successBatches}");
        $this->info("Total Baris Berhasil Terisi di Spreadsheet: {$totalRowsUpdatedInGas} baris");
        $this->info("=========================================");

        return Command::SUCCESS;
    }
}
