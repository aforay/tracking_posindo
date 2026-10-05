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
    protected ?string $targetSheet;
    protected mixed $targetMonth;
    protected bool $withColors;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ?string $spreadsheetIdOrUrl = null,
        string $defaultSeller = 'Aliqa',
        ?string $targetSheet = null,
        mixed $targetMonth = null,
        bool $withColors = true
    ) {
        $this->spreadsheetIdOrUrl = $spreadsheetIdOrUrl;
        $this->defaultSeller = $defaultSeller;
        $this->targetSheet = $targetSheet;
        $this->targetMonth = $targetMonth;
        $this->withColors = $withColors;
    }

    /**
     * Execute the job asynchronously in background queue worker.
     */
    public function handle(GoogleSheetsSyncService $syncService): void
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $startMsg = "ProcessGoogleSheetSyncJob started for Spreadsheet ID/URL: " . ($this->spreadsheetIdOrUrl ?: 'DEFAULT_SETTING') . " (Seller: {$this->defaultSeller})";
        Log::info($startMsg);

        try {
            $summary = $syncService->sync(
                $this->spreadsheetIdOrUrl,
                $this->defaultSeller,
                $this->targetSheet,
                $this->targetMonth,
                $this->withColors
            );
            $doneMsg = "ProcessGoogleSheetSyncJob completed successfully: Processed {$summary['total_rows_processed']} rows across {$summary['total_sheets']} sheets, saved {$summary['total_rows_inserted']} rows.";
            Log::info($doneMsg);
        } catch (Throwable $e) {
            Log::error("ProcessGoogleSheetSyncJob error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            \Illuminate\Support\Facades\Cache::put('sync_progress', [
                'is_syncing' => false,
                'current_sheet' => 'Gagal',
                'current_sheet_index' => 0,
                'total_sheets' => 0,
                'processed_rows' => 0,
                'inserted_rows' => 0,
                'percentage' => 0,
                'message' => 'Gagal sinkronisasi: ' . $e->getMessage(),
                'updated_at' => now()->toDateTimeString(),
            ], 3600);
        }
    }
}
