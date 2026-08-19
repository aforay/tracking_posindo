<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;

class TrackingTest extends TestCase
{
    public function test_tracking_page_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Pos Indonesia Tracking Automation');
        $response->assertSee('AGUSTUS (ZAHERBA)');
    }

    public function test_mock_nipos_endpoint_renders_and_responds(): void
    {
        $response = $this->get('/mock-nipos/lacak_item_banyakzaref.php?cari_barcode=P2601020133264&ajax=1');
        $response->assertStatus(200);
        $response->assertSee('DELIVERED');
        $response->assertSee('P2601020133264');
    }

    public function test_process_tracking_with_dummy_file(): void
    {
        $response = $this->post('/process', [
            'use_dummy' => 1,
            'target_url' => url('/mock-nipos/lacak_item_banyakzaref.php'),
        ]);

        $response->assertStatus(200);
        $response->assertSee('Tracking berhasil diselesaikan');
        $response->assertSee('P2601020133264');
        $response->assertSee('DELIVERED');
    }
}
