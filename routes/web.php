<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\AdminController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Social Profiles (admin only — team creates posts for admin's connected profiles)
    Route::middleware('admin')->group(function () {
        Route::get('/accounts', [SocialAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts/mock', [SocialAccountController::class, 'connectMock'])->name('accounts.mock');
        Route::post('/accounts/{id}/sync', [SocialAccountController::class, 'sync'])->name('accounts.sync');
        Route::get('/accounts/{id}/pinterest/boards', [SocialAccountController::class, 'selectPinterestBoard'])->name('accounts.pinterest.boards');
        Route::post('/accounts/{id}/pinterest/boards', [SocialAccountController::class, 'storePinterestBoard'])->name('accounts.pinterest.boards.save');
        Route::delete('/accounts/{id}', [SocialAccountController::class, 'disconnect'])->name('accounts.disconnect');
        Route::get('/auth/{provider}/redirect', [SocialAccountController::class, 'redirectToProvider'])->name('auth.redirect');
    });

    // Media Library
    Route::get('/media', [MediaAssetController::class, 'index'])->name('media.index');
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

    // Admin Panel
    Route::middleware('admin')->group(function () {
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/admin/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser'])->name('admin.delete-user');
        
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

        Route::prefix('admin/automation')->name('admin.automation.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AutomationController::class, 'index'])->name('index');
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
});

require __DIR__.'/auth.php';
