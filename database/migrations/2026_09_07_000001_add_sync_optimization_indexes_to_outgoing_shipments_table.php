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
        if (Schema::hasTable('outgoing_shipments')) {
            Schema::table('outgoing_shipments', function (Blueprint $table) {
                // Composite & single indexes for fast sync filtering & stats queries
                $table->index(['last_tracked_at', 'status_kategori'], 'idx_last_tracked_status');
                $table->index(['nama_seller', 'tanggal_kirim', 'status_kategori'], 'idx_seller_tgl_status');
                $table->index('kantor_tujuan', 'idx_kantor_tujuan');
                $table->index('updated_at', 'idx_updated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('outgoing_shipments')) {
            Schema::table('outgoing_shipments', function (Blueprint $table) {
                $table->dropIndex('idx_last_tracked_status');
                $table->dropIndex('idx_seller_tgl_status');
                $table->dropIndex('idx_kantor_tujuan');
                $table->dropIndex('idx_updated_at');
            });
        }
    }
};
