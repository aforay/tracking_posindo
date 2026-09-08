<?php

namespace Tests\Feature;

use App\Models\OutgoingShipment;
use App\Models\ShipmentLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementAndLogsTest extends TestCase
{
    protected User $adminUser;
    protected User $csUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_test_mgr@posindo.com'],
            [
                'name' => 'Admin Manager',
                'password' => Hash::make('secretadmin123'),
                'role' => 'admin',
            ]
        );

        $this->csUser = User::firstOrCreate(
            ['email' => 'cs_test_mgr@posindo.com'],
            [
                'name' => 'CS Operator',
                'password' => Hash::make('secretcs123'),
                'role' => 'cs',
            ]
        );
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/users');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name', 'email', 'role', 'created_at'],
            ],
        ]);
    }

    public function test_cs_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->csUser)->getJson('/users');
        $response->assertStatus(403);

        $createResponse = $this->actingAs($this->csUser)->postJson('/users', [
            'name' => 'New User',
            'email' => 'new@posindo.com',
            'role' => 'cs',
            'password' => 'password123',
        ]);
        $createResponse->assertStatus(403);
    }

    public function test_admin_can_create_and_update_and_delete_user(): void
    {
        // 1. Create
        $createRes = $this->actingAs($this->adminUser)->postJson('/users', [
            'name' => 'Staf Baru',
            'email' => 'stafbaru@posindo.com',
            'role' => 'cs',
            'password' => 'password123',
        ]);
        $createRes->assertStatus(201);
        $createdId = $createRes->json('user.id');
        $this->assertDatabaseHas('users', ['email' => 'stafbaru@posindo.com']);

        // 2. Update
        $updateRes = $this->actingAs($this->adminUser)->putJson("/users/{$createdId}", [
            'name' => 'Staf Baru Diedit',
            'email' => 'stafbaru@posindo.com',
            'role' => 'admin',
            'password' => 'newsecret123',
        ]);
        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $createdId, 'name' => 'Staf Baru Diedit', 'role' => 'admin']);

        // 3. Prevent self-deletion
        $selfDelRes = $this->actingAs($this->adminUser)->deleteJson("/users/{$this->adminUser->id}");
        $selfDelRes->assertStatus(422);

        // 4. Delete created user
        $delRes = $this->actingAs($this->adminUser)->deleteJson("/users/{$createdId}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $createdId]);
    }

    public function test_user_can_update_own_password(): void
    {
        // Failed with wrong current password
        $failRes = $this->actingAs($this->csUser)->postJson('/profile/password', [
            'current_password' => 'wrongpass',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $failRes->assertStatus(422);

        // Successful password update
        $successRes = $this->actingAs($this->csUser)->postJson('/profile/password', [
            'current_password' => 'secretcs123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $successRes->assertStatus(200);
        $successRes->assertJson(['success' => true]);

        // Verify can login with new password
        $this->assertTrue(Hash::check('newpassword123', $this->csUser->fresh()->password));
    }

    public function test_can_fetch_shipment_logs(): void
    {
        $shipment = OutgoingShipment::create([
            'nama_seller' => 'Mitra Aliqa',
            'no_resi' => 'PCP0907LOG001ID',
            'nama_penerima' => 'Pelanggan Log',
            'no_hp' => '0899999999',
            'alamat' => 'Jl. Pengujian Log No 1',
            'status_pos' => 'ON PROCESS',
            'color_code' => 'PUTIH',
        ]);

        ShipmentLog::logAction($shipment->id, 'UPDATE_STATUS', 'Status diubah ke KUNING', $this->csUser->id);

        $res = $this->actingAs($this->csUser)->getJson("/shipments/{$shipment->id}/logs");
        $res->assertStatus(200);
        $res->assertJsonStructure([
            'success',
            'shipment' => ['id', 'no_resi', 'nama_penerima'],
            'logs' => [
                '*' => ['id', 'action', 'note', 'created_at', 'user'],
            ],
        ]);
        $this->assertCount(1, $res->json('logs'));
        $this->assertEquals('UPDATE_STATUS', $res->json('logs.0.action'));
    }
}
