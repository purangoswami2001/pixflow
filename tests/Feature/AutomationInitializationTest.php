<?php

use App\Enums\PostStatus;
use App\Models\ContentCategory;
use App\Models\OrganizationMembership;
use App\Models\Post;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Automation\AutomationActorResolver;

test('automation starts off and activation seeds private firm defaults', function () {
    [$owner, $organization] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    expect($organization->ai_enabled)->toBeFalse();
    expect($organization->automation_enabled)->toBeFalse();
    expect(PromptTemplate::query()->where('organization_id', $organization->id)->count())->toBe(0);

    // Without AI Mode the automation area points to Firm Settings.
    $this->actingAs($owner)
        ->get(route('admin.automation.index'))
        ->assertOk()
        ->assertSee('AI Mode is off');

    // Enabling automation is blocked until AI Mode is on.
    $this->actingAs($owner)
        ->post(route('admin.automation.enable'))
        ->assertRedirect(route('admin.settings'));

    $organization->update(['ai_enabled' => true]);

    $this->actingAs($owner)
        ->get(route('admin.automation.index'))
        ->assertOk()
        ->assertSee('Automation is off')
        ->assertSee('Turn on automation');

    $this->actingAs($owner)
        ->post(route('admin.automation.enable'))
        ->assertRedirect(route('admin.automation.index'));

    $organization->refresh();

    expect($organization->automation_enabled)->toBeTrue();
    expect($organization->automation_initialized_at)->not->toBeNull();
    expect(PromptTemplate::query()->where('organization_id', $organization->id)->count())->toBe(9);
    expect(ContentCategory::query()->where('organization_id', $organization->id)->count())->toBe(1);
    expect(ContentCategory::query()->where('organization_id', $organization->id)->value('slug'))->toBe('sample-niche');
    expect($organization->automationCampaigns()->count())->toBe(0);
    expect($organization->posts()->count())->toBe(0);
    expect(User::query()->where('email', config('automation.ai_agent_email', 'ai-agent@pixflow.local'))->exists())
        ->toBeFalse();
});

test('firm prompt initialization is isolated and idempotent', function () {
    [$ownerA, $organizationA] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    [$ownerB, $organizationB] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    $organizationA->update(['ai_enabled' => true]);
    $organizationB->update(['ai_enabled' => true]);

    $this->actingAs($ownerA)->post(route('admin.automation.enable'))->assertRedirect();
    $this->actingAs($ownerB)->post(route('admin.automation.enable'))->assertRedirect();
    $this->actingAs($ownerA)->post(route('admin.automation.enable'))->assertRedirect();

    expect(PromptTemplate::query()->where('organization_id', $organizationA->id)->count())->toBe(9);
    expect(PromptTemplate::query()->where('organization_id', $organizationB->id)->count())->toBe(9);
    expect(PromptTemplate::query()->count())->toBe(18);
});

test('disabled firms cannot open automation setup pages directly', function () {
    [$owner, $organization] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    // AI Mode off → sent to Firm Settings.
    $this->actingAs($owner)
        ->get(route('admin.automation.prompts'))
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHas('error');

    // AI Mode on but automation still off → sent to the automation landing page.
    $organization->update(['ai_enabled' => true]);

    $this->actingAs($owner)
        ->get(route('admin.automation.prompts'))
        ->assertRedirect(route('admin.automation.index'))
        ->assertSessionHas('error');
});

test('automation uses a real firm admin as storage owner but displays AI Agent', function () {
    [$owner, $organization] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    [$creator] = createOrganizationUser(OrganizationMembership::ROLE_CREATOR, $organization);

    $category = ContentCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'AI Test',
        'slug' => 'ai-test',
        'is_active' => true,
    ]);

    $campaign = $organization->automationCampaigns()->create([
        'name' => 'AI Campaign',
        'content_category_id' => $category->id,
        'is_active' => true,
        'posts_per_day' => 1,
        'platforms' => ['facebook'],
        'platform_accounts' => [],
        'promotional_ratio' => 0,
        'created_by' => $owner->id,
    ]);

    $actor = app(AutomationActorResolver::class)->forCampaign($campaign);
    expect($actor->is($owner))->toBeTrue();

    $post = Post::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $actor->id,
        'title' => 'AI-only draft',
        'status' => PostStatus::PENDING_APPROVAL,
        'metadata' => [
            'source' => 'automation',
            'actor' => 'ai_agent',
            'actor_label' => 'AI Agent',
        ],
    ]);

    expect($post->authorLabel())->toBe('AI Agent');
    expect(User::query()->where('email', config('automation.ai_agent_email', 'ai-agent@pixflow.local'))->exists())
        ->toBeFalse();

    $this->actingAs($creator)
        ->get(route('posts.index'))
        ->assertOk()
        ->assertDontSee('AI-only draft');

    $this->actingAs($owner)
        ->get(route('posts.index'))
        ->assertOk()
        ->assertSee('AI-only draft')
        ->assertSee('AI Agent');
});
