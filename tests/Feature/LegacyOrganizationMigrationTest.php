<?php

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Enums\PostStatus;
use Spatie\Permission\Models\Role;

test('legacy organization migrate command creates a firm per admin and remaps owned data', function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('creator');

    $legacy = Organization::query()->firstOrCreate(
        ['slug' => 'legacy-organization'],
        ['name' => 'Legacy Organization'],
    );

    $admin = User::factory()->create(['name' => 'Ada Admin', 'current_organization_id' => $legacy->id]);
    $admin->assignRole('admin');

    $creator = User::factory()->create(['name' => 'Chris Creator', 'current_organization_id' => $legacy->id]);

    OrganizationMembership::query()->updateOrCreate(
        [
            'organization_id' => $legacy->id,
            'user_id' => $admin->id,
        ],
        [
            'role' => OrganizationMembership::ROLE_OWNER,
            'status' => OrganizationMembership::STATUS_ACTIVE,
        ]
    );
    OrganizationMembership::query()->updateOrCreate(
        [
            'organization_id' => $legacy->id,
            'user_id' => $creator->id,
        ],
        [
            'role' => OrganizationMembership::ROLE_CREATOR,
            'status' => OrganizationMembership::STATUS_ACTIVE,
        ]
    );

    $account = SocialAccount::query()->create([
        'organization_id' => $legacy->id,
        'user_id' => $admin->id,
        'provider' => 'facebook',
        'provider_user_id' => 'legacy-fb-1',
        'name' => 'Ada Page',
        'access_token' => 'token',
    ]);

    $post = Post::query()->create([
        'organization_id' => $legacy->id,
        'user_id' => $admin->id,
        'title' => 'Legacy post',
        'status' => PostStatus::DRAFT,
    ]);

    $this->artisan('organizations:migrate-legacy')
        ->assertSuccessful();

    $admin->refresh();
    $creator->refresh();
    $account->refresh();
    $post->refresh();

    expect($admin->current_organization_id)->not->toBe($legacy->id);
    expect($admin->organizationRole())->toBe(OrganizationMembership::ROLE_OWNER);

    $firm = Organization::query()->find($admin->current_organization_id);
    expect($firm)->not->toBeNull();
    expect($firm->name)->toBe("Ada Admin's Firm");
    expect((int) $account->organization_id)->toBe((int) $firm->id);
    expect((int) $post->organization_id)->toBe((int) $firm->id);

    expect((int) $creator->current_organization_id)->toBe((int) $firm->id);
    expect($creator->organizationRole($firm))->toBe(OrganizationMembership::ROLE_CREATOR);
    expect(
        OrganizationMembership::query()
            ->where('organization_id', $legacy->id)
            ->where('user_id', $admin->id)
            ->exists()
    )->toBeFalse();
});
