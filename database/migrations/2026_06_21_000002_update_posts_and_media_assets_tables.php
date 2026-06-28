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
        Schema::table('posts', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('metadata');
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->json('channels')->nullable()->after('metadata'); // e.g. ["facebook", "pinterest"]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('admin_note');
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn('channels');
        });
    }
};
