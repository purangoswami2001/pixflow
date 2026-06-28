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
        Schema::create('queue_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_destination_id')->constrained()->cascadeOnDelete();
            $table->integer('day_of_week'); // 0 = Sunday, 1 = Monday, etc.
            $table->time('time'); // e.g. "09:00:00"
            $table->timestamps();

            $table->unique(['social_destination_id', 'day_of_week', 'time'], 'uniq_dest_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_slots');
    }
};
