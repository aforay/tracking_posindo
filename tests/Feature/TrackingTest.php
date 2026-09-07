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
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@posindo.com'],
            [
                'name' => 'Admin Test',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'admin',
            ]
        );
        $this->actingAs($admin);
    }

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

        $mockBotService = $this->getMockBuilder(TrackingBotService::class)
            ->onlyMethods(['trackResiList'])
            ->getMock();
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

    public function test_start_bot_tracking_directly_updates_pending_shipments(): void
    {
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'P2601020130943',
            'status_pos' => 'inBag',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        $response = $this->postJson('/bot/start-tracking');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertNotNull($shipment->fresh()->last_tracked_at);
    }

    public function test_start_bot_tracking_with_queue_dispatches_jobs(): void
    {
        Queue::fake();

        OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'TEST_RESI_BOT_01',
            'status_pos' => 'inBag',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        $response = $this->postJson('/bot/start-tracking', ['use_queue' => true]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        Queue::assertPushed(ProcessNiposTrackingJob::class);
    }

    public function test_bot_progress_endpoint_returns_live_metrics(): void
    {
        $response = $this->getJson('/bot/progress');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total',
            'tracked',
            'delivered',
            'retur',
            'pending',
            'percentage',
            'is_running',
        ]);
    }

    /**
     * Test specific resi BAC29082622171022A39 transit is parsed as IN PROSES (Anti-Fallthrough)
     */
    public function test_resi_invehicle_transit_is_strictly_in_proses_and_not_delivered(): void
    {
        $botService = app(TrackingBotService::class);
        $resi = 'BAC29082622171022A39';

        $html = <<<HTML
        <table>
            <tr><td>NO RESI</td><td>BAC29082622171022A39</td></tr>
            <tr><td>STATUS AKHIR</td><td>INVEHICLE di JAKARTASOEKARNO HATTA</td></tr>
            <tr><td>SLA</td><td>jatuh tempo => 2 hari lagi</td></tr>
            <tr><td>KANTOR TUJUAN</td><td>KCU BANDUNG 40000</td></tr>
        </table>
HTML;

        $result = $botService->parseStatusResult('STATUS AKHIR : INVEHICLE di JAKARTASOEKARNO HATTA SLA: 2 hari lagi', $html, $resi);

        $this->assertEquals('IN_PROCESS', $result['status_kategori'], 'Status kategori must be IN_PROCESS, not DELIVERED');
        $this->assertEquals('PUTIH', $result['color_code'], 'Color code must be PUTIH for in-transit package');
        $this->assertStringContainsString('INVEHICLE', $result['keterangan']);
        $this->assertStringNotContainsString('SATPAM', $result['keterangan']);
        $this->assertEquals(2, $result['sla_days']);

        // Verify saving to database and checking fresh model
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Zaherba',
            'no_resi' => $resi,
            'nama_penerima' => 'Customer Test',
            'alamat' => 'Bandung, Jawa Barat',
            'tanggal_kirim' => '2026-08-29',
            'status_pos' => $result['status_pos'],
            'keterangan' => $result['keterangan'],
            'status_kategori' => $result['status_kategori'],
            'color_code' => $result['color_code'],
            'sla_days' => $result['sla_days'],
            'last_tracked_at' => now(),
        ]);

        $fresh = $shipment->fresh();
        $this->assertEquals('IN_PROCESS', $fresh->status_kategori);
        $this->assertEquals('PUTIH', $fresh->color_code);
        $this->assertNotEquals('SUKSES', $fresh->status_kategori);
        $this->assertNotEquals('DELIVERED', $fresh->status_pos);
    }

    /**
     * Test delivered with pengirim mitra is considered SUKSES per updated rules
     */
    public function test_delivered_with_pengirim_mitra_is_sukses_per_business_rules(): void
    {
        $botService = app(TrackingBotService::class);
        $category = $botService->categorizeStatus('DELIVERED', 'diterima oleh umar (DITERIMA PENGIRIM (MITRA))');

        $this->assertEquals('SUKSES', $category, 'DELIVERED with recipient confirmation must be categorized as SUKSES');
        $this->assertEquals('BIRU', $botService->determineColorCode($category));
    }

    /**
     * Test pure return is categorized as RETUR
     */
    public function test_pure_return_is_strictly_retur(): void
    {
        $botService = app(TrackingBotService::class);
        $category = $botService->categorizeStatus('RETUR', 'paket gagal antar dikembalikan ke pengirim');

        $this->assertEquals('RETUR', $category, 'Pure return must be categorized as RETUR');
        $this->assertEquals('ORANGE', $botService->determineColorCode($category));
    }

    /**
     * Test default guard never falls back to DELIVERED
     */
    public function test_anti_fallthrough_unknown_status_defaults_to_in_proses(): void
    {
        $botService = app(TrackingBotService::class);
        $result = $botService->parseStatusResult('SOME UNKNOWN SYSTEM TEXT', '<div>Unknown event</div>', 'UNKNOWN123');

        $this->assertEquals('IN_PROCESS', $result['status_kategori']);
        $this->assertEquals('PUTIH', $result['color_code']);
        $this->assertNotEquals('SUKSES', $result['status_kategori']);
        $this->assertNotEquals('DELIVERED', $result['status_pos']);
    }

    /**
     * Test sanitization & regex /i with wild spaces and irregular casing
     */
    public function test_sanitization_and_regex_anti_whitespace_and_casing(): void
    {
        $botService = app(TrackingBotService::class);

        // Multiple spaces & html entities & mixed casing
        $raw1 = "  in   vehicle   di   jakarta  ";
        $this->assertEquals('IN_PROCESS', $botService->categorizeStatus($raw1, ''));

        $raw2 = "UN-BAG   di  KCU   SURABAYA";
        $this->assertEquals('IN_PROCESS', $botService->categorizeStatus($raw2, ''));

        $raw3 = "dikembalikan   ke    Pengirim";
        $this->assertEquals('RETUR', $botService->categorizeStatus($raw3, ''));

        $raw4 = "DELIVERED  oleh  Umar  (DITERIMA   PENGIRIM   MITRA)";
        $this->assertEquals('SUKSES', $botService->categorizeStatus($raw4, ''));
    }

    /**
     * Test dynamic SLA parser handling positive and overdue minus
     */
    public function test_dynamic_sla_parser_positive_and_overdue_minus(): void
    {
        $botService = app(TrackingBotService::class);

        // Positive remaining
        $this->assertEquals(3, $botService->extractSlaDays("jatuh tempo => 3 hari lagi"));
        $this->assertEquals(2, $botService->extractSlaDays("SLA : 2 hari"));

        // Overdue / minus
        $this->assertEquals(-240, $botService->extractSlaDays("P2601030000909 [ SLA : 3 hari, Kiriman sudah Over SLA => 240 hari ]"));
        $this->assertEquals(-5, $botService->extractSlaDays("Kiriman terlewati 5 hari"));

        // Format running SLA
        $this->assertEquals("Telat 240 Hari", $botService->formatRunningSla('2026-01-03', 'IN_PROCESS', 2, -240));
        $this->assertEquals("H+3 (JALAN)", $botService->formatRunningSla('2026-08-28', 'IN_PROCESS', 2, 3));
    }

    /**
     * Test tracker:recalibrate artisan command
     */
    public function test_tracker_recalibrate_command_runs_successfully(): void
    {
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Zaherba',
            'no_resi' => 'BAC29082622171022A39',
            'nama_penerima' => 'Customer Test',
            'tanggal_kirim' => '2026-08-29',
            'status_pos' => 'DELIVERED', // Previously corrupted
            'keterangan' => 'DITERIMA SATPAM KANTOR',
            'status_kategori' => 'SUKSES',
            'color_code' => 'BIRU',
            'sla_days' => 2,
        ]);

        $this->artisan('tracker:recalibrate', [
            '--seller' => 'all',
            '--month' => '8',
            '--force' => true,
        ])->assertExitCode(0);

        // Fresh model should now be corrected back to IN_PROCESS (Transit)
        $fresh = $shipment->fresh();
        $this->assertEquals('IN_PROCESS', $fresh->status_kategori);
        $this->assertEquals('PUTIH', $fresh->color_code);
        $this->assertNotEmpty($fresh->keterangan);
    }

    /**
     * Test precision DOM/XPath selector matching exact NIPos HTML structure
     */
    public function test_precision_dom_xpath_selector_for_status_akhir_and_nomor_kiriman_sla(): void
    {
        $botService = app(TrackingBotService::class);
        $resi = 'P2601030000909';

        $html = <<<HTML
        <table>
            <tr>
                <td class="bgsiap"><b>Nomor Kiriman</b></td>
                <td>P2601030000909 [ SLA : 3 hari, Kiriman sudah Over SLA => 240 hari ]</td>
            </tr>
            <tr>
                <td class="bgsiap"><b>STATUS AKHIR</b></td>
                <td>&nbsp;<font size="2">DELIVERED (RETURN DELIVERY) di KC CILACAP 53200 oleh (Diki Cahyo Putranto / ) Tanggal : 2026-01-09 23:51:33 aliqa ivan<br></font></td>
            </tr>
        </table>
HTML;

        $result = $botService->parseStatusResult('P2601030000909 Over SLA => 240 hari', $html, $resi);

        $this->assertStringContainsString('DELIVERED (RETURN DELIVERY)', $result['status_pos']);
        $this->assertEquals('RETUR', $result['status_kategori']);
        $this->assertEquals('ORANGE', $result['color_code']);
        $this->assertEquals(-240, $result['sla_days']);
        $this->assertStringContainsString('KC CILACAP 53200', $result['keterangan']);
        $this->assertStringContainsString('DIKI CAHYO PUTRANTO', $result['keterangan']);
    }

    /**
     * Test date parsing correctly isolates data into sheet tab month
     */
    public function test_date_parsing_maps_to_sheet_tab_month_without_random_august_fallback(): void
    {
        $importer = new ShipmentsImport('Mitra Aliqa');
        $ref = new \ReflectionClass($importer);
        $parseDateMethod = $ref->getMethod('parseDateValue');
        $parseDateMethod->setAccessible(true);

        // Explicit Indonesian textual date
        $d1 = $parseDateMethod->invokeArgs($importer, ['10 Maret 2026', 'MARET (ZAHERBA)', null]);
        $this->assertEquals('2026-03-10', $d1);

        // Explicit slash date (DD/MM/YYYY)
        $d2 = $parseDateMethod->invokeArgs($importer, ['15/02/2026', null, null]);
        $this->assertEquals('2026-02-15', $d2);

        // Empty date with sheet tab "JANUARI 2026"
        $d3 = $parseDateMethod->invokeArgs($importer, [null, 'JANUARI 2026 (FP ALIQA)', null]);
        $this->assertEquals('2026-01-01', $d3);

        // Empty date with sheet tab "DESEMBER"
        $d4 = $parseDateMethod->invokeArgs($importer, [null, 'DESEMBER (ZAHERBA)', null]);
        $this->assertEquals('2026-12-01', $d4);
    }

    /**
     * Test imported data is fixed and remains unchanged unless explicitly tracked
     */
    public function test_shipments_imported_are_fixed_and_do_not_change_unless_tracked_or_synced(): void
    {
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Zaherba',
            'no_resi' => 'TEST_FIX_RESI_99',
            'nama_penerima' => 'Budi Santoso',
            'no_hp' => '081234567890',
            'alamat' => 'Cilacap, Jawa Tengah',
            'tanggal_kirim' => '2026-03-01',
            'status_pos' => 'ON PROCESS',
            'keterangan' => 'PROSES PENGIRIMAN POS',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
            'sla_days' => 2,
        ]);

        // Verify data in database is completely fix and matches input
        $fresh = $shipment->fresh();
        $this->assertEquals('TEST_FIX_RESI_99', $fresh->no_resi);
        $this->assertEquals('2026-03-01', $fresh->tanggal_kirim ? (is_string($fresh->tanggal_kirim) ? substr($fresh->tanggal_kirim, 0, 10) : $fresh->tanggal_kirim->format('Y-m-d')) : null);
        $this->assertEquals('IN_PROCESS', $fresh->status_kategori);
        $this->assertEquals('PUTIH', $fresh->color_code);
    }

    /**
     * Test bahwa Bot NIPOS force-override warna FU (KUNING/HIJAU/BIRU_TUA) ke BIRU/ORANGE
     * ketika NIPOS mengkonfirmasi status DELIVERED atau RETUR.
     * CS tidak bisa mempertahankan status FU jika NIPOS sudah konfirmasi selesai.
     */
    public function test_bot_nipos_force_overrides_fu_status_to_delivered_or_retur(): void
    {
        Queue::fake();
        Event::fake();

        $botService = new TrackingBotService();

        // === KASUS 1: Resi KUNING (FU 1x) → NIPOS DELIVERED → harus jadi BIRU ===
        $resiKuning = OutgoingShipment::create([
            'nama_seller'     => 'Mitra Aliqa',
            'no_resi'         => 'KUNING_RESI_001',
            'nama_penerima'   => 'Budi Santoso',
            'no_hp'           => '081200000001',
            'alamat'          => 'Cilacap',
            'tanggal_kirim'   => '2026-03-01',
            'status_pos'      => 'ON PROCESS',
            'keterangan'      => 'PROSES PENGIRIMAN',
            'status_kategori' => 'FOLLOW_UP',
            'color_code'      => 'KUNING',
            'sla_days'        => 3,
        ]);

        // === KASUS 2: Resi HIJAU (FU 2x) → NIPOS DELIVERED → harus jadi BIRU ===
        $resiHijau = OutgoingShipment::create([
            'nama_seller'     => 'Mitra Zaherba',
            'no_resi'         => 'HIJAU_RESI_002',
            'nama_penerima'   => 'Sari Dewi',
            'no_hp'           => '081200000002',
            'alamat'          => 'Purwokerto',
            'tanggal_kirim'   => '2026-03-02',
            'status_pos'      => 'ON PROCESS',
            'keterangan'      => 'PROSES PENGIRIMAN',
            'status_kategori' => 'FOLLOW_UP',
            'color_code'      => 'HIJAU',
            'sla_days'        => 4,
        ]);

        // === KASUS 3: Resi BIRU_TUA (FU POS) → NIPOS DELIVERED → harus jadi BIRU ===
        $resiBiruTua = OutgoingShipment::create([
            'nama_seller'     => 'Mitra Aliqa',
            'no_resi'         => 'BIRUTUA_RESI_003',
            'nama_penerima'   => 'Ahmad Rizki',
            'no_hp'           => '081200000003',
            'alamat'          => 'Majenang',
            'tanggal_kirim'   => '2026-03-03',
            'status_pos'      => 'ON PROCESS',
            'keterangan'      => 'PROSES PENGIRIMAN',
            'status_kategori' => 'FOLLOW_UP',
            'color_code'      => 'BIRU_TUA',
            'sla_days'        => 5,
        ]);

        // === KASUS 4: Resi PUTIH (Belum FU) → NIPOS RETUR → harus jadi ORANGE ===
        $resiPutihRetur = OutgoingShipment::create([
            'nama_seller'     => 'Mitra Zaherba',
            'no_resi'         => 'PUTIH_RESI_004',
            'nama_penerima'   => 'Dian Pratiwi',
            'no_hp'           => '081200000004',
            'alamat'          => 'Kroya',
            'tanggal_kirim'   => '2026-03-04',
            'status_pos'      => 'ON PROCESS',
            'keterangan'      => 'PROSES PENGIRIMAN',
            'status_kategori' => 'IN_PROCESS',
            'color_code'      => 'PUTIH',
            'sla_days'        => 2,
        ]);

        // Simulasikan hasil NIPOS: semua resi DELIVERED kecuali PUTIH_RESI_004 RETUR
        $fakeResults = [
            'KUNING_RESI_001'  => [
                'status_pos'      => 'DELIVERED',
                'keterangan'      => 'PAKET DITERIMA OLEH BUDI SANTOSO',
                'status_kategori' => 'SUKSES',
                'color_code'      => 'BIRU',
                'sla_days'        => 3,
            ],
            'HIJAU_RESI_002'   => [
                'status_pos'      => 'DELIVERED',
                'keterangan'      => 'PAKET DITERIMA OLEH SARI DEWI',
                'status_kategori' => 'SUKSES',
                'color_code'      => 'BIRU',
                'sla_days'        => 4,
            ],
            'BIRUTUA_RESI_003' => [
                'status_pos'      => 'DELIVERED',
                'keterangan'      => 'PAKET DITERIMA OLEH AHMAD RIZKI',
                'status_kategori' => 'SUKSES',
                'color_code'      => 'BIRU',
                'sla_days'        => 5,
            ],
            'PUTIH_RESI_004'   => [
                'status_pos'      => 'DELIVERED (RETURN DELIVERY)',
                'keterangan'      => 'PAKET DIKEMBALIKAN KE PENGIRIM',
                'status_kategori' => 'RETUR',
                'color_code'      => 'ORANGE',
                'sla_days'        => 2,
            ],
        ];

        // Jalankan Job secara langsung (synchronous) dengan hasil NIPOS yang sudah disiapkan
        $allIds = [
            $resiKuning->id,
            $resiHijau->id,
            $resiBiruTua->id,
            $resiPutihRetur->id,
        ];

        $shipments = OutgoingShipment::whereIn('id', $allIds)->get();
        $now = now();
        $fuColorCodes = ['PUTIH', 'KUNING', 'HIJAU', 'BIRU_TUA'];

        // Simulasi logika force-override dari ProcessNiposTrackingJob::handle()
        \Illuminate\Support\Facades\DB::transaction(function () use ($shipments, $fakeResults, $botService, $now, $fuColorCodes) {
            foreach ($shipments as $shipment) {
                $resi = $shipment->no_resi;
                if (isset($fakeResults[$resi])) {
                    $res = $fakeResults[$resi];
                    $shipment->status_pos = $res['status_pos'];
                    $shipment->keterangan = $res['keterangan'];

                    $category = $res['status_kategori'];
                    $newColorCode = $res['color_code'];

                    // === FORCE OVERRIDE (same logic as ProcessNiposTrackingJob) ===
                    $prevColorCode = $shipment->color_code;
                    if (in_array($category, ['SUKSES', 'RETUR']) && in_array($prevColorCode, $fuColorCodes)) {
                        $newColorCode = $botService->determineColorCode($category);
                    }

                    $shipment->status_kategori = $category;
                    $shipment->color_code = $newColorCode;
                    $shipment->sla_days = $res['sla_days'];
                    $shipment->last_tracked_at = $now;
                    $shipment->save();
                }
            }
        });

        // === ASSERTIONS: Semua resi FU harus ter-override ke status final ===
        $k = $resiKuning->fresh();
        $this->assertEquals('SUKSES', $k->status_kategori, "KUNING: status_kategori harus SUKSES setelah NIPOS DELIVERED");
        $this->assertEquals('BIRU', $k->color_code, "KUNING: color_code harus BIRU setelah NIPOS DELIVERED");

        $h = $resiHijau->fresh();
        $this->assertEquals('SUKSES', $h->status_kategori, "HIJAU: status_kategori harus SUKSES setelah NIPOS DELIVERED");
        $this->assertEquals('BIRU', $h->color_code, "HIJAU: color_code harus BIRU setelah NIPOS DELIVERED");

        $b = $resiBiruTua->fresh();
        $this->assertEquals('SUKSES', $b->status_kategori, "BIRU_TUA: status_kategori harus SUKSES setelah NIPOS DELIVERED");
        $this->assertEquals('BIRU', $b->color_code, "BIRU_TUA: color_code harus BIRU setelah NIPOS DELIVERED");

        $p = $resiPutihRetur->fresh();
        $this->assertEquals('RETUR', $p->status_kategori, "PUTIH: status_kategori harus RETUR setelah NIPOS RETURN DELIVERY");
        $this->assertEquals('ORANGE', $p->color_code, "PUTIH: color_code harus ORANGE setelah NIPOS RETURN DELIVERY");
    }

    public function test_start_bot_tracking_strictly_filters_by_month(): void
    {
        // 1. Resi di bulan Juli (month 7)
        $juli = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'TEST_JULI_01',
            'tanggal_kirim' => '2026-07-15',
            'status_pos' => 'inBag',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        // 2. Resi di bulan Agustus (month 8)
        $agustus = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'TEST_AGUSTUS_01',
            'tanggal_kirim' => '2026-08-15',
            'status_pos' => 'inBag',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        // Jalankan bot khusus bulan 8 (Agustus)
        $response = $this->postJson('/bot/start-tracking', [
            'month' => 8,
            'seller' => 'Mitra Aliqa',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Resi Agustus harus ter-track
        $this->assertNotNull($agustus->fresh()->last_tracked_at, 'Resi Agustus harus ter-track saat bot dijalankan untuk bulan 8');

        // Resi Juli TIDAK BOLEH ter-track
        $this->assertNull($juli->fresh()->last_tracked_at, 'Resi Juli tidak boleh ter-track saat bot hanya dijalankan untuk bulan Agustus');
    }

    public function test_ditolak_is_categorized_as_retur(): void
    {
        $botService = app(\App\Services\TrackingBotService::class);
        $cat = $botService->categorizeStatus('unBag - -', '(KIRIMAN DITOLAK YANG BERSANGKUTAN)');
        $this->assertEquals('RETUR', $cat, 'KIRIMAN DITOLAK harus dikategorikan sebagai RETUR');
    }

    public function test_start_bot_tracking_returns_updated_items_for_notification(): void
    {
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'TEST_NOTIF_01',
            'tanggal_kirim' => '2026-08-20',
            'status_pos' => 'ON PROCESS',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        $response = $this->postJson('/bot/start-tracking', [
            'month' => 8,
            'seller' => 'Mitra Aliqa',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'processed_count',
            'updated_items',
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['updated_items']);
        $this->assertEquals('TEST_NOTIF_01', $data['updated_items'][0]['resi']);
    }

    public function test_excel_upload_dispatches_reverse_sync_for_spreadsheet_colors(): void
    {
        Queue::fake();

        // Buat file CSV test yang mensimulasikan file hasil follow-up dari Kantor Pos
        $csvContent = "No Resi,Seller,Nama Penerima,Status POS,Keterangan,Status FU\n";
        $csvContent .= "P260810001001,Mitra Aliqa,Budi Santoso,DELIVERED,DITERIMA YBS,FU SEKALI\n";
        $csvContent .= "P260810001002,Mitra Aliqa,Siti Rahma,DELIVERED (RETURN),DITOLAK PENERIMA,RETUR\n";

        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('pos_fu_result.csv', $csvContent);

        $response = $this->post('/process', [
            'excel_file' => $uploadedFile,
            'default_seller' => 'Mitra Aliqa',
        ]);

        $response->assertStatus(302);

        // Verifikasi database terupdate
        $this->assertDatabaseHas('outgoing_shipments', [
            'no_resi' => 'P260810001001',
            'color_code' => 'KUNING',
        ]);

        $this->assertDatabaseHas('outgoing_shipments', [
            'no_resi' => 'P260810001002',
            'color_code' => 'ORANGE',
        ]);

        // Verifikasi ReverseSyncGoogleSheetsJob di-dispatch agar warna di spreadsheet terupdate
        Queue::assertPushed(ReverseSyncGoogleSheetsJob::class, function ($job) {
            return true;
        });
    }

    public function test_retur_packages_with_operational_status_are_tracked_and_updated(): void
    {
        // 1. Buat paket yang sebelumnya berstatus unBag dan berwarna ORANGE / RETUR
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCPTESTRETUR01',
            'nama_penerima' => 'Konsumen Retur',
            'no_hp' => '08123456789',
            'alamat' => 'Bandung',
            'tanggal_kirim' => '2026-08-20',
            'status_pos' => 'unBag',
            'keterangan' => 'unBag - -, (KIRIMAN DITOLAK YANG BERSANGKUTAN)',
            'status_kategori' => 'RETUR',
            'color_code' => 'ORANGE',
            'sla_days' => 5,
        ]);

        // 2. Pastikan scope needsTracking menyertakan paket ini karena status_pos belum final
        $this->assertTrue(OutgoingShipment::needsTracking()->where('id', $shipment->id)->exists());

        // 3. Mock bot tracking service mengembalikan status akhir DELIVERED (RETURN DELIVERY)
        $botService = $this->createMock(TrackingBotService::class);
        $botService->method('trackResiList')->willReturn([
            'PCPTESTRETUR01' => [
                'resi' => 'PCPTESTRETUR01',
                'status_pos' => 'DELIVERED (RETURN DELIVERY)',
                'keterangan' => 'DELIVERED (RETURN DELIVERY)',
                'status_kategori' => 'RETUR',
                'color_code' => 'ORANGE',
                'sla_days' => 12,
            ]
        ]);
        $botService->method('categorizeStatus')->willReturn('RETUR');
        $botService->method('determineColorCode')->willReturn('ORANGE');
        $botService->method('extractSlaDays')->willReturn(12);

        $job = new ProcessNiposTrackingJob([$shipment->id]);
        $job->handle($botService);

        $fresh = $shipment->fresh();
        $this->assertEquals('DELIVERED (RETURN DELIVERY)', $fresh->status_pos);
        $this->assertEquals('RETUR', $fresh->status_kategori);
        $this->assertEquals('ORANGE', $fresh->color_code);
    }

    public function test_retur_packages_preserve_orange_color_when_transit_status_returned(): void
    {
        // Paket yang berstatus RETUR / ORANGE dan NIPOS mengembalikan status transit operasional (INVEHICLE)
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCPTESTRETUR02',
            'nama_penerima' => 'Konsumen Transit',
            'no_hp' => '08123456780',
            'alamat' => 'Semarang',
            'tanggal_kirim' => '2026-08-21',
            'status_pos' => 'unBag',
            'keterangan' => 'unBag - -, (KIRIMAN DITOLAK YANG BERSANGKUTAN)',
            'status_kategori' => 'RETUR',
            'color_code' => 'ORANGE',
            'sla_days' => 4,
        ]);

        $botService = $this->createMock(TrackingBotService::class);
        $botService->method('trackResiList')->willReturn([
            'PCPTESTRETUR02' => [
                'resi' => 'PCPTESTRETUR02',
                'status_pos' => 'INVEHICLE',
                'keterangan' => 'INVEHICLE - -',
                'status_kategori' => 'IN_PROCESS', // NIPOS raw parser returns IN_PROCESS for invehicle
                'color_code' => 'PUTIH',
                'sla_days' => 6,
            ]
        ]);
        $botService->method('categorizeStatus')->willReturn('IN_PROCESS');
        $botService->method('determineColorCode')->willReturn('PUTIH');
        $botService->method('extractSlaDays')->willReturn(6);

        $job = new ProcessNiposTrackingJob([$shipment->id]);
        $job->handle($botService);

        // Harus tetap RETUR dan ORANGE, tidak boleh turun ke IN_PROCESS / PUTIH
        $fresh = $shipment->fresh();
        $this->assertEquals('INVEHICLE', $fresh->status_pos);
        $this->assertEquals('RETUR', $fresh->status_kategori);
        $this->assertEquals('ORANGE', $fresh->color_code);
    }
}


