<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE social_accounts MODIFY access_token TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE social_accounts MODIFY access_token TEXT NOT NULL');
    }
};
