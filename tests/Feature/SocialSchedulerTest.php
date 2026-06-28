<?php

use App\Models\User;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\SocialDestination;
use App\Models\RecurringSchedule;
use App\Enums\PostStatus;
use App\Enums\ProviderType;
use App\Enums\SocialAccountStatus;
use App\Enums\PostType;
use App\Enums\DestinationType;
use App\Jobs\PublishPostTargetJob;
use App\Jobs\ProcessScheduledPostsJob;
use App\Services\Social\SocialAccountManager;
use App\Services\Posts\PostSchedulerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Set up default roles
    Role::findOrCreate('user');
    Role::findOrCreate('admin');
    $this->withoutMiddleware();
});

it('allows a user to connect a mock social account', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $manager = new SocialAccountManager();
    $account = $manager->connectMock($user, 'facebook', 'Test User FB Page');

    expect($account)->toBeInstanceOf(SocialAccount::class);
    expect($account->provider)->toBe(ProviderType::FACEBOOK);
    expect($account->destinations)->toHaveCount(1);
});

it('allows a user to upload media via standard upload request', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('my_image.png', 800, 800);

    $this->actingAs($user);

    $response = $this->post(route('media.upload'), [
        'files' => [$file],
        'channel' => 'facebook',
        'media_type' => 'image',
        'preset_key' => 'facebook_flexible',
    ]);

    $response->assertRedirect(route('media.index'));

    $asset = MediaAsset::where('user_id', $user->id)->first();
    expect($asset)->not->toBeNull();
    expect($asset->original_name)->toBe('my_image.png');
    expect($asset->media_type)->toBe('image');
    expect($asset->preset_key)->toBe('facebook_flexible');
    expect($asset->channels)->toBe(['facebook']);
    expect(Storage::disk('public')->exists($asset->file_path))->toBeTrue();
});

it('saves draft post successfully', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($user, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    $scheduler = new PostSchedulerService();
    $post = $scheduler->createPost($user, [
        'title' => 'My Draft Post',
        'caption' => 'Draft description text',
        'is_draft' => true,
        'status' => PostStatus::DRAFT->value,
        'schedule_type' => 'once',
        'targets' => [
            [
                'social_account_id' => $fbAcc->id,
                'social_destination_id' => $fbDest->id,
                'provider' => 'facebook',
                'post_type' => 'facebook_post',
                'scheduled_at' => now()->addDay(),
            ]
        ]
    ]);

    expect($post->status)->toBe(PostStatus::DRAFT);
    expect($post->targets->first()->status)->toBe(PostStatus::DRAFT);
});

it('dispatches due posts and publishes them successfully', function () {
    Queue::fake();

    $user = User::factory()->create();
    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($user, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    // Define queue slot for dispatcher to pickup
    \App\Models\QueueSlot::create([
        'social_destination_id' => $fbDest->id,
        'day_of_week' => now()->dayOfWeekIso % 7,
        'time' => now()->format('H:i:00'),
        'timezone' => 'UTC',
        'posts_per_slot' => 1,
    ]);

    $scheduler = new PostSchedulerService();
    $post = $scheduler->createPost($user, [
        'title' => 'My Scheduled Post',
        'caption' => 'Description text',
        'is_draft' => false,
        'status' => PostStatus::APPROVED->value, // Approved posts are eligible
        'schedule_type' => 'once',
        'targets' => [
            [
                'social_account_id' => $fbAcc->id,
                'social_destination_id' => $fbDest->id,
                'provider' => 'facebook',
                'post_type' => 'facebook_post',
                'scheduled_at' => null,
                'timezone' => 'UTC',
                'status' => PostStatus::SCHEDULED->value,
            ]
        ]
    ]);

    // Dispatch job to scan due items
    (new ProcessScheduledPostsJob())->handle();

    Queue::assertPushed(PublishPostTargetJob::class);
});

it('publishes mock post successfully via job', function () {
    $user = User::factory()->create();
    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($user, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    $scheduler = new PostSchedulerService();
    $post = $scheduler->createPost($user, [
        'title' => 'Publish Post',
        'caption' => 'Description text',
        'is_draft' => false,
        'status' => PostStatus::APPROVED->value,
        'schedule_type' => 'once',
        'targets' => [
            [
                'social_account_id' => $fbAcc->id,
                'social_destination_id' => $fbDest->id,
                'provider' => 'facebook',
                'post_type' => 'facebook_post',
                'scheduled_at' => now(),
                'status' => PostStatus::SCHEDULED->value,
            ]
        ]
    ]);

    $target = $post->targets()->first();
    expect($target->status)->toBe(PostStatus::SCHEDULED);

    // Run the job synchronously
    $job = new PublishPostTargetJob($target->id);
    $job->handle();

    $target->refresh();
    expect($target->status)->toBe(PostStatus::PUBLISHED);
    expect($target->provider_post_id)->not->toBeNull();
});

it('publishes queued post immediately when admin clicks post now', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($admin, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    $scheduler = new PostSchedulerService();
    $post = $scheduler->createPost($admin, [
        'title' => 'Post Now Test',
        'caption' => 'Publish immediately',
        'is_draft' => false,
        'status' => PostStatus::APPROVED->value,
        'schedule_type' => 'once',
        'targets' => [[
            'social_account_id' => $fbAcc->id,
            'social_destination_id' => $fbDest->id,
            'provider' => 'facebook',
            'post_type' => 'facebook_post',
            'status' => PostStatus::SCHEDULED->value,
        ]],
    ]);

    $this->actingAs($admin)
        ->post(route('posts.publish-now', $post->id))
        ->assertRedirect();

    $target = $post->targets()->first()->fresh();
    $post->refresh();

    expect($target->status)->toBe(PostStatus::PUBLISHED);
    expect($post->status)->toBe(PostStatus::PUBLISHED);
});

it('retries failed post immediately when user clicks retry', function () {
    $user = User::factory()->create();

    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($user, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    $scheduler = new PostSchedulerService();
    $post = $scheduler->createPost($user, [
        'title' => 'Retry Test',
        'caption' => 'Failed then retry',
        'is_draft' => false,
        'status' => PostStatus::FAILED->value,
        'schedule_type' => 'once',
        'targets' => [[
            'social_account_id' => $fbAcc->id,
            'social_destination_id' => $fbDest->id,
            'provider' => 'facebook',
            'post_type' => 'facebook_post',
            'status' => PostStatus::FAILED->value,
            'error_message' => 'Mock failure',
        ]],
    ]);

    $target = $post->targets()->first();

    $this->actingAs($user)
        ->post(route('posts.retry', $target->id))
        ->assertRedirect();

    $target->refresh();
    $post->refresh();

    expect($target->status)->toBe(PostStatus::PUBLISHED);
    expect($post->status)->toBe(PostStatus::PUBLISHED);
});

it('cannot access another users media assets deletion action', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $asset = MediaAsset::create([
        'user_id' => $user1->id,
        'file_path' => 'media/demo.jpg',
        'original_name' => 'demo.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
        'channels' => ['facebook']
    ]);

    $this->actingAs($user2);

    $response = $this->delete(route('media.delete', $asset->id));
    
    // Assert redirect / page throws ModelNotFound due to scoping query
    $response->assertStatus(404);
});

it('reschedules weekly recurring schedules successfully', function () {
    $user = User::factory()->create();
    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($user, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    $scheduler = new PostSchedulerService();
    
    // Create recurring post on monday/wednesday at 09:00
    $post = $scheduler->createPost($user, [
        'title' => 'Weekly Insights',
        'caption' => 'Post body info',
        'is_draft' => false,
        'schedule_type' => 'recurring',
        'targets' => [
            [
                'social_account_id' => $fbAcc->id,
                'social_destination_id' => $fbDest->id,
                'provider' => 'facebook',
                'post_type' => 'facebook_post',
            ]
        ],
        'recurring' => [
            'enabled' => true,
            'days_of_week' => [1, 3], // Mon, Wed
            'time' => '09:00',
            'timezone' => 'UTC',
            'starts_at' => now()->toDateString(),
        ]
    ]);

    $schedule = $post->recurringSchedule;
    expect($schedule)->toBeInstanceOf(RecurringSchedule::class);
    expect($schedule->next_run_at)->not->toBeNull();
    expect($schedule->status)->toBe('active');
});

it('returns caption and hashtag suggestions via post request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('posts.suggest'), [
        'brief' => 'Launching our new summer candle collection with 20% off',
        'platform' => 'instagram',
        'category' => 'marketing',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'title',
        'caption',
        'hashtags',
        'source',
    ]);
    expect($response->json('source'))->toBe('template');
});

it('reads oauth credentials from env config only', function () {
    config([
        'social-platforms.platforms.facebook.client_id' => 'env-app-id',
        'social-platforms.platforms.facebook.client_secret' => 'env-app-secret',
        'social-platforms.platforms.facebook.redirect_uri' => 'https://example.test/oauth/facebook/callback',
        'social-platforms.platforms.facebook.sandbox' => true,
    ]);

    \App\Services\SettingsService::set('facebook_client_id', 'saved-app-id');
    \App\Services\SettingsService::set('facebook_client_secret', 'saved-app-secret');

    $oauth = \App\Services\SettingsService::platformOAuth('facebook');

    expect($oauth['client_id'])->toBe('env-app-id');
    expect($oauth['client_secret'])->toBe('env-app-secret');
    expect($oauth['redirect_uri'])->toBe('https://example.test/oauth/facebook/callback');
    expect($oauth['sandbox'])->toBeTrue();
});

it('allows oauth callback without prior auth middleware', function () {
    $response = $this->get('/oauth/pinterest/callback?state=invalid&code=test');

    $response->assertRedirect(route('login'));
});

it('calculates fifo publish times for queued posts', function () {
    $user = User::factory()->create();
    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($user, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    $nextMonday = now()->next(Carbon\Carbon::MONDAY)->setTime(9, 0, 0);

    \App\Models\QueueSlot::create([
        'social_destination_id' => $fbDest->id,
        'day_of_week' => $nextMonday->dayOfWeek,
        'time' => '09:00:00',
        'timezone' => 'UTC',
        'posts_per_slot' => 1,
    ]);

    $scheduler = new PostSchedulerService();
    $post = $scheduler->createPost($user, [
        'title' => 'Queued Post',
        'caption' => 'Test',
        'is_draft' => false,
        'status' => PostStatus::APPROVED->value,
        'schedule_type' => 'once',
        'targets' => [[
            'social_account_id' => $fbAcc->id,
            'social_destination_id' => $fbDest->id,
            'provider' => 'facebook',
            'post_type' => 'facebook_post',
            'status' => PostStatus::SCHEDULED->value,
        ]],
    ]);

    $calculator = new \App\Services\Posts\QueueScheduleCalculatorService();
    $calculator->refreshDestinationSchedule($fbDest->id);

    $target = $post->targets()->first();
    expect($target->scheduled_at)->not->toBeNull();
    expect($target->scheduled_at->format('H:i'))->toBe('09:00');
});

it('builds a forward calendar from today with upcoming slots only', function () {
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-18 08:00:00'));

    $user = User::factory()->create();
    $manager = new SocialAccountManager();
    $fbAcc = $manager->connectMock($user, 'facebook', 'Page FB');
    $fbDest = $fbAcc->destinations()->first();

    $slotDay = now();

    $morningSlot = \App\Models\QueueSlot::create([
        'social_destination_id' => $fbDest->id,
        'day_of_week' => $slotDay->dayOfWeek,
        'time' => '09:00:00',
        'timezone' => 'UTC',
        'posts_per_slot' => 1,
    ]);

    $afternoonSlot = \App\Models\QueueSlot::create([
        'social_destination_id' => $fbDest->id,
        'day_of_week' => $slotDay->dayOfWeek,
        'time' => '14:00:00',
        'timezone' => 'UTC',
        'posts_per_slot' => 1,
    ]);

    \App\Models\QueueSlot::create([
        'social_destination_id' => $fbDest->id,
        'day_of_week' => $slotDay->dayOfWeek,
        'time' => '07:00:00',
        'timezone' => 'UTC',
        'posts_per_slot' => 1,
    ]);

    $calculator = new \App\Services\Posts\QueueScheduleCalculatorService();
    $calendar = $calculator->buildAccountWeekCalendar($fbAcc, now()->startOfDay());

    expect($calendar['days'])->toHaveCount(7);
    expect($calendar['days'][0]['day_name'])->toBe('Thursday');

    $todayColumn = collect($calendar['days'])->first(fn ($d) => $d['is_today']);
    expect($todayColumn)->not->toBeNull();
    expect($todayColumn['times'])->toHaveCount(2);
    expect($todayColumn['times'][0]['time'])->toBe('9:00 AM');
    expect($todayColumn['times'][1]['time'])->toBe('2:00 PM');

    $fridayColumn = collect($calendar['days'])->first(fn ($d) => $d['day_name'] === 'Friday');
    expect($fridayColumn['has_content'])->toBeFalse();

    \Carbon\Carbon::setTestNow();
});
