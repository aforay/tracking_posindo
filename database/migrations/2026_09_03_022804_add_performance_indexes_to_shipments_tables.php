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
                $table->index('tanggal_kirim');
                $table->index('status_pos');
                $table->index(['tanggal_kirim', 'status_kategori']);
                $table->index(['nama_seller', 'tanggal_kirim']);
                $table->index(['status_kategori', 'color_code']);
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (Schema::hasColumn('shipments', 'seller')) {
                    $table->index('seller');
                }
                $table->index('status');
                $table->index('color_code');
                $table->index('month');
                $table->index(['month', 'status']);
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
                $table->dropIndex(['tanggal_kirim']);
                $table->dropIndex(['status_pos']);
                $table->dropIndex(['tanggal_kirim', 'status_kategori']);
                $table->dropIndex(['nama_seller', 'tanggal_kirim']);
                $table->dropIndex(['status_kategori', 'color_code']);
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (Schema::hasColumn('shipments', 'seller')) {
                    $table->dropIndex(['seller']);
                }
                $table->dropIndex(['status']);
                $table->dropIndex(['color_code']);
                $table->dropIndex(['month']);
                $table->dropIndex(['month', 'status']);
            });
        }
    }
};
