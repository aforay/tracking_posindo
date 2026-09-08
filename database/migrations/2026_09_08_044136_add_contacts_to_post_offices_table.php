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
        Schema::table('post_offices', function (Blueprint $table) {
            $table->string('phone_wa', 30)->nullable()->change();
            $table->string('phone_wa_2', 30)->nullable()->after('phone_wa');
            $table->string('telegram_handle', 100)->nullable()->after('phone_wa_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_offices', function (Blueprint $table) {
            $table->dropColumn(['phone_wa_2', 'telegram_handle']);
        });
    }
};
