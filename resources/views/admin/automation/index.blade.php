<x-app-layout>
    <div class="pp-automation-page">
        @include('admin.automation._nav')

        <div class="pp-auto-hero">
            <div class="pp-auto-hero-inner flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <div class="pp-auto-hero-badge">
                        <span class="material-symbols-outlined text-[14px]">smart_toy</span>
                        AI Automation
                    </div>
                    <h1>Generate posts on autopilot</h1>
                    <p>AI creates channel-sized posts from your topics. You review and approve before anything publishes.</p>
                    <div class="pp-auto-hero-actions">
                        <a href="{{ route('admin.automation.campaigns.create') }}" class="pp-btn-primary">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            New campaign
                        </a>
                        <a href="{{ route('admin.automation.categories') }}" class="pp-btn-ghost">
                            <span class="material-symbols-outlined text-[18px]">folder_open</span>
                            Manage content
                        </a>
                    </div>
                </div>
                <div class="pp-auto-metrics lg:!mb-0 lg:max-w-md shrink-0">
                    <div class="pp-auto-metric pp-auto-metric--green">
                        <div class="pp-auto-metric-accent"></div>
                        <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">bolt</span></div>
                        <p class="pp-auto-metric-label">AI Provider</p>
                        <p class="pp-auto-metric-value capitalize" style="font-size:1.25rem">{{ config('automation.ai_provider', 'gemini') }}</p>
                        <p class="pp-auto-metric-sub">Image: {{ \App\Support\AutomationAiProvider::imageProviderName() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="pp-auto-metrics">
            <a href="{{ route('admin.automation.categories') }}" class="pp-auto-metric pp-auto-metric--blue is-link">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">folder_open</span></div>
                <p class="pp-auto-metric-label">Categories</p>
                <p class="pp-auto-metric-value">{{ $stats['categories'] }}</p>
                <p class="pp-auto-metric-sub">{{ $stats['topics'] }} active topics</p>
            </a>
            <a href="{{ route('admin.automation.campaigns') }}" class="pp-auto-metric pp-auto-metric--purple is-link">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">campaign</span></div>
                <p class="pp-auto-metric-label">Campaigns</p>
                <p class="pp-auto-metric-value">{{ $stats['campaigns_active'] }}</p>
                <p class="pp-auto-metric-sub">{{ $stats['campaigns_total'] }} total</p>
            </a>
            <a href="{{ route('admin.automation.runs') }}" class="pp-auto-metric pp-auto-metric--green is-link">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">timeline</span></div>
                <p class="pp-auto-metric-label">Runs today</p>
                <p class="pp-auto-metric-value">{{ $stats['runs_today'] }}</p>
                <p class="pp-auto-metric-sub">Pipeline batches</p>
            </a>
            <a href="{{ route('admin.automation.runs') }}" class="pp-auto-metric pp-auto-metric--amber is-link">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">error</span></div>
                <p class="pp-auto-metric-label">Failed (7d)</p>
                <p class="pp-auto-metric-value">{{ $stats['failed_recent'] }}</p>
                <p class="pp-auto-metric-sub">Items needing retry</p>
            </a>
        </div>

        @include('admin.automation._guide', [
            'variant' => 'wizard',
            'steps' => [
                ['title' => 'Content', 'text' => 'Categories with niche, safety rules, and topic headlines.'],
                ['title' => 'Prompt Studio', 'text' => 'Platform templates for idea, copy, and image steps.'],
                ['title' => 'Campaign', 'text' => 'Daily volume, platforms, and connected accounts.'],
                ['title' => 'Monitor', 'text' => 'Pipeline runs and AI logs for full visibility.'],
                ['title' => 'Approve', 'text' => 'Review pending posts before they publish.'],
            ],
        ])

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="pp-card overflow-hidden">
                <div class="pp-auto-card-head">
                    <div>
                        <p class="pp-auto-card-head-title">Active campaigns</p>
                        <p class="pp-auto-card-head-sub">Run now or wait for daily schedule</p>
                    </div>
                    <a href="{{ route('admin.automation.campaigns') }}" class="text-xs font-semibold text-[#004ac6]">View all</a>
                </div>
                <div class="divide-y divide-[#e8eaf5]">
                    @forelse($campaigns->where('is_active', true)->take(4) as $campaign)
                        <div class="px-5 py-4 flex items-center justify-between gap-3 hover:bg-[#fafbff] transition-colors">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-[#131b2e] truncate">{{ $campaign->name }}</p>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    <span class="text-xs text-[#737686]">{{ $campaign->category?->name }}</span>
                                    <span class="text-xs text-[#c3c6d7]">·</span>
                                    <span class="text-xs font-medium text-[#434655]">{{ $campaign->posts_per_day }}/day</span>
                                </div>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($campaign->platforms ?? [] as $p)
                                        @include('admin.automation._platform-chip', ['platform' => $p, 'size' => 'sm'])
                                    @endforeach
                                </div>
                            </div>
                            <form action="{{ route('admin.automation.campaigns.run', $campaign) }}" method="POST">
                                @csrf
                                <button type="submit" class="pp-btn-primary text-xs py-2 px-3 shrink-0">Run</button>
                            </form>
                        </div>
                    @empty
                        @include('admin.automation._empty', [
                            'icon' => 'campaign',
                            'title' => 'No active campaigns',
                            'description' => 'Create a campaign to start generating posts.',
                            'actionLabel' => 'Create campaign',
                            'actionUrl' => route('admin.automation.campaigns.create'),
                        ])
                    @endforelse
                </div>
            </div>

            <div class="pp-card overflow-hidden">
                <div class="pp-auto-card-head">
                    <div>
                        <p class="pp-auto-card-head-title">Recent pipeline runs</p>
                        <p class="pp-auto-card-head-sub">Track generation progress per batch</p>
                    </div>
                    <a href="{{ route('admin.automation.runs') }}" class="text-xs font-semibold text-[#004ac6]">View all</a>
                </div>
                <div class="divide-y divide-[#e8eaf5]">
                    @forelse($recentRuns as $run)
                        @php
                            $pct = $run->planned_count > 0 ? round(($run->created_count / $run->planned_count) * 100) : 0;
                            $statusClass = match($run->status) {
                                'completed' => 'pp-status-badge--success',
                                'failed' => 'pp-status-badge--danger',
                                'running' => 'pp-status-badge--running',
                                default => 'pp-status-badge--muted',
                            };
                        @endphp
                        <a href="{{ route('admin.automation.runs.show', $run) }}" class="block px-5 py-4 hover:bg-[#fafbff] transition-colors">
                            <div class="flex justify-between items-start gap-3 mb-2">
                                <div>
                                    <p class="font-semibold text-sm text-[#131b2e]">Run #{{ $run->id }}</p>
                                    <p class="text-xs text-[#737686] mt-0.5">{{ $run->campaign?->name }}</p>
                                </div>
                                <span class="pp-status-badge {{ $statusClass }}">{{ $run->status }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="pp-auto-progress flex-1">
                                    <div class="pp-auto-progress-bar" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-xs font-semibold text-[#434655] shrink-0">{{ $run->created_count }}/{{ $run->planned_count }}</span>
                            </div>
                            @if($run->failed_count > 0)
                                <p class="text-xs text-red-600 mt-1.5">{{ $run->failed_count }} failed — click to retry</p>
                            @endif
                        </a>
                    @empty
                        @include('admin.automation._empty', [
                            'icon' => 'timeline',
                            'title' => 'No runs yet',
                            'description' => 'Run a campaign to see pipeline activity here.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
