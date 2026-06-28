<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure standard roles exist
        \Spatie\Permission\Models\Role::findOrCreate('admin');
        \Spatie\Permission\Models\Role::findOrCreate('creator');

        // Create Admin User
        $admin = User::updateOrCreate([
            'email' => 'admin@pixflow.com',
        ], [
            'name' => 'System Administrator',
            'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
        ]);
        $admin->assignRole('admin');

        $this->call(AutomationSeeder::class);
    }
}
