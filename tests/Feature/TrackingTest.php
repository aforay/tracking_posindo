<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_page_renders_successfully_with_12_months(): void
    {
        $response = $this->get('/?month=AGUSTUS&year=2026');
        $response->assertStatus(200);
        $response->assertSee('Pos Indonesia Tracking');
        $response->assertSee('AGUSTUS');
        $response->assertSee('Januari');
        $response->assertSee('Desember');
    }

    public function test_mock_nipos_endpoint_renders_and_responds(): void
    {
        $response = $this->get('/mock-nipos/lacak_item_banyakzaref.php?cari_barcode=P2601020130943&ajax=1');
        $response->assertStatus(200);
        $response->assertSee('DELIVERED (RETURN DELIVERY)');
        $response->assertSee('zaherba fajar');
        $response->assertSee('P2601020130943');
    }

    public function test_store_new_shipment_inbound_and_outbound(): void
    {
        // 1. Store Outbound Shipment
        $responseOut = $this->post('/shipments', [
            'month' => 'SEPTEMBER',
            'year' => 2026,
            'type' => 'keluar',
            'resi' => 'BAC30072635B11B6E971',
            'nama_konsumen' => 'Budi Santoso',
            'no_hp' => '081234567890',
            'invoice' => 'SO_20260901001',
            'nama_cs' => 'CRM DILA',
            'produk' => 'LAMBUNG CERIA ZAHERBA',
            'jumlah_cod' => 'Rp 276.000 = COD',
            'alamat' => 'Jalan Merdeka No. 45, Bandung',
        ]);

        $responseOut->assertRedirect();
        $this->assertDatabaseHas('shipments', [
            'resi' => 'BAC30072635B11B6E971',
            'month' => 'SEPTEMBER',
            'type' => 'keluar',
        ]);

        // 2. Store Inbound Retur Shipment
        $responseIn = $this->post('/shipments', [
            'month' => 'SEPTEMBER',
            'year' => 2026,
            'type' => 'masuk',
            'resi' => 'BAC30072635B11B6E972',
            'nama_konsumen' => 'Sari Indah',
            'no_hp' => '081298765432',
            'nama_cs' => 'CRM NOVIYA',
            'keterangan' => 'BARANG RETUR DITERIMA DARI DC',
        ]);

        $responseIn->assertRedirect();
        $this->assertDatabaseHas('shipments', [
            'resi' => 'BAC30072635B11B6E972',
            'month' => 'SEPTEMBER',
            'type' => 'masuk',
            'color_code' => 'ORANGE',
        ]);
    }

    public function test_csv_spreadsheet_upload_processing(): void
    {
        $csvContent = "NO,TANGGAL,NAMA KONSUMEN,INVOICE,RESI,ALAMAT,NAMA CS,PRODUK,NO HP,JUMLAH COD,KETERANGAN,TRACKING POS,SLA\n";
        $csvContent .= "1,2026-08-01,Rina Wardani,INV001,P2601020133264,Jl Mawar No 1,CRM DILA,LAMBUNG CERIA,0812345678,250000,DITERIMA YANG BERSANGKUTAN,DELIVERED,2\n";

        $file = UploadedFile::fake()->createWithContent('shipments_test.csv', $csvContent);

        $response = $this->post('/process', [
            'excel_file' => $file,
            'month' => 'AGUSTUS',
            'year' => 2026,
            'filter_mode' => 'only_empty',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shipments', [
            'resi' => 'P2601020133264',
            'month' => 'AGUSTUS',
        ]);
    }

    public function test_update_shipment_color(): void
    {
        $shipment = Shipment::create([
            'month' => 'AGUSTUS',
            'year' => 2026,
            'type' => 'keluar',
            'resi' => 'TEST_RESI_COLOR_01',
            'color_code' => 'PUTIH',
        ]);

        $response = $this->postJson("/shipments/{$shipment->id}/color", [
            'color_code' => 'KUNING',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals('KUNING', $shipment->fresh()->color_code);
    }

    public function test_export_colored_excel_active_month_and_all_months(): void
    {
        Shipment::create([
            'month' => 'AGUSTUS',
            'year' => 2026,
            'type' => 'keluar',
            'resi' => 'TEST_RESI_EXPORT_01',
            'status' => 'DELIVERED',
            'color_code' => 'BIRU',
        ]);

        // Test export active month
        $response = $this->postJson('/export-colored-excel', [
            'month' => 'AGUSTUS',
            'year' => 2026,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Test export all 12 months
        $responseAll = $this->postJson('/export-colored-excel', [
            'month' => 'ALL',
            'year' => 2026,
        ]);

        $responseAll->assertStatus(200);
        $responseAll->assertJson(['success' => true]);
    }
}
