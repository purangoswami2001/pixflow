<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\RecurringSchedule;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Services\Social\SocialAccountManager;
use Spatie\Permission\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CreateDemoData extends Command
{
    protected $signature = 'social:create-demo-data';
    protected $description = 'Seed the database with roles, users, and mock social schedule data for local testing';

    public function handle(SocialAccountManager $accountManager): int
    {
        $this->info('Initializing database seeding...');

        // Clear existing tables
        DB::statement('PRAGMA foreign_keys = OFF;');
        DB::table('publish_logs')->truncate();
        DB::table('recurring_schedules')->truncate();
        DB::table('post_targets')->truncate();
        DB::table('posts')->truncate();
        DB::table('media_assets')->truncate();
        DB::table('social_destinations')->truncate();
        DB::table('social_accounts')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('roles')->truncate();
        DB::table('users')->truncate();
        DB::statement('PRAGMA foreign_keys = ON;');

        // 1. Create Roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        // 2. Create Users
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@pixflow.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole($adminRole);

        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@pixflow.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($userRole);

        $this->info('Users created:');
        $this->info('- Admin: admin@pixflow.local (password)');
        $this->info('- User: jane@pixflow.local (password)');

        // 3. Connect Mock Accounts for Jane Doe
        $this->info('Connecting mock social accounts for Jane...');
        $fbAcc = $accountManager->connectMock($user, 'facebook', 'Jane Meta Page', 'jane.meta');
        $igAcc = $accountManager->connectMock($user, 'instagram', 'Jane Creator', 'jane.creator');
        $pinAcc = $accountManager->connectMock($user, 'pinterest', 'Jane Ideas', 'jane_ideas');

        // Fetch targets to link posts
        $fbDest = $fbAcc->destinations()->first();
        $igDest = $igAcc->destinations()->first();
        $pinDest = $pinAcc->destinations()->first();

        // 4. Create Mock Media Asset
        $this->info('Creating mock media assets...');
        Storage::disk('public')->makeDirectory('media');
        
        // Let's write a dummy image file for mock processing
        $dummyImagePath = 'media/demo_post.jpg';
        $fullPath = Storage::disk('public')->path($dummyImagePath);
        
        // Generate a 200x200 placeholder image using GD if possible
        if (function_exists('imagecreate')) {
            $im = imagecreate(800, 600);
            $bg = imagecolorallocate($im, 79, 70, 229); // Indigo 600
            $textColor = imagecolorallocate($im, 255, 255, 255);
            imagestring($im, 5, 300, 280, "PixFlow Media Asset", $textColor);
            imagejpeg($im, $fullPath);
            imagedestroy($im);
        } else {
            file_put_contents($fullPath, 'dummy image data');
        }

        $media = MediaAsset::create([
            'user_id' => $user->id,
            'file_path' => $dummyImagePath,
            'disk' => 'public',
            'original_name' => 'nature_banner.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => filesize($fullPath),
            'width' => 800,
            'height' => 600,
            'alt_text' => 'A scenic banner for our campaigns',
            'tags' => ['marketing', 'banner'],
        ]);

        // 5. Create Sample Posts
        $this->info('Seeding sample scheduled, published, and failed posts...');

        // Post 1: Published Post
        $post1 = Post::create([
            'user_id' => $user->id,
            'title' => 'Launched PixFlow App!',
            'status' => PostStatus::PUBLISHED,
            'caption' => 'We are officially live with our social scheduling assistant! Optimize your workflow and manage multiple social streams from a single workspace.',
            'hashtags' => ['#growth', '#saas', '#marketing'],
        ]);

        PostTarget::create([
            'post_id' => $post1->id,
            'social_account_id' => $fbAcc->id,
            'social_destination_id' => $fbDest->id,
            'provider' => $fbAcc->provider,
            'post_type' => PostType::FACEBOOK_POST,
            'media_asset_id' => $media->id,
            'scheduled_at' => now()->subHours(2),
            'status' => PostStatus::PUBLISHED,
            'provider_post_id' => 'fb_post_12345',
            'provider_post_url' => 'https://facebook.com/posts/12345',
            'published_at' => now()->subHours(2),
        ]);

        // Post 2: Scheduled Post
        $post2 = Post::create([
            'user_id' => $user->id,
            'title' => 'Upcoming Webinar Scheduling',
            'status' => PostStatus::SCHEDULED,
            'caption' => 'Join our upcoming webinar where we discuss modern marketing operations and schedules. Do not miss it!',
            'hashtags' => ['#webinar', '#digitalmarketing'],
        ]);

        PostTarget::create([
            'post_id' => $post2->id,
            'social_account_id' => $igAcc->id,
            'social_destination_id' => $igDest->id,
            'provider' => $igAcc->provider,
            'post_type' => PostType::INSTAGRAM_POST,
            'media_asset_id' => $media->id,
            'scheduled_at' => now()->addHours(6),
            'status' => PostStatus::SCHEDULED,
        ]);

        // Post 3: Failed Post
        $post3 = Post::create([
            'user_id' => $user->id,
            'title' => 'Expired Connection Test',
            'status' => PostStatus::FAILED,
            'caption' => 'This post failed to publish due to an expired credentials token error simulation.',
        ]);

        PostTarget::create([
            'post_id' => $post3->id,
            'social_account_id' => $pinAcc->id,
            'social_destination_id' => $pinDest->id,
            'provider' => $pinAcc->provider,
            'post_type' => PostType::PINTEREST_PIN,
            'media_asset_id' => $media->id,
            'scheduled_at' => now()->subHour(),
            'status' => PostStatus::FAILED,
            'error_message' => 'Pinterest access token expired or unauthorized: Credentials invalid.',
            'attempts_count' => 1,
        ]);

        // Post 4: Recurring Weekly Schedule
        $post4 = Post::create([
            'user_id' => $user->id,
            'title' => 'Weekly Growth Hacks',
            'status' => PostStatus::SCHEDULED,
            'caption' => 'Your weekly dose of marketing insights! Read our tips and tricks.',
            'hashtags' => ['#growth', '#marketingtips'],
        ]);

        PostTarget::create([
            'post_id' => $post4->id,
            'social_account_id' => $fbAcc->id,
            'social_destination_id' => $fbDest->id,
            'provider' => $fbAcc->provider,
            'post_type' => PostType::FACEBOOK_POST,
            'media_asset_id' => $media->id,
            'scheduled_at' => now()->addDays(2),
            'status' => PostStatus::SCHEDULED,
        ]);

        RecurringSchedule::create([
            'post_id' => $post4->id,
            'frequency' => 'weekly',
            'days_of_week' => [1, 3], // Mon, Wed
            'time' => '09:00',
            'timezone' => 'UTC',
            'starts_at' => now(),
            'next_run_at' => now()->addDays(2),
            'status' => 'active',
        ]);

        $this->info('Database seeding completed successfully.');
        return 0;
    }
}
