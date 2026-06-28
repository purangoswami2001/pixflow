<x-app-layout>
    @php
        $channelTabs = ['all' => 'All Channels'] + collect($channels)->mapWithKeys(fn ($c) => [$c['key'] => $c['name']])->all();
        $channelQuery = fn (string $key) => route('admin.schedule', array_filter([
            'channel' => $key !== 'all' ? $key : null,
        ]));
    @endphp

    <div class="pp-schedule-page">
        <div class="pp-schedule-hero">
            <div class="pp-schedule-hero-inner flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <div class="pp-schedule-hero-badge">
                        <span class="material-symbols-outlined text-[14px]">calendar_month</span>
                        Queue Schedule
                    </div>
                    <h1>Publishing schedule</h1>
                    <p>Set weekly time slots per social account. Approved posts auto-fill slots and publish on schedule.</p>
                    <div class="pp-schedule-hero-actions">
                        <a href="{{ route('accounts.index') }}" class="pp-btn-ghost">
                            <span class="material-symbols-outlined text-[18px]">share_reviews</span>
                            Social profiles
                        </a>
                        <a href="{{ route('posts.index') }}" class="pp-btn-ghost">
                            <span class="material-symbols-outlined text-[18px]">reorder</span>
                            View posts
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.schedule._nav', compact('channelTabs', 'channelFilter', 'channelQuery'))

        <div class="pp-schedule-metrics mb-6">
            <div class="pp-schedule-metric pp-schedule-metric--blue">
                <div class="pp-schedule-metric-accent"></div>
                <div class="pp-schedule-metric-icon"><span class="material-symbols-outlined">group</span></div>
                <p class="pp-schedule-metric-label">Connected</p>
                <p class="pp-schedule-metric-value">{{ $stats['total'] }}</p>
                <p class="pp-schedule-metric-sub">Active accounts</p>
            </div>
            <div class="pp-schedule-metric pp-schedule-metric--fb">
                <div class="pp-schedule-metric-accent"></div>
                <div class="pp-schedule-metric-icon"><span class="material-symbols-outlined">groups</span></div>
                <p class="pp-schedule-metric-label">Facebook</p>
                <p class="pp-schedule-metric-value">{{ $stats['facebook'] }}</p>
            </div>
            <div class="pp-schedule-metric pp-schedule-metric--ig">
                <div class="pp-schedule-metric-accent"></div>
                <div class="pp-schedule-metric-icon"><span class="material-symbols-outlined">photo_camera</span></div>
                <p class="pp-schedule-metric-label">Instagram</p>
                <p class="pp-schedule-metric-value">{{ $stats['instagram'] }}</p>
            </div>
            <div class="pp-schedule-metric pp-schedule-metric--pin">
                <div class="pp-schedule-metric-accent"></div>
                <div class="pp-schedule-metric-icon"><span class="material-symbols-outlined">push_pin</span></div>
                <p class="pp-schedule-metric-label">Pinterest</p>
                <p class="pp-schedule-metric-value">{{ $stats['pinterest'] }}</p>
            </div>
        </div>

        @if($accounts->isEmpty())
            <div class="pp-card">
                @include('admin.schedule._empty', [
                    'icon' => 'link_off',
                    'title' => $channelFilter !== 'all' ? 'No ' . ucfirst($channelFilter) . ' accounts' : 'No connected accounts',
                    'description' => 'Connect social profiles first, then configure weekly publishing slots for each account.',
                    'actionLabel' => 'Connect profiles',
                    'actionUrl' => route('accounts.index'),
                ])
            </div>
        @else
            <div class="pp-schedule-account-grid">
                @foreach($accounts as $account)
                    @php $provider = $account->provider->value; @endphp
                    <article class="pp-schedule-account-card pp-schedule-account-card--{{ $provider }}">
                        <div class="pp-schedule-account-card-top">
                            <img src="{{ $account->avatar_url }}" alt="" class="pp-schedule-avatar">
                            <div class="min-w-0 flex-1">
                                <p class="font-display font-bold text-[#131b2e] truncate">{{ $account->name }}</p>
                                <p class="text-sm text-[#737686] truncate">{{ $account->displayHandle() }}</p>
                                <div class="mt-2">
                                    @include('admin.automation._platform-chip', ['platform' => $provider])
                                </div>
                            </div>
                        </div>
                        <div class="pp-schedule-account-card-foot">
                            <a href="{{ route('admin.schedule.account', array_filter(['accountId' => $account->id, 'channel' => $channelFilter !== 'all' ? $channelFilter : null])) }}"
                               class="pp-btn-primary w-full py-2.5 text-sm justify-center">
                                <span class="material-symbols-outlined text-[18px]">calendar_month</span>
                                Open calendar
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
