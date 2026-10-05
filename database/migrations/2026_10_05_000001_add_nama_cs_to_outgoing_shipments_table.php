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
        if (Schema::hasTable('outgoing_shipments') && !Schema::hasColumn('outgoing_shipments', 'nama_cs')) {
            Schema::table('outgoing_shipments', function (Blueprint $table) {
                $table->string('nama_cs', 100)->nullable()->after('nama_seller')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('outgoing_shipments') && Schema::hasColumn('outgoing_shipments', 'nama_cs')) {
            Schema::table('outgoing_shipments', function (Blueprint $table) {
                $table->dropIndex(['nama_cs']);
                $table->dropColumn('nama_cs');
            });
        }
    }
};
