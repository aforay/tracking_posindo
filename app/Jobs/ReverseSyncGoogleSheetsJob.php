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

class ReverseSyncGoogleSheetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    protected array $trackingItems;

    /**
     * Create a new job instance.
     *
     * @param array $trackingItems Array of tracking items with resi, status_pos, keterangan, color_code, etc.
     */
    public function __construct(array $trackingItems)
    {
        $this->trackingItems = array_values($trackingItems);
    }

    /**
     * Execute the job in the background queue worker.
     */
    public function handle(GoogleSheetsSyncService $syncService): void
    {
        if (empty($this->trackingItems)) {
            return;
        }

        $count = count($this->trackingItems);
        $startMsg = "ReverseSyncGoogleSheetsJob: Auto-updating {$count} NIPos tracking statuses back to Google Sheets in background...";
        dump($startMsg);
        Log::info($startMsg);

        try {
            $summary = $syncService->reverseSyncNiposTracking($this->trackingItems);

            $doneMsg = "ReverseSyncGoogleSheetsJob: Completed reverse sync for {$count} items. Webhook Success: " . ($summary['webhook_success'] ? 'YES' : 'NO');
            dump($doneMsg);
            Log::info($doneMsg, $summary);
        } catch (Throwable $e) {
            $errMsg = "ReverseSyncGoogleSheetsJob error: " . $e->getMessage();
            dump("ERROR REVERSE SYNC: " . $errMsg);
            Log::error($errMsg, [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
