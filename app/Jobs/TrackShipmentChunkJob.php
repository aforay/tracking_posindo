<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\TrackingBotService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tracks one chunk of resi numbers in the background.
 *
 * Resi yang sudah SUKSES (DELIVERED) atau RETUR otomatis dilewati, sehingga
 * bot hanya melacak resi baru atau yang masih IN_PROCESS.
 */
class TrackShipmentChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 1800;

    public int $tries = 2;

    /**
     * @param  array<int, string>  $resis
     */
    public function __construct(
        public array $resis,
        public int $year,
        public ?string $targetUrl = null,
    ) {
        $this->onQueue(config('tracking.queue'));
    }

    public function handle(TrackingBotService $botService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $shipments = Shipment::query()
            ->whereIn('resi', $this->resis)
            ->where('year', $this->year)
            ->needsTracking()
            ->get();

        if ($shipments->isEmpty()) {
            return;
        }

        $results = $botService->trackResiList($shipments->pluck('resi')->all(), $this->targetUrl);

        foreach ($shipments as $shipment) {
            if (isset($results[$shipment->resi])) {
                $shipment->applyTrackingResult($results[$shipment->resi]);
            }
        }
    }
}
