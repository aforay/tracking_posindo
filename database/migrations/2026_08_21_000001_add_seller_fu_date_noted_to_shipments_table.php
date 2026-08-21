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
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'seller')) {
                $table->string('seller', 100)->default('Mitra Aliqa')->after('nama_cs');
            }
            if (!Schema::hasColumn('shipments', 'fu_pos_date')) {
                $table->date('fu_pos_date')->nullable()->after('needs_follow_up');
            }
            if (!Schema::hasColumn('shipments', 'noted')) {
                $table->text('noted')->nullable()->after('fu_pos_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('shipments', 'seller')) $columns[] = 'seller';
            if (Schema::hasColumn('shipments', 'fu_pos_date')) $columns[] = 'fu_pos_date';
            if (Schema::hasColumn('shipments', 'noted')) $columns[] = 'noted';
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
