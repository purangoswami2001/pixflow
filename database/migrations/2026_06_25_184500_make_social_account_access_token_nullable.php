<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('social_accounts', function (Blueprint $table) {
                $table->text('access_token')->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE social_accounts MODIFY access_token TEXT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('social_accounts', function (Blueprint $table) {
                $table->text('access_token')->nullable(false)->change();
            });

            return;
        }

        DB::statement('ALTER TABLE social_accounts MODIFY access_token TEXT NOT NULL');
    }
};
