<?php

namespace App\Services\Automation;

use App\Models\Organization;
use Database\Seeders\AutomationSeeder;
use Database\Seeders\OrganizationSampleContentSeeder;
use Illuminate\Support\Facades\DB;

class AutomationInitializer
{
    public function initialize(Organization $organization): Organization
    {
        return DB::transaction(function () use ($organization) {
            app(AutomationSeeder::class)->run($organization->id);
            app(OrganizationSampleContentSeeder::class)->run($organization->id);

            $organization->update([
                'automation_enabled' => true,
                'automation_initialized_at' => $organization->automation_initialized_at ?? now(),
            ]);

            return $organization->fresh();
        });
    }
}
