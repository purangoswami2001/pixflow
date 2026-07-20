<?php

use App\Models\OrganizationMembership;

test('owner can view firm settings and toggle ai mode', function () {
    [$owner, $organization] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);

    config(['ai.cursor.api_key' => 'crsr_test_key']);

    $this->actingAs($owner)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('AI Mode')
        ->assertSee('Turn on AI Mode');

    $organization->update(['ai_enabled' => true]);

    $this->actingAs($owner)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertDontSee('AI provider ready')
        ->assertDontSee('Cursor')
        ->assertDontSee('no AI provider is configured');

    $this->actingAs($owner)
        ->put(route('admin.settings.ai'), ['ai_enabled' => 1])
        ->assertRedirect(route('admin.settings'));

    expect($organization->fresh()->ai_enabled)->toBeTrue();

    $this->actingAs($owner)
        ->put(route('admin.settings.ai'), ['ai_enabled' => 0])
        ->assertRedirect(route('admin.settings'));

    expect($organization->fresh()->ai_enabled)->toBeFalse();
});

test('creators cannot access firm settings or toggle ai mode', function () {
    [$owner, $organization] = createOrganizationUser(OrganizationMembership::ROLE_OWNER);
    [$creator] = createOrganizationUser(OrganizationMembership::ROLE_CREATOR, $organization);

    $this->actingAs($creator)->get(route('admin.settings'))->assertForbidden();
    $this->actingAs($creator)->put(route('admin.settings.ai'), ['ai_enabled' => 1])->assertForbidden();

    expect($organization->fresh()->ai_enabled)->toBeFalse();
});
