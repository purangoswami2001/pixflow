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
            if (! Schema::hasColumn('queue_slots', 'posts_per_slot')) {
                $table->unsignedTinyInteger('posts_per_slot')->default(1)->after('timezone');
            }
        });

        Schema::table('post_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('post_targets', 'schedule_mode')) {
                $table->string('schedule_mode')->default('auto')->after('timezone');
            }
            if (! Schema::hasColumn('post_targets', 'queue_slot_id')) {
                $table->foreignId('queue_slot_id')->nullable()->after('schedule_mode')
                    ->constrained('queue_slots')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            if (Schema::hasColumn('post_targets', 'queue_slot_id')) {
                $table->dropForeign(['queue_slot_id']);
                $table->dropColumn('queue_slot_id');
            }
            if (Schema::hasColumn('post_targets', 'schedule_mode')) {
                $table->dropColumn('schedule_mode');
            }
        });

        Schema::table('queue_slots', function (Blueprint $table) {
            if (Schema::hasColumn('queue_slots', 'posts_per_slot')) {
                $table->dropColumn('posts_per_slot');
            }
        });
    }
};
