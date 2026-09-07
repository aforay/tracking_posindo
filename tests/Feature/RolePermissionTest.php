<?php

namespace Tests\Feature;

use App\Models\OutgoingShipment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    protected User $adminUser;
    protected User $csUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@posindo.com'],
            [
                'name' => 'Admin Test',
                'password' => Hash::make('AADDMMIINN123'),
                'role' => 'admin',
            ]
        );

        $this->csUser = User::firstOrCreate(
            ['email' => 'cs@posindo.com'],
            [
                'name' => 'CS Test',
                'password' => Hash::make('OKEEECS'),
                'role' => 'cs',
            ]
        );
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        // Web request without auth should redirect to /login
        $webResponse = $this->get('/shipments');
        $webResponse->assertRedirect('/login');

        // JSON / API request without auth should return 401
        $jsonResponse = $this->postJson('/shipments/update-status', [
            'ids' => [1],
            'fu' => 'BIRU',
        ]);
        $jsonResponse->assertStatus(401);
    }

    public function test_login_and_logout_flow(): void
    {
        // Failed login
        $failResponse = $this->post('/login', [
            'email' => 'admin_test@posindo.com',
            'password' => 'wrongpassword',
        ]);
        $failResponse->assertSessionHasErrors('email');
        $this->assertGuest();

        // Successful login
        $successResponse = $this->post('/login', [
            'email' => 'admin_test@posindo.com',
            'password' => 'secret123',
        ]);
        $successResponse->assertRedirect('/');
        $this->assertAuthenticatedAs($this->adminUser);

        // Logout
        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_cs_user_can_access_dashboard_and_follow_up(): void
    {
        $this->actingAs($this->csUser);

        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP0901CS001ID',
            'nama_penerima' => 'Pelanggan CS',
            'no_hp' => '08123456789',
            'alamat' => 'Jakarta',
            'tanggal_kirim' => now()->toDateString(),
            'status_pos' => 'IN TRANSIT',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        // CS can view dashboard
        $response = $this->get('/shipments');
        $response->assertStatus(200);

        // CS can update follow up color & note
        $updateResponse = $this->postJson("/shipments/{$shipment->id}/color", [
            'color_code' => 'KUNING',
            'noted' => 'Sudah ditelepon pembeli',
        ]);
        $updateResponse->assertStatus(200);
        $this->assertEquals('KUNING', $shipment->fresh()->color_code);

        // CS can access bot progress
        $botResponse = $this->get('/bot/progress');
        $botResponse->assertStatus(200);
    }

    public function test_cs_user_is_forbidden_from_admin_only_routes(): void
    {
        $this->actingAs($this->csUser);

        // Settings Google Sheets
        $sheetResponse = $this->post('/settings/google-sheets', [
            'google_sheet_url_aliqa' => 'https://example.com',
        ]);
        $sheetResponse->assertStatus(403);

        // Import
        $importResponse = $this->post('/shipments/import');
        $importResponse->assertStatus(403);

        // NIPOS Cookie Settings
        $cookieResponse = $this->get('/settings/nipos-cookie');
        $cookieResponse->assertStatus(403);

        // Export data
        $exportResponse = $this->post('/export-colored-excel');
        $exportResponse->assertStatus(403);
    }

    public function test_cs_user_cannot_delete_resi_via_bulk_action_but_can_update_status(): void
    {
        $this->actingAs($this->csUser);

        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP0901CS002ID',
            'nama_penerima' => 'Pelanggan Aman',
            'no_hp' => '08123456789',
            'alamat' => 'Jakarta',
            'tanggal_kirim' => now()->toDateString(),
            'status_pos' => 'IN TRANSIT',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        // CS attempting DELETE action must be blocked with 403
        $deleteResponse = $this->postJson('/shipments/bulk-action', [
            'ids' => [$shipment->id],
            'action' => 'DELETE',
        ]);
        $deleteResponse->assertStatus(403);
        $this->assertDatabaseHas('outgoing_shipments', ['id' => $shipment->id]);

        // CS updating status to BIRU via bulk action must succeed
        $statusResponse = $this->post('/shipments/bulk-action', [
            'ids' => [$shipment->id],
            'action' => 'BIRU',
        ]);
        $statusResponse->assertStatus(302);
        $this->assertEquals('BIRU', $shipment->fresh()->color_code);
    }

    public function test_admin_can_perform_delete_and_access_admin_routes(): void
    {
        $this->actingAs($this->adminUser);

        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP0901ADM001ID',
            'nama_penerima' => 'Pelanggan Hapus',
            'no_hp' => '08123456789',
            'alamat' => 'Jakarta',
            'tanggal_kirim' => now()->toDateString(),
            'status_pos' => 'IN TRANSIT',
            'status_kategori' => 'IN_PROCESS',
            'color_code' => 'PUTIH',
        ]);

        // Admin can delete resi
        $deleteResponse = $this->post('/shipments/bulk-action', [
            'ids' => [$shipment->id],
            'action' => 'DELETE',
        ]);
        $deleteResponse->assertStatus(302);
        $this->assertDatabaseMissing('outgoing_shipments', ['id' => $shipment->id]);

        // Admin can access NIPOS cookie settings
        $cookieResponse = $this->get('/settings/nipos-cookie');
        $cookieResponse->assertStatus(200);
    }
}
