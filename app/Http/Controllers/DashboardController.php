<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\PublishLog;
use App\Models\User;
use App\Enums\PostStatus;
use App\Enums\ProviderType;
use App\Services\Posts\QueueScheduleCalculatorService;

class DashboardController extends Controller
{
    public function index(QueueScheduleCalculatorService $calculator)
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        $calculator->refreshAllQueuedSchedules();

        $postQuery = fn () => Post::query()->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id));

        $targetQuery = fn () => PostTarget::query()->whereHas(
            'post',
            fn ($q) => $q->when(! $isAdmin, fn ($pq) => $pq->where('user_id', $user->id))
        );

        $adminIds = User::role('admin')->pluck('id');
        $connectedAccountsCount = SocialAccount::whereIn('user_id', $adminIds)
            ->where('status', 'active')
            ->count();

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $scheduledThisWeek = $targetQuery()
            ->where('status', PostStatus::SCHEDULED)
            ->whereBetween('scheduled_at', [$startOfWeek, $endOfWeek])
            ->count();

        $publishedCount = $targetQuery()->where('status', PostStatus::PUBLISHED)->count();
        $failedCount = $targetQuery()->where('status', PostStatus::FAILED)->count();
        $pendingCount = $postQuery()->where('status', PostStatus::PENDING_APPROVAL)->count();

        $facebookCount = $targetQuery()->where('provider', ProviderType::FACEBOOK)->count();
        $instagramCount = $targetQuery()->where('provider', ProviderType::INSTAGRAM)->count();
        $pinterestCount = $targetQuery()->where('provider', ProviderType::PINTEREST)->count();

        $upcomingPosts = $targetQuery()
            ->with(['post', 'socialAccount', 'mediaAsset'])
            ->where('status', PostStatus::SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now()->startOfMinute())
            ->orderBy('scheduled_at', 'asc')
            ->take(5)
            ->get();

        $recentLogs = PublishLog::with(['postTarget.post', 'postTarget.socialAccount'])
            ->whereHas('postTarget.post', fn ($q) => $q->when(! $isAdmin, fn ($pq) => $pq->where('user_id', $user->id)))
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        $calendarDays = [];
        for ($i = 0; $i < 7; $i++) {
            $day = now()->addDays($i)->startOfDay();
            $dayTargets = $targetQuery()
                ->with(['post', 'socialAccount', 'mediaAsset'])
                ->where('status', PostStatus::SCHEDULED)
                ->whereNotNull('scheduled_at')
                ->whereDate('scheduled_at', $day->toDateString())
                ->orderBy('scheduled_at', 'asc')
                ->get();

            $calendarDays[] = [
                'date' => $day,
                'targets' => $dayTargets,
            ];
        }

        $calendarDaysJson = collect($calendarDays)->map(function ($day) {
            return [
                'key' => $day['date']->format('Y-m-d'),
                'label' => $day['date']->format('l, M j'),
                'short_label' => $day['date']->format('D'),
                'day_num' => $day['date']->format('j'),
                'is_today' => $day['date']->isToday(),
                'count' => $day['targets']->count(),
                'posts' => $day['targets']->map(function ($t) {
                    $tz = $t->timezone ?: 'UTC';
                    $local = $t->scheduled_at?->timezone($tz);

                    return [
                        'title' => $t->post->title,
                        'account' => $t->resolvedPublishLabel(),
                        'provider' => $t->provider->value,
                        'time' => $local?->format('g:i A') ?? '—',
                        'date' => $local?->format('M j, Y') ?? '—',
                        'timezone' => $tz,
                        'media_url' => $t->resolvedMediaUrl(),
                        'is_video' => $t->isVideoMedia(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $todayDay = collect($calendarDays)->first(fn ($d) => $d['date']->isToday());
        $defaultCalendarKey = $todayDay
            ? $todayDay['date']->format('Y-m-d')
            : ($calendarDays[0]['date']->format('Y-m-d') ?? now()->format('Y-m-d'));

        return view('dashboard', [
            'userName' => $user->name,
            'isAdmin' => $isAdmin,
            'connectedAccountsCount' => $connectedAccountsCount,
            'scheduledThisWeek' => $scheduledThisWeek,
            'publishedCount' => $publishedCount,
            'failedCount' => $failedCount,
            'pendingCount' => $pendingCount,
            'facebookCount' => $facebookCount,
            'instagramCount' => $instagramCount,
            'pinterestCount' => $pinterestCount,
            'upcomingPosts' => $upcomingPosts,
            'recentLogs' => $recentLogs,
            'calendarDays' => $calendarDays,
            'calendarDaysJson' => $calendarDaysJson,
            'defaultCalendarKey' => $defaultCalendarKey,
        ]);
    }
}
