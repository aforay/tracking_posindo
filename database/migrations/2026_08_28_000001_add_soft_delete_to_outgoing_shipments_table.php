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
        Schema::table('outgoing_shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('outgoing_shipments', 'deleted_at')) {
                $table->softDeletes()->after('last_tracked_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('outgoing_shipments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
