<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('month', 20)->default('AGUSTUS'); // JANUARI - DESEMBER
            $table->integer('year')->default(2026);
            $table->enum('type', ['keluar', 'masuk'])->default('keluar'); // keluar = pengiriman baru, masuk = retur / transit
            $table->integer('row_index')->nullable();
            $table->string('tanggal')->nullable();
            $table->string('nama_konsumen')->nullable();
            $table->string('invoice')->nullable();
            $table->string('resi')->index();
            $table->text('alamat')->nullable();
            $table->string('nama_cs')->nullable();
            $table->string('produk')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('jumlah_cod')->nullable();
            $table->string('keterangan')->nullable(); // Kolom K
            $table->string('status')->nullable();     // Kolom L
            $table->string('sla')->nullable();        // Kolom M
            $table->string('color_code', 20)->default('PUTIH'); // BIRU, ORANGE, KUNING, PUTIH, HIJAU, BIRU_TUA
            $table->boolean('needs_follow_up')->default(false);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
