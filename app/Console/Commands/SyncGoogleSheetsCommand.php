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
    protected $signature = 'sheets:sync {spreadsheet_id? : Optional Google Spreadsheet ID or URL} {--seller=Aliqa : Default seller name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Outgoing Shipments from dynamic Google Spreadsheet (Januari - Agustus)';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSheetsSyncService $syncService): int
    {
        $spreadsheetId = $this->argument('spreadsheet_id');
        $seller = $this->option('seller');

        $this->info("Starting Google Sheets synchronization...");
        $summary = $syncService->sync($spreadsheetId, $seller);

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
