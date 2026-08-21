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
        Schema::create('outgoing_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('nama_seller')->index();
            $table->string('no_resi')->unique();
            $table->string('nama_penerima')->nullable();
            $table->string('no_hp')->nullable();
            $table->text('alamat')->nullable();
            $table->date('tanggal_kirim')->nullable();
            $table->string('status_pos')->nullable();
            $table->text('keterangan')->nullable();
            $table->enum('status_kategori', ['SUKSES', 'RETUR', 'IN_PROCESS', 'FOLLOW_UP'])->default('IN_PROCESS')->index();
            $table->integer('sla_days')->nullable();
            $table->timestamp('last_tracked_at')->nullable();
            $table->timestamps();

            // Composite index for fast 40k+ count & filter queries
            $table->index(['nama_seller', 'status_kategori']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_shipments');
    }
};
