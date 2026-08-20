<?php

namespace Tests\Feature;

use App\Jobs\ImportSpreadsheetChunkJob;
use App\Jobs\TrackShipmentChunkJob;
use App\Models\Shipment;
use App\Services\TrackingBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedImportTest extends TestCase
{
    use RefreshDatabase;

    protected function csvContent(): string
    {
        $csv = "NO,TANGGAL,NAMA KONSUMEN,INVOICE,RESI,ALAMAT,NAMA CS,PRODUK,NO HP,JUMLAH COD,KETERANGAN,TRACKING POS,SLA\n";
        $csv .= "1,2026-08-01,Rina,INV001,RESI_DELIVERED_01,Jl Mawar 1,CRM DILA,LAMBUNG,0812,250000,DITERIMA YANG BERSANGKUTAN,DELIVERED,2\n";
        $csv .= "2,2026-08-01,Budi,INV002,RESI_RETUR_01,Jl Melati 2,CRM DILA,LAMBUNG,0813,250000,RETUR,DELIVERED (RETURN DELIVERY),9\n";
        $csv .= "3,2026-08-01,Sari,INV003,RESI_PROSES_01,Jl Kenanga 3,CRM DILA,LAMBUNG,0814,250000,PROSES PENGIRIMAN POS,ON PROCESS,1\n";

        return $csv;
    }

    protected function writeCsvFile(): string
    {
        $path = storage_path('app/testing_import_'.uniqid().'.csv');
        file_put_contents($path, $this->csvContent());

        return $path;
    }

    public function test_background_upload_dispatches_chunk_jobs_in_a_batch(): void
    {
        Bus::fake();

        $file = UploadedFile::fake()->createWithContent('shipments.csv', $this->csvContent());

        $response = $this->postJson('/process', [
            'excel_file' => $file,
            'month' => 'AGUSTUS',
            'year' => 2026,
            'filter_mode' => 'undelivered',
            'background' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1
                && $batch->jobs->first() instanceof ImportSpreadsheetChunkJob;
        });
    }

    public function test_chunk_job_imports_rows_and_only_queues_unfinished_resi(): void
    {
        Queue::fake();

        $path = $this->writeCsvFile();

        (new ImportSpreadsheetChunkJob($path, 'Worksheet', 'AGUSTUS', 2026, 2, 4, 'undelivered'))->handle();

        $this->assertDatabaseHas('shipments', ['resi' => 'RESI_DELIVERED_01', 'color_code' => 'BIRU']);
        $this->assertDatabaseHas('shipments', ['resi' => 'RESI_RETUR_01', 'color_code' => 'ORANGE']);
        $this->assertDatabaseHas('shipments', ['resi' => 'RESI_PROSES_01', 'color_code' => 'PUTIH']);

        Queue::assertPushed(TrackShipmentChunkJob::class, function (TrackShipmentChunkJob $job) {
            return $job->resis === ['RESI_PROSES_01'];
        });

        @unlink($path);
    }

    public function test_chunk_job_skips_tracking_when_everything_is_already_finished(): void
    {
        Queue::fake();

        $path = $this->writeCsvFile();

        // only_empty: hanya resi tanpa status di file yang dilacak
        (new ImportSpreadsheetChunkJob($path, 'Worksheet', 'AGUSTUS', 2026, 2, 4, 'only_empty'))->handle();

        Queue::assertNotPushed(TrackShipmentChunkJob::class);

        @unlink($path);
    }

    public function test_needs_tracking_scope_skips_delivered_and_retur(): void
    {
        Shipment::create(['month' => 'AGUSTUS', 'year' => 2026, 'type' => 'keluar', 'resi' => 'A1', 'status' => 'DELIVERED']);
        Shipment::create(['month' => 'AGUSTUS', 'year' => 2026, 'type' => 'keluar', 'resi' => 'A2', 'status' => 'DELIVERED (RETURN DELIVERY)']);
        Shipment::create(['month' => 'AGUSTUS', 'year' => 2026, 'type' => 'masuk', 'resi' => 'A3', 'status' => 'ON PROCESS']);
        Shipment::create(['month' => 'AGUSTUS', 'year' => 2026, 'type' => 'keluar', 'resi' => 'A4', 'status' => 'ON PROCESS']);
        Shipment::create(['month' => 'AGUSTUS', 'year' => 2026, 'type' => 'keluar', 'resi' => 'A5', 'status' => null]);

        $this->assertEquals(['A4', 'A5'], Shipment::needsTracking()->pluck('resi')->sort()->values()->all());
    }

    public function test_tracking_job_does_nothing_for_finished_shipments(): void
    {
        Shipment::create(['month' => 'AGUSTUS', 'year' => 2026, 'type' => 'keluar', 'resi' => 'B1', 'status' => 'DELIVERED']);

        (new TrackShipmentChunkJob(['B1'], 2026))->handle(app(TrackingBotService::class));

        $this->assertNull(Shipment::where('resi', 'B1')->first()->last_scanned_at);
    }

    public function test_import_status_endpoint_returns_404_for_unknown_batch(): void
    {
        $this->getJson('/import-status/unknown-batch-id')->assertStatus(404);
    }
}
