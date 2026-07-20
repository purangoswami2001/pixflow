<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('creator');
        Role::findOrCreate('super-admin');

        $admin = User::updateOrCreate([
            'email' => 'admin@pixflow.com',
        ], [
            'name' => 'System Administrator',
            'password' => Hash::make('admin123'),
            'email_verified_at' => now(),
        ]);

        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'pixflow'],
            ['name' => 'PixFlow'],
        );

        app(OrganizationService::class)->attachMember(
            $organization,
            $admin,
            OrganizationMembership::ROLE_OWNER,
        );

        $admin->forceFill(['current_organization_id' => $organization->id])->save();

        // Keep Spatie admin for backward-compatible tooling; tenant auth uses memberships.
        $admin->assignRole('admin');

        // Automation defaults are tenant data and are installed only when this
        // organization explicitly turns automation on.
    }
}
