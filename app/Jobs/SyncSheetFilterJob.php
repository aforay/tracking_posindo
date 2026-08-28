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

class SyncSheetFilterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $seller;
    protected ?string $month;
    protected ?string $color;
    protected ?string $search;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $seller = 'ALL', ?string $month = 'ALL', ?string $color = null, ?string $search = null)
    {
        $this->seller = $seller;
        $this->month = $month;
        $this->color = $color;
        $this->search = $search;
    }

    /**
     * Execute the job to sync filter to Google Spreadsheet via Webhook.
     */
    public function handle(GoogleSheetsSyncService $syncService): void
    {
        try {
            $syncService->syncFilter(
                $this->seller,
                $this->month,
                $this->color,
                $this->search
            );
        } catch (Throwable $e) {
            Log::warning("SyncSheetFilterJob error: " . $e->getMessage());
        }
    }
}
