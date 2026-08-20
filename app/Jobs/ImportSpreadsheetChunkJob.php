<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Support\ChunkReadFilter;
use App\Support\SpreadsheetRowMapper;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Imports one chunk of spreadsheet rows (default 500) and queues the resi
 * numbers of that chunk that still need to be tracked.
 */
class ImportSpreadsheetChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 900;

    public int $tries = 3;

    public function __construct(
        public string $filePath,
        public string $sheetName,
        public string $month,
        public int $year,
        public int $startRow,
        public int $endRow,
        public string $filterMode = 'only_empty',
        public ?string $targetUrl = null,
    ) {
        $this->onQueue(config('tracking.queue'));
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! file_exists($this->filePath)) {
            Log::warning("ImportSpreadsheetChunkJob: file not found {$this->filePath}");

            return;
        }

        $rows = $this->readChunk();
        if (empty($rows)) {
            return;
        }

        $mapped = [];
        foreach ($rows as $idx => $row) {
            $attributes = SpreadsheetRowMapper::map($row, $this->month, $this->year, $this->startRow + $idx);
            if ($attributes !== null) {
                $mapped[$attributes['resi']] = $attributes;
            }
        }

        if (empty($mapped)) {
            return;
        }

        $existing = Shipment::query()
            ->whereIn('resi', array_keys($mapped))
            ->where('year', $this->year)
            ->get()
            ->keyBy('resi');

        $inserts = [];
        $now = now()->toDateTimeString();
        $resisToTrack = [];

        foreach ($mapped as $resi => $attributes) {
            $rawStatus = $attributes['raw_status'];
            unset($attributes['raw_status']);

            if (isset($existing[$resi])) {
                $shipment = $existing[$resi];
                $shipment->fill($attributes);
                if ($shipment->isDirty()) {
                    $shipment->save();
                }
            } else {
                $inserts[] = $attributes + ['created_at' => $now, 'updated_at' => $now];
            }

            // Smart filtering: lewati resi yang sudah SUKSES / RETUR
            if (SpreadsheetRowMapper::shouldTrack($rawStatus, $this->filterMode)) {
                $resisToTrack[] = $resi;
            }
        }

        if (! empty($inserts)) {
            DB::transaction(function () use ($inserts) {
                foreach (array_chunk($inserts, 50) as $chunk) {
                    DB::table('shipments')->insert($chunk);
                }
            });
        }

        $this->queueTracking($resisToTrack);
    }

    /**
     * Read only this job's row range to keep memory usage flat.
     */
    protected function readChunk(): array
    {
        $reader = IOFactory::createReaderForFile($this->filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new ChunkReadFilter($this->sheetName, $this->startRow, $this->endRow));
        }

        $spreadsheet = $reader->load($this->filePath);
        $sheet = $spreadsheet->getSheetByName($this->sheetName) ?: $spreadsheet->getActiveSheet();
        $rows = $sheet->rangeToArray("A{$this->startRow}:M{$this->endRow}", null, false, false, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * Dispatch tracking jobs for the resi numbers that are still unresolved.
     *
     * @param  array<int, string>  $resis
     */
    protected function queueTracking(array $resis): void
    {
        if (empty($resis)) {
            return;
        }

        $pending = Shipment::query()
            ->whereIn('resi', $resis)
            ->where('year', $this->year)
            ->needsTracking()
            ->pluck('resi')
            ->all();

        if (empty($pending)) {
            return;
        }

        $jobs = [];
        foreach (array_chunk($pending, max(1, (int) config('tracking.track_chunk_size'))) as $chunk) {
            $jobs[] = new TrackShipmentChunkJob($chunk, $this->year, $this->targetUrl);
        }

        if ($batch = $this->batch()) {
            $batch->add($jobs);

            return;
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }
    }
}
