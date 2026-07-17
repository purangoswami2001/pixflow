<x-app-layout>
    <div class="pp-automation-page">
        @include('admin.automation._nav')

        <div class="pp-auto-toolbar">
            <a href="{{ route('admin.automation.campaigns.create') }}" class="pp-btn-primary text-sm py-2">
                <span class="material-symbols-outlined text-[18px]">add</span>
                New campaign
            </a>
        </div>

        @if($campaigns->total() === 0)
            <div class="pp-card">
                @include('admin.automation._empty', [
                    'icon' => 'campaign',
                    'title' => 'No campaigns yet',
                    'description' => 'Campaigns link a content category to platforms, accounts, and a daily generation schedule.',
                    'actionLabel' => 'Create your first campaign',
                    'actionUrl' => route('admin.automation.campaigns.create'),
                ])
            </div>
        @else
            <div class="pp-auto-campaign-grid" id="pp-campaigns-list" data-pp-paginated-list>
                @foreach($campaigns as $campaign)
                    <article class="pp-auto-campaign-card">
                        <div class="pp-auto-campaign-card-body">
                            <div class="flex justify-between items-start gap-2 mb-3">
                                <h3 class="font-display font-bold text-[var(--pp-text)] leading-tight">{{ $campaign->name }}</h3>
                                @if($campaign->is_active)
                                    <span class="pp-status-badge pp-status-badge--success">Active</span>
                                @else
                                    <span class="pp-status-badge pp-status-badge--muted">Paused</span>
                                @endif
                            </div>
                            <p class="text-sm text-[var(--pp-text-muted)] mb-3">{{ $campaign->category?->name ?? 'No category' }}</p>
                            <div class="flex flex-wrap gap-1.5 mb-4">
                                @foreach($campaign->platforms ?? [] as $p)
                                    @include('admin.automation._platform-chip', ['platform' => $p])
                                @endforeach
                            </div>
                            <dl class="grid grid-cols-2 gap-2.5 sm:gap-3 text-xs">
                                <div class="p-2.5 rounded-lg bg-[var(--pp-surface-soft)] border border-[var(--pp-border-soft)]">
                                    <dt class="text-[var(--pp-text-muted)] font-semibold uppercase tracking-wide">Volume</dt>
                                    <dd class="font-bold text-[var(--pp-text)] mt-0.5 text-sm">{{ $campaign->posts_per_day }}/day</dd>
                                </div>
                                <div class="p-2.5 rounded-lg bg-[var(--pp-surface-soft)] border border-[var(--pp-border-soft)]">
                                    <dt class="text-[var(--pp-text-muted)] font-semibold uppercase tracking-wide">Promo</dt>
                                    <dd class="font-bold text-[var(--pp-text)] mt-0.5 text-sm">{{ $campaign->promotional_ratio }}%</dd>
                                </div>
                                @foreach($campaign->platforms ?? [] as $p)
                                    @php
                                        $presetKey = \App\Support\AutomationCampaignPresets::resolvePresetKey($campaign, $p);
                                        $dims = \App\Support\AutomationCampaignPresets::presetDimensions($presetKey);
                                    @endphp
                                    <div class="p-2.5 rounded-lg bg-[var(--pp-surface-soft)] border border-[var(--pp-border-soft)] col-span-2">
                                        <dt class="text-[var(--pp-text-muted)] font-semibold uppercase tracking-wide capitalize">{{ $p }} image</dt>
                                        <dd class="font-medium text-[var(--pp-text-secondary)] mt-0.5 text-xs">{{ $dims['name'] }}</dd>
                                    </div>
                                @endforeach
                                <div class="p-2.5 rounded-lg bg-[var(--pp-surface-soft)] border border-[var(--pp-border-soft)] col-span-2">
                                    <dt class="text-[var(--pp-text-muted)] font-semibold uppercase tracking-wide">Schedule</dt>
                                    <dd class="font-medium text-[var(--pp-text-secondary)] mt-0.5">
                                        {{ \Carbon\Carbon::parse($campaign->generation_time)->format('g:i A') }}
                                        <span class="text-[var(--pp-text-muted)]">· {{ $campaign->default_timezone }}</span>
                                    </dd>
                                </div>
                            </dl>
                            <p class="text-xs text-[var(--pp-text-muted)] mt-3">{{ $campaign->runs_count }} pipeline runs</p>
                        </div>
                        <div class="pp-auto-campaign-card-foot">
                            <form action="{{ route('admin.automation.campaigns.run', $campaign) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" class="pp-btn-primary w-full text-xs py-2 justify-center">
                                    <span class="material-symbols-outlined text-[16px]">play_arrow</span>
                                    Run now
                                </button>
                            </form>
                            <a href="{{ route('admin.automation.campaigns.edit', $campaign) }}" class="pp-btn-secondary text-xs py-2 px-4 shrink-0">Edit</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <x-pp-pagination :paginator="$campaigns" :per-page="$perPage" list-target="#pp-campaigns-list" />
        @endif
    </div>
</x-app-layout>
