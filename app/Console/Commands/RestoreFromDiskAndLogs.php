<?php

namespace App\Console\Commands;

use App\Enums\DestinationType;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\ProviderType;
use App\Enums\SocialAccountStatus;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\SocialDestination;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class RestoreFromDiskAndLogs extends Command
{
    protected $signature = 'pixflow:restore-from-disk
                            {--fresh-posts : Delete existing recovered posts first}';

    protected $description = 'Restore media library + published posts from storage files and laravel.log payloads';

    public function handle(): int
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('creator');
        Role::findOrCreate('user');

        $admin = User::updateOrCreate(
            ['email' => 'admin@pixflow.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);
        $this->info("Admin ready: {$admin->email}");

        $accounts = $this->ensurePlaceholderAccounts($admin);
        $mediaCount = $this->restoreMediaLibrary($admin);
        $this->info("Restored {$mediaCount} media library file(s).");

        if ($this->option('fresh-posts')) {
            PostTarget::query()->delete();
            Post::withTrashed()->forceDelete();
            $this->warn('Cleared existing posts before restore.');
        }

        $postCount = $this->restorePosts($admin, $accounts);
        $this->info("Restored {$postCount} post(s) with archived media.");

        $this->newLine();
        $this->warn('Social accounts are placeholders reconstructed from logs.');
        $this->warn('Reconnect Facebook / Instagram / Pinterest to publish again.');
        $this->info('Login: admin@pixflow.com / admin123');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{account: SocialAccount, destination: SocialDestination}>
     */
    protected function ensurePlaceholderAccounts(User $admin): array
    {
        $defs = [
            'facebook' => [
                'name' => 'Postpilot (restored)',
                'username' => 'postpilot',
                'provider_user_id' => 'restored_fb_user',
                'destination_id' => '1232084166650504',
                'destination_name' => 'Postpilot',
                'destination_type' => DestinationType::FACEBOOK_PAGE,
            ],
            'instagram' => [
                'name' => 'Puran Goswami (restored)',
                'username' => 'purangoswami',
                'provider_user_id' => 'restored_ig_user',
                'destination_id' => '17841414036199670',
                'destination_name' => 'Instagram Business',
                'destination_type' => DestinationType::INSTAGRAM_ACCOUNT,
            ],
            'pinterest' => [
                'name' => 'appestradevs (restored)',
                'username' => 'appestradevs',
                'provider_user_id' => 'restored_pin_user',
                'destination_id' => '1098315496566300880',
                'destination_name' => 'Sandbox Board',
                'destination_type' => DestinationType::PINTEREST_BOARD,
            ],
        ];

        $out = [];

        foreach ($defs as $provider => $def) {
            $account = SocialAccount::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'provider' => ProviderType::from($provider),
                    'provider_user_id' => $def['provider_user_id'],
                ],
                [
                    'name' => $def['name'],
                    'username' => $def['username'],
                    'avatar_url' => 'https://ui-avatars.com/api/?name='.urlencode($def['name']),
                    'access_token' => 'restored_placeholder_token',
                    'refresh_token' => null,
                    'token_expires_at' => now()->subDay(),
                    'scopes' => [],
                    'metadata' => [
                        'restored' => true,
                        'note' => 'Placeholder account after DB wipe. Reconnect OAuth to publish.',
                    ],
                    'status' => SocialAccountStatus::EXPIRED,
                    'last_error' => 'Restored placeholder — reconnect this profile before publishing.',
                ]
            );

            $destination = SocialDestination::updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_destination_id' => $def['destination_id'],
                ],
                [
                    'social_account_id' => $account->id,
                    'destination_type' => $def['destination_type'],
                    'name' => $def['destination_name'],
                    'avatar_url' => $account->avatar_url,
                    'metadata' => ['restored' => true],
                    'status' => 'active',
                ]
            );

            $out[$provider] = compact('account', 'destination');
        }

        return $out;
    }

    protected function restoreMediaLibrary(User $admin): int
    {
        $disk = Storage::disk('public');
        $files = File::files($disk->path('media'));
        $count = 0;

        foreach ($files as $file) {
            $relative = 'media/'.$file->getFilename();
            $mime = mime_content_type($file->getPathname()) ?: 'application/octet-stream';
            $isVideo = str_starts_with($mime, 'video/');
            $width = null;
            $height = null;

            if (! $isVideo && function_exists('getimagesize')) {
                $sizes = @getimagesize($file->getPathname());
                if ($sizes) {
                    $width = $sizes[0];
                    $height = $sizes[1];
                }
            }

            MediaAsset::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'file_path' => $relative,
                ],
                [
                    'disk' => 'public',
                    'original_name' => $file->getFilename(),
                    'mime_type' => $mime,
                    'media_type' => $isVideo ? 'video' : 'image',
                    'file_size' => $file->getSize(),
                    'width' => $width,
                    'height' => $height,
                    'tags' => ['restored'],
                    'channels' => ['facebook', 'instagram', 'pinterest'],
                    'metadata' => [
                        'source' => 'disk-restore',
                        'restored_at' => now()->toIso8601String(),
                    ],
                    'created_at' => \Carbon\Carbon::createFromTimestamp($file->getMTime()),
                    'updated_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, array{account: SocialAccount, destination: SocialDestination}>  $accounts
     */
    protected function restorePosts(User $admin, array $accounts): int
    {
        $posts = $this->postBlueprints();
        $count = 0;

        foreach ($posts as $blueprint) {
            $path = $blueprint['path'];
            if (! Storage::disk('public')->exists($path)) {
                $this->warn("Missing media file: {$path}");
                continue;
            }

            $provider = $blueprint['provider'];
            $account = $accounts[$provider]['account'];
            $destination = $accounts[$provider]['destination'];
            $absolute = Storage::disk('public')->path($path);
            $mime = mime_content_type($absolute) ?: ($blueprint['mime'] ?? 'application/octet-stream');
            $isVideo = ($blueprint['media_type'] ?? null) === 'video' || str_starts_with($mime, 'video/');
            $original = basename($path);

            $post = Post::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'title' => $blueprint['title'],
                ],
                [
                    'caption' => $blueprint['caption'],
                    'hashtags' => $blueprint['hashtags'],
                    'status' => PostStatus::PUBLISHED,
                    'metadata' => [
                        'source' => 'disk-restore',
                        'restored' => true,
                        'original_target_id' => $blueprint['target_id'],
                    ],
                    'created_at' => $blueprint['published_at'] ?? now(),
                    'updated_at' => $blueprint['published_at'] ?? now(),
                ]
            );

            $target = PostTarget::updateOrCreate(
                [
                    'post_id' => $post->id,
                    'provider' => ProviderType::from($provider),
                ],
                [
                    'social_account_id' => $account->id,
                    'social_destination_id' => $destination->id,
                    'post_type' => match ($provider) {
                        'facebook' => PostType::FACEBOOK_POST,
                        'instagram' => $isVideo ? PostType::INSTAGRAM_REEL : PostType::INSTAGRAM_POST,
                        'pinterest' => PostType::PINTEREST_PIN,
                        default => PostType::FACEBOOK_POST,
                    },
                    'platform_media_path' => $path,
                    'media_snapshot' => [
                        'original_name' => $original,
                        'mime_type' => $mime,
                        'media_type' => $isVideo ? 'video' : 'image',
                        'file_size' => Storage::disk('public')->size($path),
                        'restored' => true,
                    ],
                    'connection_snapshot' => [
                        'account_name' => $account->name,
                        'account_handle' => $account->username,
                        'destination_name' => $destination->name,
                        'provider_destination_id' => $destination->provider_destination_id,
                        'restored' => true,
                    ],
                    'platform_payload' => array_filter([
                        'title' => $blueprint['title'],
                        'description' => $blueprint['caption'],
                    ]),
                    'scheduled_at' => $blueprint['scheduled_at'] ?? null,
                    'timezone' => 'UTC',
                    'status' => PostStatus::PUBLISHED,
                    'provider_post_id' => $blueprint['provider_post_id'] ?? null,
                    'provider_post_url' => $blueprint['provider_post_url'] ?? null,
                    'published_at' => $blueprint['published_at'] ?? now(),
                    'attempts_count' => 1,
                ]
            );

            $this->line("Restored post #{$post->id} / target #{$target->id} ({$provider})");
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function postBlueprints(): array
    {
        $marketingCaption = "Marketing doesn't have to be complicated or expensive. Small, consistent actions often produce better long-term results than chasing every new trend.\n\nStart by creating posts that solve one customer problem at a time. Share genuine customer success stories to build trust, include one clear call to action, and keep a consistent weekly posting schedule so your audience knows what to expect. If you want to start more conversations, offer a free marketing checklist or a no-obligation consultation that provides real value.\n\nIf you're looking for practical marketing ideas that fit your business and budget, we'd be happy to help. Click the link to learn more and discover simple strategies you can start using today";

        $meetingCaption = "We've all had that moment where everyone is nodding confidently, and you're hoping someone explains the last five minutes. Whether it's a college project, internship discussion, or club meeting, pretending to understand usually creates more confusion later. A better approach is to jot down quick notes, ask one simple follow-up question, and repeat the main point in your own words to make sure you've understood it correctly. After the meeting, review the shared notes or summary to fill any gaps. Trust us—asking early is much easier than guessing later. What's the funniest moment you've had when you were completely lost in a meeting or group discussion?";

        $andharban = "Discover the breathtaking beauty of the Andharban Jungle Trek in Maharashtra, where lush green forests, misty trails, waterfalls, and scenic valley views create an unforgettable adventure. Whether you're planning a monsoon trek or looking for one of the best nature trails in the Western Ghats, this guide will inspire your next outdoor escape. Save this for your Maharashtra travel bucket list.";

        return [
            [
                'target_id' => 1,
                'provider' => 'facebook',
                'title' => "7 Evidence-Based Morning Habits to Boost Energy, Reduce Stress & Support Women's Health",
                'caption' => "7 Evidence-Based Morning Habits to Boost Energy, Reduce Stress & Support Women's Health",
                'hashtags' => [],
                'path' => 'post-media/1/8d56450a-6ebb-46ac-a9d5-cbff6faddb71_system-administrator-20260714-1855.png',
                'media_type' => 'image',
                'mime' => 'image/png',
                'published_at' => '2026-07-14 18:56:20',
            ],
            [
                'target_id' => 2,
                'provider' => 'instagram',
                'title' => 'Me Pretending to Understand in Meetings: 4 Smart Ways to Stay Engaged',
                'caption' => $meetingCaption,
                'hashtags' => ['IndianStudentMemes', 'CollegeHumor', 'GenZStudents', 'StudentLife'],
                'path' => 'post-media/2/26f093d7-d084-4128-a3ca-5634d757835c_system-administrator-20260714-1903.png',
                'media_type' => 'image',
                'mime' => 'image/png',
                'published_at' => '2026-07-14 19:04:42',
            ],
            [
                'target_id' => 3,
                'provider' => 'instagram',
                'title' => 'That One Friend Who Says "I Didn\'t Study" 😭 (Then Tops the Exam)',
                'caption' => 'That One Friend Who Says "I Didn\'t Study" 😭 (Then Tops the Exam)',
                'hashtags' => ['CollegeHumor', 'StudentLife'],
                'path' => 'post-media/3/8c4c81fa-c9da-4582-ac60-1974b1938092_system-administrator-20260714-1914.png',
                'media_type' => 'image',
                'mime' => 'image/png',
                'published_at' => '2026-07-14 19:15:22',
            ],
            [
                'target_id' => 4,
                'provider' => 'facebook',
                'title' => "Me Pretending to Understand in Meetings: What to Do When You're Lost",
                'caption' => $meetingCaption,
                'hashtags' => ['CollegeHumor', 'GenZStudents'],
                'path' => 'post-media/4/a3452484-186e-4342-96b7-af7d96c4ecfb_system-administrator-20260714-1940.png',
                'media_type' => 'image',
                'mime' => 'image/png',
                'published_at' => '2026-07-14 19:48:52',
                'scheduled_at' => '2026-07-14 19:48:41',
            ],
            [
                'target_id' => 5,
                'provider' => 'facebook',
                'title' => '10 Easy Marketing Tips That Help Small Businesses Get More Customers',
                'caption' => $marketingCaption,
                'hashtags' => ['SmallBusinessMarketing', 'FacebookMarketing', 'LocalBusiness', 'ContentMarketing', 'MarketingStrategy', 'BusinessGrowth'],
                'path' => 'post-media/5/85128988-f45f-49ac-8aba-350b2ca09d20_untitled-design.png',
                'media_type' => 'image',
                'mime' => 'image/png',
                'published_at' => '2026-07-15 18:36:58',
                'provider_post_id' => '122109178371376319',
            ],
            [
                'target_id' => 6,
                'provider' => 'facebook',
                'title' => "Marketing doesn't have to be complicated or expensive.",
                'caption' => $marketingCaption,
                'hashtags' => ['SmallBusinessMarketing', 'FacebookMarketing', 'LocalBusiness', 'ContentMarketing', 'MarketingStrategy', 'BusinessGrowth'],
                'path' => 'post-media/6/37e4bf7d-3f49-4bc1-9020-98d64e685778_istockphoto-1569926179-640-adpp-is.mp4',
                'media_type' => 'video',
                'mime' => 'video/mp4',
                'published_at' => '2026-07-15 18:07:42',
                'scheduled_at' => '2026-07-15 18:07:32',
                'provider_post_id' => '1360469092892466',
            ],
            [
                'target_id' => 7,
                'provider' => 'instagram',
                'title' => "We've all had that moment where everyone is",
                'caption' => $meetingCaption,
                'hashtags' => ['IndianStudentMemes', 'CollegeHumor', 'GenZStudents', 'StudentLife', 'RelatableMemes', 'InternshipLife', 'CollegeLife', 'ExamHumor'],
                'path' => 'post-media/7/23aa1b31-d699-4c4c-9829-ce3c492e8a98_86488-593944815-medium.mp4',
                'media_type' => 'video',
                'mime' => 'video/mp4',
                'published_at' => '2026-07-15 18:33:31',
                'provider_post_id' => '17978578626026392',
            ],
            [
                'target_id' => 8,
                'provider' => 'pinterest',
                'title' => "Andharban Jungle Trek: A Magical Walk Through Maharashtra's Hidden Forest",
                'caption' => $andharban,
                'hashtags' => ['Andharban', 'AndharbanTrek', 'Maharashtra', 'MaharashtraTourism', 'WesternGhats', 'JungleTrek', 'MonsoonTrek', 'NatureLovers', 'HikingIndia', 'AdventureTravel'],
                'path' => 'post-media/8/4d17cd75-d62b-42a6-b658-617f87a0b28d_untitled-design-2.png',
                'media_type' => 'image',
                'mime' => 'image/png',
                'published_at' => '2026-07-15 19:44:54',
                'provider_post_id' => '1098315427903927102',
            ],
        ];
    }
}
