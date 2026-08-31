<?php

namespace App\Jobs;

use App\Services\GoogleSheetsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessGoogleSheetSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $spreadsheetIdOrUrl;
    protected string $defaultSeller;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $spreadsheetIdOrUrl = null, string $defaultSeller = 'Aliqa')
    {
        $this->spreadsheetIdOrUrl = $spreadsheetIdOrUrl;
        $this->defaultSeller = $defaultSeller;
    }

    /**
     * Execute the job asynchronously in background queue worker.
     */
    public function handle(GoogleSheetsSyncService $syncService): void
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $startMsg = "ProcessGoogleSheetSyncJob started for Spreadsheet ID/URL: " . ($this->spreadsheetIdOrUrl ?: 'DEFAULT_SETTING');
        Log::info($startMsg);

        try {
            $summary = $syncService->sync($this->spreadsheetIdOrUrl, $this->defaultSeller);
            $doneMsg = "ProcessGoogleSheetSyncJob completed successfully: Processed {$summary['total_rows_processed']} rows across {$summary['total_sheets']} sheets, saved {$summary['total_rows_inserted']} rows.";
            Log::info($doneMsg);
        } catch (Throwable $e) {
            Log::error("ProcessGoogleSheetSyncJob error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
