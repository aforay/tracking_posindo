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
            if (!Schema::hasColumn('outgoing_shipments', 'color_code')) {
                $table->string('color_code', 30)->nullable()->index()->after('status_kategori');
            }
            if (!Schema::hasColumn('outgoing_shipments', 'fu_pos_date')) {
                $table->date('fu_pos_date')->nullable()->after('color_code');
            }
            if (!Schema::hasColumn('outgoing_shipments', 'noted')) {
                $table->text('noted')->nullable()->after('fu_pos_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_shipments', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('outgoing_shipments', 'color_code')) $columns[] = 'color_code';
            if (Schema::hasColumn('outgoing_shipments', 'fu_pos_date')) $columns[] = 'fu_pos_date';
            if (Schema::hasColumn('outgoing_shipments', 'noted')) $columns[] = 'noted';
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
