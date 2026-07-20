<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'ai_enabled')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->boolean('ai_enabled')->default(false)->after('slug');
            });
        }

        // Firms that already run automation clearly rely on AI — keep them working.
        DB::table('organizations')
            ->where('automation_enabled', true)
            ->update(['ai_enabled' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'ai_enabled')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('ai_enabled');
            });
        }
    }
};
