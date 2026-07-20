<?php

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and create a firm', function () {
    $response = $this->post('/register', [
        'name' => 'Test Owner',
        'organization_name' => 'Acme Social',
        'email' => 'owner@acme.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'owner@acme.test')->first();
    expect($user)->not->toBeNull();
    expect($user->current_organization_id)->not->toBeNull();

    $organization = Organization::query()->find($user->current_organization_id);
    expect($organization?->name)->toBe('Acme Social');

    $membership = OrganizationMembership::query()
        ->where('user_id', $user->id)
        ->where('organization_id', $organization->id)
        ->first();

    expect($membership?->role)->toBe(OrganizationMembership::ROLE_OWNER);
});
