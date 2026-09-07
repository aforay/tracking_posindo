<?php

namespace Tests\Feature;

use App\Models\OutgoingShipment;
use App\Models\ShipmentLog;
use Tests\TestCase;

class ShipmentLogAndOverdueTest extends TestCase
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

    public function test_overdue_filter_correctly_filters_stuck_shipments(): void
    {
        $stuckShipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP08011234567ID',
            'nama_penerima' => 'Budi Santoso',
            'no_hp' => '08123456780',
            'alamat' => 'Bandung',
            'tanggal_kirim' => now()->subDays(6)->toDateString(),
            'status_pos' => 'IN TRANSIT',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        $deliveredShipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP08021234568ID',
            'nama_penerima' => 'Siti Nurhaliza',
            'no_hp' => '08123456781',
            'alamat' => 'Surabaya',
            'tanggal_kirim' => now()->subDays(6)->toDateString(),
            'status_pos' => 'DELIVERED',
            'status_kategori' => 'SUKSES',
            'color_code' => 'BIRU',
        ]);

        $recentShipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP08031234569ID',
            'nama_penerima' => 'Dewi Lestari',
            'no_hp' => '08123456782',
            'alamat' => 'Yogyakarta',
            'tanggal_kirim' => now()->subDays(2)->toDateString(),
            'status_pos' => 'IN TRANSIT',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        $response = $this->get('/shipments?seller=Mitra+Aliqa&month=ALL&overdue=1');
        $response->assertStatus(200);

        $inertiaProps = $response->viewData('page')['props'];
        $items = $inertiaProps['shipments']['data'];

        $resis = collect($items)->pluck('resi')->toArray();
        $this->assertContains('PCP08011234567ID', $resis);
        $this->assertNotContains('PCP08021234568ID', $resis);
        $this->assertNotContains('PCP08031234569ID', $resis);

        $this->assertGreaterThanOrEqual(1, $inertiaProps['stats']['overdue']);
    }

    public function test_shipment_logs_created_on_status_update(): void
    {
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP08041234570ID',
            'nama_penerima' => 'Eko Prasetyo',
            'no_hp' => '08123456783',
            'alamat' => 'Semarang',
            'tanggal_kirim' => now()->subDays(3)->toDateString(),
            'status_pos' => 'IN TRANSIT',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        $response = $this->postJson("/shipments/{$shipment->id}/color", [
            'color_code' => 'BIRU_TUA',
            'noted' => 'CS koordinasi ke KC Semarang',
            'fu_pos_date' => now()->toDateString(),
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('shipment_logs', [
            'shipment_id' => $shipment->id,
            'action' => 'NOTED',
        ]);

        $log = ShipmentLog::where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('BIRU_TUA', $log->note);
        $this->assertStringContainsString('CS koordinasi ke KC Semarang', $log->note);

        $this->assertCount(1, $shipment->fresh()->logs);
    }
}