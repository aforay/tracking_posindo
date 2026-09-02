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
                if (!Schema::hasColumn('outgoing_shipments', 'kantor_tujuan')) {
                    $table->string('kantor_tujuan')->nullable()->after('alamat');
                }
                if (!Schema::hasColumn('outgoing_shipments', 'last_location')) {
                    $table->string('last_location')->nullable()->after('kantor_tujuan');
                }
                if (!Schema::hasColumn('outgoing_shipments', 'kantor_pos_id')) {
                    $table->unsignedBigInteger('kantor_pos_id')->nullable()->after('last_location');
                }
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (!Schema::hasColumn('shipments', 'kantor_tujuan')) {
                    $table->string('kantor_tujuan')->nullable()->after('alamat');
                }
                if (!Schema::hasColumn('shipments', 'last_location')) {
                    $table->string('last_location')->nullable()->after('kantor_tujuan');
                }
                if (!Schema::hasColumn('shipments', 'kantor_pos_id')) {
                    $table->unsignedBigInteger('kantor_pos_id')->nullable()->after('last_location');
                }
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
                $table->dropColumn(['kantor_tujuan', 'last_location', 'kantor_pos_id']);
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn(['kantor_tujuan', 'last_location', 'kantor_pos_id']);
            });
        }
    }
};
