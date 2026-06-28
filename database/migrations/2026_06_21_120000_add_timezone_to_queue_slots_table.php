<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_slots', 'timezone')) {
                $table->string('timezone', 64)->default('UTC')->after('time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_slots', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
