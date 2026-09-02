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
        Schema::create('post_offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->index(); // e.g. 40000, 10000
            $table->string('name')->index(); // e.g. KC BANDUNG 40000, KC JAKARTA PUSAT
            $table->string('city')->nullable()->index(); // e.g. Bandung, Jakarta Pusat
            $table->string('province')->nullable(); // e.g. Jawa Barat, DKI Jakarta
            $table->string('phone_wa', 30); // WhatsApp Phone Number e.g. 6281234567890
            $table->string('pic_name')->nullable(); // e.g. CS Antaran / Pak Ahmad
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_offices');
    }
};
