<?php

use App\Enums\PostStatus;
use App\Enums\ScheduleMode;
use App\Models\User;
use App\Services\Social\SocialAccountManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('user');
    Role::findOrCreate('admin');
    Role::findOrCreate('creator');
    Storage::fake('public');
});

it('authenticates via api and returns a sanctum token', function () {
    $user = User::factory()->create([
        'email' => 'api@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('admin');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'api@example.com',
        'password' => 'password',
        'device_name' => 'pest',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'user' => ['id', 'name', 'email', 'roles', 'is_admin'],
        ]);

    expect($response->json('token_type'))->toBe('Bearer');
    expect($response->json('user.is_admin'))->toBeTrue();
});

it('rejects invalid api login credentials', function () {
    User::factory()->create([
        'email' => 'api@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'api@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422);
});

it('returns the authenticated user from auth me', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});

it('lists connected pinterest accounts for api clients', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $manager = app(SocialAccountManager::class);
    $account = $manager->connectMock($admin, 'pinterest', 'Travel Pins');

    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/accounts')
        ->assertOk()
        ->assertJsonPath('data.0.id', $account->id)
        ->assertJsonPath('data.0.name', 'Travel Pins')
        ->assertJsonPath('data.0.provider', 'pinterest');
});

it('creates and schedules a pinterest post via board name in ist', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $manager = app(SocialAccountManager::class);
    $account = $manager->connectMock($admin, 'pinterest', 'Travel Profile');
    $board = $account->destinations()->first();
    $board->update(['name' => 'Wanderlust Board']);

    $token = $admin->createToken('test')->plainTextToken;
    $scheduleLocal = now('Asia/Kolkata')->addDay()->setTime(15, 30, 0);

    $response = $this->withToken($token)->post('/api/v1/posts', [
        'channel' => 'pinterest',
        'title' => 'Sunset Pin',
        'description' => 'Golden hour vibes',
        'link' => 'https://example.com/sunset',
        'board_name' => 'Wanderlust Board',
        'schedule_time' => $scheduleLocal->format('Y-m-d H:i'),
        'hashtags' => 'travel,sunset',
        'image' => UploadedFile::fake()->image('pin.jpg', 1000, 1500),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Sunset Pin')
        ->assertJsonPath('data.status', PostStatus::APPROVED->value)
        ->assertJsonPath('data.targets.0.provider', 'pinterest')
        ->assertJsonPath('data.targets.0.destination.name', 'Wanderlust Board')
        ->assertJsonPath('data.targets.0.platform_payload.link_url', 'https://example.com/sunset');

    $target = $response->json('data.targets.0');
    expect($target['schedule_mode'])->toBe(ScheduleMode::MANUAL->value);
    expect($target['timezone'])->toBe('Asia/Kolkata');
    expect($target['scheduled_at_local'])->toStartWith($scheduleLocal->format('Y-m-d H:i'));
    expect($response->json('data.hashtags'))->toBe(['travel', 'sunset']);
});

it('creates a pinterest post using board_id without profile name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $manager = app(SocialAccountManager::class);
    $account = $manager->connectMock($admin, 'pinterest', 'Travel Profile');
    $board = $account->destinations()->first();
    $board->update(['name' => 'Food Board']);

    $token = $admin->createToken('test')->plainTextToken;
    $scheduleLocal = now('Asia/Kolkata')->addDays(2)->setTime(10, 0, 0);

    $this->withToken($token)->post('/api/v1/posts', [
        'channel' => 'pinterest',
        'title' => 'Brunch ideas',
        'description' => 'Weekend brunch pin',
        'board_id' => $board->id,
        'schedule_time' => $scheduleLocal->format('Y-m-d H:i'),
        'image' => UploadedFile::fake()->image('brunch.jpg', 1000, 1500),
    ], [
        'Accept' => 'application/json',
    ])->assertCreated()
        ->assertJsonPath('data.targets.0.destination.name', 'Food Board');
});
