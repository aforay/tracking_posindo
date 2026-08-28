<?php

namespace Tests\Feature;

use App\Events\NiposTrackingUpdatedEvent;
use App\Imports\ShipmentsImport;
use App\Jobs\ProcessNiposTrackingJob;
use App\Jobs\ReverseSyncGoogleSheetsJob;
use App\Models\OutgoingShipment;
use App\Models\SystemSetting;
use App\Services\GoogleSheetsSyncService;
use App\Services\TrackingBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_renders_successfully(): void
    {
        $response = $this->get('/?month=AGUSTUS&year=2026');
        $response->assertStatus(200);
    }

    public function test_mock_nipos_endpoint_renders_and_responds(): void
    {
        $response = $this->get('/mock-nipos/lacak_item_banyakzaref.php?cari_barcode=P2601020130943&ajax=1');
        $response->assertStatus(200);
        $response->assertSee('DELIVERED (RETURN DELIVERY)');
        $response->assertSee('zaherba fajar');
        $response->assertSee('P2601020130943');
    }

    public function test_nipos_status_is_primary_source_of_truth_on_sheet_sync(): void
    {
        // 1. Create shipment that has been tracked via NIPos
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP260800001ID',
            'nama_penerima' => 'Ahmad Fauzi',
            'no_hp' => '08123456789',
            'alamat' => 'Jakarta Selatan',
            'tanggal_kirim' => '2026-08-15',
            'status_pos' => 'DELIVERED',
            'keterangan' => 'DITERIMA YANG BERSANGKUTAN',
            'status_kategori' => 'SUKSES',
            'color_code' => 'BIRU',
            'sla_days' => 2,
            'last_tracked_at' => now(),
        ]);

        // 2. Simulate re-syncing from Google Sheets where the sheet row still has older status 'inBag'
        $importer = new ShipmentsImport('Mitra Aliqa');
        $reflection = new \ReflectionClass($importer);
        $processMethod = $reflection->getMethod('processBufferBatch');
        $processMethod->setAccessible(true);

        $sheetRowData = [
            [
                'nama_seller' => 'Mitra Aliqa',
                'no_resi' => 'PCP260800001ID',
                'nama_penerima' => 'Ahmad Fauzi (Updated Address)',
                'no_hp' => '08123456789',
                'alamat' => 'Jakarta Selatan Baru',
                'tanggal_kirim' => '2026-08-15',
                'status_pos' => 'inBag', // Older status from Google Sheets
                'keterangan' => 'DALAM KANTONG POS',
                'status_kategori' => 'IN_PROCESS',
                'color_code' => 'PUTIH',
                'sla_days' => 2,
            ]
        ];

        $processMethod->invokeArgs($importer, [$sheetRowData, ['PCP260800001ID']]);

        // 3. Verify that NIPOS status DELIVERED & SUKSES & BIRU are PRESERVED as Primary Source of Truth
        $fresh = $shipment->fresh();
        $this->assertEquals('DELIVERED', $fresh->status_pos);
        $this->assertEquals('DITERIMA YANG BERSANGKUTAN', $fresh->keterangan);
        $this->assertEquals('SUKSES', $fresh->status_kategori);
        $this->assertEquals('BIRU', $fresh->color_code);
        // But metadata like updated recipient address from sheet gets updated:
        $this->assertEquals('Ahmad Fauzi (Updated Address)', $fresh->nama_penerima);
    }

    public function test_dashboard_displays_nipos_status_when_different_from_sheet(): void
    {
        OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP260800002ID',
            'nama_penerima' => 'Siti Nurhaliza',
            'no_hp' => '08129876543',
            'alamat' => 'Bandung',
            'tanggal_kirim' => '2026-08-15',
            'status_pos' => 'DELIVERED',
            'keterangan' => 'DITERIMA YANG BERSANGKUTAN',
            'status_kategori' => 'SUKSES',
            'color_code' => 'BIRU',
            'sla_days' => 2,
            'last_tracked_at' => now(),
        ]);

        $response = $this->get('/?search=PCP260800002ID');
        $response->assertStatus(200);
        $response->assertSee('DELIVERED');
        $response->assertSee('DITERIMA YANG BERSANGKUTAN');
        $response->assertSee('BIRU');
    }

    public function test_process_nipos_tracking_dispatches_reverse_sync_job_and_event(): void
    {
        Queue::fake();
        Event::fake();

        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'P2601020130943',
            'nama_penerima' => 'Zaherba Fajar',
            'no_hp' => '08123456789',
            'alamat' => 'Cilacap',
            'tanggal_kirim' => '2026-08-15',
            'status_pos' => 'inBag',
            'keterangan' => 'DALAM KANTONG',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
            'sla_days' => 2,
        ]);

        $mockBotService = $this->createMock(TrackingBotService::class);
        $mockBotService->method('trackResiList')->willReturn([
            'P2601020130943' => [
                'resi' => 'P2601020130943',
                'status_pos' => 'DELIVERED (RETURN DELIVERY)',
                'status' => 'DELIVERED (RETURN DELIVERY)',
                'keterangan' => 'zaherba fajar, (DITERIMA PENGIRIM (MITRA))',
                'status_kategori' => 'RETUR',
                'sla_days' => 9,
            ]
        ]);

        $job = new ProcessNiposTrackingJob([$shipment->id]);
        $job->handle($mockBotService);

        // Assert that ReverseSyncGoogleSheetsJob was dispatched to background queue worker
        Queue::assertPushed(ReverseSyncGoogleSheetsJob::class, function ($job) {
            $ref = new \ReflectionClass($job);
            $prop = $ref->getProperty('trackingItems');
            $prop->setAccessible(true);
            $items = $prop->getValue($job);

            return count($items) > 0 && ($items[0]['status_pos'] ?? null) === 'DELIVERED (RETURN DELIVERY)';
        });

        // Assert that event was also dispatched
        Event::assertDispatched(NiposTrackingUpdatedEvent::class);
    }

    public function test_reverse_sync_google_sheets_service_returns_structured_metrics(): void
    {
        SystemSetting::set('google_sheet_webhook_url', '');

        $syncService = app(GoogleSheetsSyncService::class);
        $result = $syncService->reverseSyncNiposTracking([
            [
                'resi' => 'PCP260800001ID',
                'status_pos' => 'DELIVERED',
                'keterangan' => 'DITERIMA YANG BERSANGKUTAN',
                'status_kategori' => 'SUKSES',
                'color_code' => 'BIRU',
                'sla_days' => 2,
            ]
        ], '');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['items_count']);
        $this->assertArrayHasKey('webhook_sent', $result);
        $this->assertArrayHasKey('spreadsheet_id', $result);
    }

    public function test_update_color_endpoint_updates_database_and_dispatches_sheet_job(): void
    {
        Queue::fake();

        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'TEST_RESI_COLOR_02',
            'status_pos' => 'ON PROCESS',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        $response = $this->postJson("/shipments/{$shipment->id}/color", [
            'color_code' => 'KUNING',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals('KUNING', $shipment->fresh()->color_code);
    }
}

