<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_campaigns') && ! Schema::hasColumn('automation_campaigns', 'platform_image_presets')) {
            Schema::table('automation_campaigns', function (Blueprint $table) {
                $table->json('platform_image_presets')->nullable()->after('platform_counts');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('automation_campaigns', 'platform_image_presets')) {
            Schema::table('automation_campaigns', function (Blueprint $table) {
                $table->dropColumn('platform_image_presets');
            });
        }
    }
};
