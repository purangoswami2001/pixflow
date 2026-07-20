<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SemiAiAssistController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// OAuth provider callbacks must be public — Pinterest/Meta redirect here without an active auth session.
Route::get('/oauth/{provider}/callback', [SocialAccountController::class, 'handleProviderCallback'])
    ->name('oauth.callback')
    ->where('provider', 'facebook|instagram|pinterest');
Route::get('/auth/{provider}/callback', [SocialAccountController::class, 'handleProviderCallback'])
    ->name('auth.callback')
    ->where('provider', 'facebook|instagram|pinterest');

Route::get('/invitations/{token}', [OrganizationController::class, 'showInvitation'])
    ->name('organizations.invitations.show');

Route::middleware(['auth'])->group(function () {
    Route::post('/invitations/{token}/accept', [OrganizationController::class, 'acceptInvitation'])
        ->name('organizations.invitations.accept');
});

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])
        ->name('organizations.switch');
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Social Profiles (admin only — team creates posts for admin's connected profiles)
    Route::middleware('admin')->group(function () {
        Route::get('/accounts', [SocialAccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/connect', [SocialAccountController::class, 'connect'])->name('accounts.connect');
        Route::post('/accounts/mock', [SocialAccountController::class, 'connectMock'])->name('accounts.mock');
        Route::post('/accounts/{id}/sync', [SocialAccountController::class, 'sync'])->name('accounts.sync');
        Route::get('/accounts/{id}/pinterest/boards', [SocialAccountController::class, 'selectPinterestBoard'])->name('accounts.pinterest.boards');
        Route::post('/accounts/{id}/pinterest/boards', [SocialAccountController::class, 'storePinterestBoard'])->name('accounts.pinterest.boards.save');
        Route::delete('/accounts/{id}', [SocialAccountController::class, 'disconnect'])->name('accounts.disconnect');
        Route::get('/auth/{provider}/redirect', [SocialAccountController::class, 'redirectToProvider'])->name('auth.redirect');
    });

    // Media Library
    Route::get('/media', [MediaAssetController::class, 'index'])->name('media.index');
    Route::get('/media/create', [MediaAssetController::class, 'create'])->name('media.create');
    Route::get('/media/options', [MediaAssetController::class, 'options'])->name('media.options');
    Route::post('/media/upload', [MediaAssetController::class, 'upload'])->name('media.upload');
    Route::post('/media/{id}/metadata', [MediaAssetController::class, 'saveMetadata'])->name('media.metadata');
    Route::delete('/media/{id}', [MediaAssetController::class, 'delete'])->name('media.delete');

    // Scheduled Posts
    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::get('/posts/{id}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{id}', [PostController::class, 'update'])->name('posts.update');
    Route::post('/posts/store', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/media-filter', [PostController::class, 'filterMedia'])->name('posts.media-filter');
    Route::post('/posts/targets/{id}/retry', [PostController::class, 'retryTarget'])->name('posts.retry');
    Route::post('/posts/targets/{id}/cancel', [PostController::class, 'cancelTarget'])->name('posts.cancel');
    Route::delete('/posts/{id}', [PostController::class, 'delete'])->name('posts.delete');
    Route::get('/posts/calendar', [PostController::class, 'calendar'])->name('posts.calendar');

    // AI Caption suggestion (JSON Endpoint)
    Route::post('/api/caption-suggest', [PostController::class, 'suggestCaption'])->name('posts.suggest');

    // In-app notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'page'])->name('notifications.page');
    Route::get('/api/notifications', [\App\Http\Controllers\NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/api/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all.form');
    Route::post('/api/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');

    // Semi-AI Assist (prompt kit + paste JSON, no API keys required)
    Route::post('/api/posts/semi-ai/draft-progress', [SemiAiAssistController::class, 'saveDraftProgress'])->name('posts.semi-ai.draft-progress');
    Route::post('/api/posts/semi-ai/idea-prompt', [SemiAiAssistController::class, 'ideaPrompt'])->name('posts.semi-ai.idea-prompt');
    Route::post('/api/posts/semi-ai/validate-idea', [SemiAiAssistController::class, 'validateIdea'])->name('posts.semi-ai.validate-idea');
    Route::post('/api/posts/semi-ai/validate-copy', [SemiAiAssistController::class, 'validateCopy'])->name('posts.semi-ai.validate-copy');
    Route::post('/api/posts/semi-ai/validate-image', [SemiAiAssistController::class, 'validateImage'])->name('posts.semi-ai.validate-image');
    Route::post('/api/posts/semi-ai/upload-image', [SemiAiAssistController::class, 'uploadImage'])->name('posts.semi-ai.upload-image');

    // Admin Panel
    Route::middleware('admin')->group(function () {
        Route::get('/admin/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings');
        Route::put('/admin/settings/ai', [\App\Http\Controllers\Admin\SettingsController::class, 'updateAi'])->name('admin.settings.ai');

        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/admin/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser'])->name('admin.delete-user');
        Route::delete('/admin/invitations/{id}', [AdminController::class, 'revokeInvitation'])->name('admin.invitations.revoke');
        
        Route::get('/admin/schedule', [AdminController::class, 'schedule'])->name('admin.schedule');
        Route::get('/admin/schedule/queued-posts', [AdminController::class, 'queuedPostsForSchedule'])->name('admin.schedule.queued-posts');
        Route::get('/admin/schedule/{accountId}', [AdminController::class, 'scheduleAccount'])->name('admin.schedule.account');
        Route::post('/admin/schedule', [AdminController::class, 'saveScheduleSlot'])->name('admin.schedule.save');
        Route::put('/admin/schedule/slots/{id}', [AdminController::class, 'updateScheduleSlot'])->name('admin.schedule.update');
        Route::post('/admin/schedule/post', [AdminController::class, 'scheduleManualPost'])->name('admin.schedule.post');
        Route::post('/admin/schedule/assign', [AdminController::class, 'assignManualSchedule'])->name('admin.schedule.assign');
        Route::delete('/admin/schedule/assign/{targetId}', [AdminController::class, 'releaseManualSchedule'])->name('admin.schedule.release');
        Route::delete('/admin/schedule/{id}', [AdminController::class, 'deleteScheduleSlot'])->name('admin.schedule.delete');

        Route::get('/admin/logs', [AdminController::class, 'logs'])->name('admin.logs');
        Route::post('/admin/logs/clear', [AdminController::class, 'clearLogs'])->name('admin.logs.clear');

        Route::post('/admin/posts/{id}/review', [PostController::class, 'review'])->name('posts.review');
        Route::post('/admin/posts/{id}/publish-now', [PostController::class, 'publishNow'])->name('posts.publish-now');

        Route::prefix('admin/automation')->name('admin.automation.')->middleware('automation.enabled')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AutomationController::class, 'index'])->name('index');
            Route::post('/enable', [\App\Http\Controllers\Admin\AutomationController::class, 'enable'])->name('enable');
            Route::get('/categories', [\App\Http\Controllers\Admin\AutomationController::class, 'categories'])->name('categories');
            Route::post('/categories', [\App\Http\Controllers\Admin\AutomationController::class, 'storeCategory'])->name('categories.store');
            Route::post('/categories/generate', [\App\Http\Controllers\Admin\AutomationController::class, 'generateCategoryBundle'])->name('categories.generate');
            Route::post('/categories/import', [\App\Http\Controllers\Admin\AutomationController::class, 'importCategoryBundle'])->name('categories.import');
            Route::put('/categories/{category}', [\App\Http\Controllers\Admin\AutomationController::class, 'updateCategory'])->name('categories.update');
            Route::get('/categories/{category}/topics', [\App\Http\Controllers\Admin\AutomationController::class, 'topics'])->name('topics');
            Route::post('/categories/{category}/topics', [\App\Http\Controllers\Admin\AutomationController::class, 'storeTopic'])->name('topics.store');
            Route::post('/categories/{category}/topics/bulk', [\App\Http\Controllers\Admin\AutomationController::class, 'bulkTopics'])->name('topics.bulk');
            Route::delete('/topics/{topic}', [\App\Http\Controllers\Admin\AutomationController::class, 'deleteTopic'])->name('topics.delete');
            Route::get('/prompts', [\App\Http\Controllers\Admin\AutomationController::class, 'prompts'])->name('prompts');
            Route::put('/prompts/bulk', [\App\Http\Controllers\Admin\AutomationController::class, 'bulkUpdatePrompts'])->name('prompts.bulk');
            Route::put('/prompts/{template}', [\App\Http\Controllers\Admin\AutomationController::class, 'updatePrompt'])->name('prompts.update');
            Route::get('/campaigns', [\App\Http\Controllers\Admin\AutomationController::class, 'campaigns'])->name('campaigns');
            Route::get('/campaigns/create', [\App\Http\Controllers\Admin\AutomationController::class, 'createCampaign'])->name('campaigns.create');
            Route::post('/campaigns', [\App\Http\Controllers\Admin\AutomationController::class, 'storeCampaign'])->name('campaigns.store');
            Route::get('/campaigns/{campaign}/edit', [\App\Http\Controllers\Admin\AutomationController::class, 'editCampaign'])->name('campaigns.edit');
            Route::put('/campaigns/{campaign}', [\App\Http\Controllers\Admin\AutomationController::class, 'updateCampaign'])->name('campaigns.update');
            Route::post('/campaigns/{campaign}/run', [\App\Http\Controllers\Admin\AutomationController::class, 'runCampaign'])->name('campaigns.run');
            Route::get('/runs', [\App\Http\Controllers\Admin\AutomationController::class, 'runs'])->name('runs');
            Route::get('/runs/{run}', [\App\Http\Controllers\Admin\AutomationController::class, 'showRun'])->name('runs.show');
            Route::get('/runs/{run}/feed', [\App\Http\Controllers\Admin\AutomationController::class, 'runFeed'])->name('runs.feed');
            Route::get('/logs', [\App\Http\Controllers\Admin\AutomationController::class, 'logs'])->name('logs');
            Route::get('/logs/feed', [\App\Http\Controllers\Admin\AutomationController::class, 'logsFeed'])->name('logs.feed');
            Route::get('/auto-review', [\App\Http\Controllers\Admin\AutoReviewController::class, 'index'])->name('auto-review');
            Route::post('/auto-review/scan', [\App\Http\Controllers\Admin\AutoReviewController::class, 'scan'])->name('auto-review.scan');
            Route::get('/auto-review/{id}', [\App\Http\Controllers\Admin\AutoReviewController::class, 'show'])->name('auto-review.show');
            Route::post('/auto-review/{id}', [\App\Http\Controllers\Admin\AutoReviewController::class, 'reviewOne'])->name('auto-review.review');
            Route::put('/topics/{topic}', [\App\Http\Controllers\Admin\AutomationController::class, 'updateTopic'])->name('topics.update');
            Route::post('/run-items/{item}/retry', [\App\Http\Controllers\Admin\AutomationController::class, 'retryRunItem'])->name('run-items.retry');
        });
    });
});

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/theme', [\App\Http\Controllers\ThemeController::class, 'update'])->name('profile.theme');
});

require __DIR__.'/auth.php';
