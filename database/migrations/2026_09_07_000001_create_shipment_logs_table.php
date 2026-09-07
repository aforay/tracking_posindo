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
        if (!Schema::hasTable('shipment_logs')) {
            Schema::create('shipment_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shipment_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action'); // e.g. UPDATE_STATUS, UPDATE_COLOR, NOTED, ESCALATION, WHATSAPP, DELETE
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_logs');
    }
};
