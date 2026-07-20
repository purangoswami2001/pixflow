<?php

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a user belonging to an organization with the given membership role.
 *
 * @return array{0: User, 1: Organization}
 */
function createOrganizationUser(string $role = OrganizationMembership::ROLE_OWNER, ?Organization $organization = null): array
{
    $user = User::factory()->create();
    $organization ??= Organization::query()->create([
        'name' => 'Test Firm '.uniqid(),
        'slug' => 'test-firm-'.uniqid(),
    ]);

    OrganizationMembership::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => OrganizationMembership::STATUS_ACTIVE,
    ]);

    $user->forceFill(['current_organization_id' => $organization->id])->save();
    app(OrganizationContext::class)->set($organization);

    return [$user->fresh(), $organization->fresh()];
}
