<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsSyncService;
use Illuminate\Console\Command;

class SyncGoogleSheetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sheets:sync
                            {spreadsheet_id? : Optional Google Spreadsheet ID or URL}
                            {--sheet= : Specific sheet tab name (e.g. "AGUSTUS (ZAHERBA)")}
                            {--month= : Specific month number (1-12) or ALL}
                            {--seller=Aliqa : Default seller name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Outgoing Shipments from dynamic Google Spreadsheet (Support --sheet and --month filtering)';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSheetsSyncService $syncService): int
    {
        $spreadsheetId = $this->argument('spreadsheet_id');
        $seller = $this->option('seller');
        $targetSheet = $this->option('sheet');
        $targetMonth = $this->option('month');

        // Default to current running month if neither --sheet nor --month is provided
        if (empty($targetSheet) && (empty($targetMonth) && $targetMonth !== '0')) {
            $targetMonth = (int)date('n');
        }

        $sheetLabel = $targetSheet ? "Sheet: {$targetSheet}" : ($targetMonth && strtoupper((string)$targetMonth) !== 'ALL' ? "Bulan: {$targetMonth}" : "Seluruh Sheet (ALL)");

        $this->info("Starting Google Sheets synchronization for {$sheetLabel}...");
        $summary = $syncService->sync($spreadsheetId, $seller, $targetSheet, $targetMonth);

        // Catat waktu sync terakhir di cache agar frontend bisa menampilkannya
        \Illuminate\Support\Facades\Cache::put('last_sheet_sync_at', now()->toDateTimeString(), 3600);

        $this->info("=========================================");
        $this->info("Google Sheets Sync Completed Successfully!");
        $this->info("Spreadsheet ID: " . $summary['spreadsheet_id']);
        $this->info("Total Sheets Processed: " . $summary['total_sheets']);
        $this->info("Total Rows Processed: " . $summary['total_rows_processed']);
        $this->info("Total Rows Saved/Queued: " . $summary['total_rows_inserted']);
        $this->info("=========================================");

        return Command::SUCCESS;
    }
}
