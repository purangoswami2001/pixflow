<?php

use App\Models\MediaAsset;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialDestination;
use App\Enums\DestinationType;
use App\Enums\PostStatus;
use App\Enums\ProviderType;
use App\Enums\SocialAccountStatus;
use App\Services\Organizations\OrganizationService;
use App\Services\Social\SocialAccountManager;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Storage;

test('registration creates isolated firms for different owners', function () {
    $this->post('/register', [
        'name' => 'Owner A',
        'organization_name' => 'Firm A',
        'email' => 'a@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    auth()->logout();

    $this->post('/register', [
        'name' => 'Owner B',
        'organization_name' => 'Firm B',
        'email' => 'b@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    $userA = \App\Models\User::where('email', 'a@example.com')->first();
    $userB = \App\Models\User::where('email', 'b@example.com')->first();

    expect($userA->current_organization_id)->not->toBe($userB->current_organization_id);
});

test('owners can invite members and invitees join that firm', function () {
    [$owner, $organization] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    $this->actingAs($owner)
        ->post(route('admin.users.store'), [
            'email' => 'creator@example.com',
            'role' => 'creator',
        ])
        ->assertRedirect();

    $invitation = OrganizationInvitation::query()
        ->where('organization_id', $organization->id)
        ->where('email', 'creator@example.com')
        ->first();

    expect($invitation)->not->toBeNull();

    auth()->logout();

    $this->post('/register', [
        'name' => 'Creator User',
        'email' => 'creator@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation_token' => $invitation->token,
    ])->assertRedirect(route('dashboard'));

    $creator = \App\Models\User::where('email', 'creator@example.com')->first();
    expect($creator->current_organization_id)->toBe($organization->id);
    expect($creator->organizationRole())->toBe(OrganizationMembership::ROLE_CREATOR);
});

test('users can belong to multiple firms and switch context', function () {
    [$user, $orgA] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    [$otherOwner, $orgB] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    app(OrganizationService::class)->attachMember($orgB, $user, OrganizationMembership::ROLE_CREATOR);

    $this->actingAs($user)
        ->post(route('organizations.switch', $orgB))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_organization_id)->toBe($orgB->id);
    expect($user->fresh()->isAdmin())->toBeFalse();

    $this->actingAs($user->fresh())
        ->post(route('organizations.switch', $orgA))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_organization_id)->toBe($orgA->id);
    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('creators cannot access social profile connection routes', function () {
    [$creator] = createOrganizationUser(OrganizationMembership::ROLE_CREATOR);

    $this->actingAs($creator)
        ->get(route('accounts.index'))
        ->assertForbidden();

    $this->actingAs($creator)
        ->get(route('accounts.connect'))
        ->assertForbidden();
});

test('owners can connect multiple accounts and destinations stay in their firm', function () {
    [$owner, $organization] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    app(OrganizationContext::class)->set($organization);

    $manager = new SocialAccountManager();
    $fb = $manager->connectMock($owner, 'facebook', 'Firm Page');
    $ig = $manager->connectMock($owner, 'instagram', 'Firm IG');
    $pin = $manager->connectMock($owner, 'pinterest', 'Firm Pin');

    expect($fb->organization_id)->toBe($organization->id);
    expect($ig->organization_id)->toBe($organization->id);
    expect($pin->organization_id)->toBe($organization->id);
    expect(SocialAccount::forOrganization($organization)->count())->toBe(3);
    expect(SocialDestination::forOrganization($organization)->count())->toBe(3);
});

test('posts and media from one firm are invisible to another firm', function () {
    Storage::fake('public');

    [$ownerA, $orgA] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    [$ownerB, $orgB] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    app(OrganizationContext::class)->set($orgA);
    $postA = Post::create([
        'organization_id' => $orgA->id,
        'user_id' => $ownerA->id,
        'title' => 'Secret A',
        'status' => PostStatus::DRAFT,
        'caption' => 'private',
    ]);
    $mediaA = MediaAsset::create([
        'organization_id' => $orgA->id,
        'user_id' => $ownerA->id,
        'file_path' => 'media/a.png',
        'disk' => 'public',
        'original_name' => 'a.png',
        'mime_type' => 'image/png',
        'media_type' => 'image',
        'file_size' => 100,
    ]);

    app(OrganizationContext::class)->set($orgB);

    $this->actingAs($ownerB)
        ->get(route('posts.index'))
        ->assertOk()
        ->assertDontSee('Secret A');

    $this->actingAs($ownerB)
        ->get(route('posts.edit', $postA->id))
        ->assertNotFound();

    $this->actingAs($ownerB)
        ->delete(route('media.delete', $mediaA->id))
        ->assertNotFound();
});

test('destination sync cannot reparent another organization destination', function () {
    [$ownerA, $orgA] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    [$ownerB, $orgB] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    $accountA = SocialAccount::create([
        'organization_id' => $orgA->id,
        'user_id' => $ownerA->id,
        'provider' => ProviderType::FACEBOOK,
        'provider_user_id' => 'shared_external_1',
        'name' => 'A Page',
        'access_token' => 'token-a',
        'status' => SocialAccountStatus::ACTIVE,
        'metadata' => ['mock' => true],
    ]);

    $destA = SocialDestination::create([
        'organization_id' => $orgA->id,
        'social_account_id' => $accountA->id,
        'provider' => ProviderType::FACEBOOK,
        'destination_type' => DestinationType::FACEBOOK_PAGE,
        'provider_destination_id' => 'page_shared',
        'name' => 'A Destination',
        'status' => 'active',
    ]);

    $accountB = SocialAccount::create([
        'organization_id' => $orgB->id,
        'user_id' => $ownerB->id,
        'provider' => ProviderType::FACEBOOK,
        'provider_user_id' => 'shared_external_1',
        'name' => 'B Page',
        'access_token' => 'token-b',
        'status' => SocialAccountStatus::ACTIVE,
        'metadata' => ['mock' => true],
    ]);

    SocialDestination::updateOrCreate(
        [
            'organization_id' => $orgB->id,
            'provider' => ProviderType::FACEBOOK,
            'provider_destination_id' => 'page_shared',
        ],
        [
            'social_account_id' => $accountB->id,
            'destination_type' => DestinationType::FACEBOOK_PAGE,
            'name' => 'B Destination',
            'status' => 'active',
        ]
    );

    expect($destA->fresh()->social_account_id)->toBe($accountA->id);
    expect($destA->fresh()->organization_id)->toBe($orgA->id);
    expect(SocialDestination::where('provider_destination_id', 'page_shared')->count())->toBe(2);
});

test('dashboard metrics only include the active firm', function () {
    [$ownerA, $orgA] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    [$ownerB, $orgB] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    SocialAccount::create([
        'organization_id' => $orgA->id,
        'user_id' => $ownerA->id,
        'provider' => ProviderType::FACEBOOK,
        'provider_user_id' => 'a1',
        'name' => 'A',
        'access_token' => 't',
        'status' => SocialAccountStatus::ACTIVE,
    ]);

    SocialAccount::create([
        'organization_id' => $orgB->id,
        'user_id' => $ownerB->id,
        'provider' => ProviderType::FACEBOOK,
        'provider_user_id' => 'b1',
        'name' => 'B',
        'access_token' => 't',
        'status' => SocialAccountStatus::ACTIVE,
    ]);

    app(OrganizationContext::class)->set($orgA);

    $this->actingAs($ownerA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('connectedAccountsCount', 1);
});
