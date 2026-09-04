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

class UpdateSheetStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $resiList;
    protected string $statusColor;
    protected ?string $note;
    protected ?string $escalationDate;
    protected ?string $fuTimestamp;

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $resiList,
        string $statusColor,
        ?string $note = null,
        ?string $escalationDate = null,
        ?string $fuTimestamp = null
    ) {
        $this->resiList       = array_values(array_unique(array_filter(array_map('trim', $resiList))));
        $this->statusColor    = strtoupper($statusColor);
        $this->note           = $note;
        $this->escalationDate = $escalationDate;
        $this->fuTimestamp    = $fuTimestamp ?? now()->toDateTimeString();
    }

    /**
     * Execute the job asynchronously in background queue worker.
     */
    public function handle(GoogleSheetsSyncService $syncService): void
    {
        if (empty($this->resiList)) {
            return;
        }

        $startMsg = "UpdateSheetStatusJob: Starting Google Sheets FU status update for " . count($this->resiList) . " resis → {$this->statusColor}";
        Log::info($startMsg);

        try {
            $summary = $syncService->updateResiStatus(
                $this->resiList,
                $this->statusColor,
                $this->note,
                $this->escalationDate,
                $this->fuTimestamp
            );

            $doneMsg = "UpdateSheetStatusJob completed for " . count($this->resiList) . " resis → {$this->statusColor}. Webhook: " . ($summary['webhook_success'] ? 'OK' : 'NO');
            Log::info($doneMsg, $summary);
        } catch (Throwable $e) {
            Log::error("UpdateSheetStatusJob error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
