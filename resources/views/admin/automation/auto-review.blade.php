<x-app-layout>
    <div class="pp-automation-page pp-auto-review-page">
        @include('admin.automation._nav')

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="pp-auto-metrics mb-4 sm:mb-6">
            <div class="pp-auto-metric {{ $enabled ? 'pp-auto-metric--green' : 'pp-auto-metric--amber' }}">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">smart_toy</span></div>
                <p class="pp-auto-metric-label">Agent</p>
                <p class="pp-auto-metric-value" style="font-size:1.25rem">{{ $enabled ? 'On' : 'Off' }}</p>
                <p class="pp-auto-metric-sub">{{ config('app.debug') ? 'AUTO_REVIEW_ENABLED' : 'Auto review agent' }}</p>
            </div>
            <div class="pp-auto-metric pp-auto-metric--amber">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">pending_actions</span></div>
                <p class="pp-auto-metric-label">Unreviewed</p>
                <p class="pp-auto-metric-value">{{ $stats['unreviewed_pending'] }}</p>
                <p class="pp-auto-metric-sub">of {{ $stats['pending'] }} pending</p>
            </div>
            <div class="pp-auto-metric pp-auto-metric--green">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">check_circle</span></div>
                <p class="pp-auto-metric-label">Approved (30d)</p>
                <p class="pp-auto-metric-value">{{ $stats['approved_by_agent'] }}</p>
                <p class="pp-auto-metric-sub">by auto-reviewer</p>
            </div>
            <div class="pp-auto-metric pp-auto-metric--blue">
                <div class="pp-auto-metric-accent"></div>
                <div class="pp-auto-metric-icon"><span class="material-symbols-outlined">rate_review</span></div>
                <p class="pp-auto-metric-label">Revision (30d)</p>
                <p class="pp-auto-metric-value">{{ $stats['revision_by_agent'] }}</p>
                <p class="pp-auto-metric-sub">{{ $stats['rejected_by_agent'] }} rejected</p>
            </div>
        </div>

        @include('admin.automation._mobile-bar', [
            'default' => 'pending',
            'tabs' => [
                ['id' => 'pending', 'label' => 'Pending', 'icon' => 'inbox'],
                ['id' => 'decisions', 'label' => 'Decisions', 'icon' => 'fact_check'],
                ['id' => 'config', 'label' => 'Config', 'icon' => 'tune'],
            ],
            'radioName' => 'pp-auto-review',
        ])

        <div class="pp-auto-panel" data-pp-auto-panel-id="config">
            <div class="pp-card overflow-hidden mb-0 sm:mb-6 pp-auto-review-config">
                <div class="pp-auto-card-head">
                    <div>
                        <p class="pp-auto-card-head-title">Configuration</p>
                        @if(config('app.debug'))
                            <p class="pp-auto-card-head-sub">Read from .env — change values and run <code class="text-[11px]">php artisan config:clear</code></p>
                        @else
                            <p class="pp-auto-card-head-sub">Managed by your platform administrator</p>
                        @endif
                    </div>
                </div>
                <div class="pp-auto-review-config-grid">
                    @foreach([
                        ['Min score', $config['min_approve_score'] ?? 75, 'tune'],
                        ['Min confidence', $config['min_confidence'] ?? 0.65, 'verified'],
                        ['Vision', !empty($config['vision_enabled']) ? 'On' : 'Off', 'visibility'],
                        ['On pipeline', !empty($config['dispatch_after_create']) ? 'Auto' : 'Off', 'bolt'],
                        ['Re-review', !empty($config['allow_rereview']) ? 'Allowed' : 'Blocked', 'replay'],
                        ['Schedule', 'Every 5 min', 'schedule'],
                    ] as [$label, $value, $icon])
                        <div class="pp-auto-review-config-item">
                            <span class="material-symbols-outlined">{{ $icon }}</span>
                            <div>
                                <p class="pp-auto-review-config-label">{{ $label }}</p>
                                <p class="pp-auto-review-config-value">{{ $value }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                @unless($enabled)
                    <div class="px-5 py-3 border-t border-[var(--pp-border-soft)] bg-amber-50 text-sm text-amber-900">
                        @if(config('app.debug'))
                            Set <code class="text-xs">AUTO_REVIEW_ENABLED=true</code> in <code class="text-xs">.env</code>, then clear config cache to turn the agent on.
                        @else
                            The auto review agent is turned off. Contact your platform administrator to enable it.
                        @endif
                    </div>
                @endunless
            </div>
        </div>

        <div class="pp-auto-review-columns">
            <div class="pp-auto-panel is-open pp-card overflow-hidden flex flex-col min-h-0" data-pp-auto-panel-id="pending">
                <div class="pp-auto-card-head">
                    <div>
                        <p class="pp-auto-card-head-title">Pending automation posts</p>
                        <p class="pp-auto-card-head-sub">Awaiting approval — agent only reviews full automation posts</p>
                    </div>
                </div>
                @if($pending->total() === 0)
                    @include('admin.automation._empty', [
                        'icon' => 'inbox',
                        'title' => 'No pending automation posts',
                        'description' => 'When a campaign creates posts, they appear here for auto or manual review.',
                        'actionLabel' => 'Pipeline',
                        'actionUrl' => route('admin.automation.runs'),
                    ])
                @else
                    <div class="pp-auto-review-list" id="pp-review-pending-list" data-pp-paginated-list>
                        @foreach($pending as $post)
                            @php
                                $ar = $post->metadata['auto_review'] ?? null;
                                $platforms = $post->targets->pluck('provider')->map(fn ($p) => is_object($p) ? $p->value : $p)->unique()->values();
                            @endphp
                            <article class="pp-auto-review-row">
                                <div class="pp-auto-review-row-main">
                                    <div class="pp-auto-review-row-meta">
                                        <span class="pp-auto-review-id">#{{ $post->id }}</span>
                                        @foreach($platforms as $p)
                                            @include('admin.automation._platform-chip', ['platform' => $p, 'size' => 'sm'])
                                        @endforeach
                                        @if(!empty($ar['decision']))
                                            <span class="pp-status-badge pp-status-badge--muted text-[10px]">reviewed: {{ $ar['decision'] }}</span>
                                        @else
                                            <span class="pp-status-badge pp-status-badge--running text-[10px]">unreviewed</span>
                                        @endif
                                    </div>
                                    <p class="pp-auto-review-title">{{ $post->title ?: \Illuminate\Support\Str::limit($post->caption, 80) }}</p>
                                    <p class="pp-auto-review-when">{{ $post->created_at?->diffForHumans() }}</p>
                                </div>
                                <div class="pp-auto-review-actions">
                                    @if(!empty($ar['decision']))
                                        <a href="{{ route('admin.automation.auto-review.show', $post->id) }}" class="pp-btn-secondary text-xs py-1.5 px-3">View</a>
                                    @endif
                                    <a href="{{ route('posts.index', ['status' => 'pending_approval']) }}#post-{{ $post->id }}" class="pp-btn-ghost text-xs py-1.5 px-3">Open</a>
                                    <form action="{{ route('admin.automation.auto-review.review', $post->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="mode" value="sync">
                                        <button type="submit" class="pp-btn-secondary text-xs py-1.5 px-3" @disabled(! $enabled)>Review</button>
                                    </form>
                                    @if(!empty($ar['decision']))
                                        <form action="{{ route('admin.automation.auto-review.review', $post->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="mode" value="sync">
                                            <input type="hidden" name="force" value="1">
                                            <button type="submit" class="pp-btn-ghost text-xs py-1.5 px-3" title="Clear prior decision and review again" @disabled(! $enabled)>Re-run</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="px-4 pb-4">
                        <x-pp-pagination
                            :paginator="$pending"
                            :per-page="$perPage"
                            page-name="pending_page"
                            list-target="#pp-review-pending-list" />
                    </div>
                @endif
            </div>

            <div class="pp-auto-panel pp-card overflow-hidden flex flex-col min-h-0" data-pp-auto-panel-id="decisions">
                <div class="pp-auto-card-head">
                    <div>
                        <p class="pp-auto-card-head-title">Recent agent decisions</p>
                        <p class="pp-auto-card-head-sub">Automation posts with an auto_review result</p>
                    </div>
                </div>
                @if($reviewed->total() === 0)
                    @include('admin.automation._empty', [
                        'icon' => 'fact_check',
                        'title' => 'No agent reviews yet',
                        'description' => 'Reviews appear here after the pipeline creates posts.',
                    ])
                @else
                    <div class="pp-auto-review-list" id="pp-review-decisions-list" data-pp-paginated-list>
                        @foreach($reviewed as $post)
                            @php
                                $ar = $post->metadata['auto_review'] ?? [];
                                $decision = $ar['decision'] ?? '—';
                                $score = $ar['overall_score'] ?? '—';
                                $confidence = isset($ar['confidence']) ? round(((float) $ar['confidence']) * 100) : null;
                                $badge = match ($decision) {
                                    'approve' => 'pp-status-badge--success',
                                    'reject' => 'pp-status-badge--danger',
                                    'revision' => 'pp-status-badge--running',
                                    default => 'pp-status-badge--muted',
                                };
                                $note = \Illuminate\Support\Str::limit((string) ($ar['admin_note'] ?? ''), 90);
                            @endphp
                            <article class="pp-auto-review-row pp-auto-review-row--decision">
                                <div class="pp-auto-review-row-main">
                                    <div class="pp-auto-review-row-meta">
                                        <span class="pp-auto-review-id">#{{ $post->id }}</span>
                                        <span class="pp-status-badge {{ $badge }}">{{ $decision }}</span>
                                        <span class="pp-auto-review-score">
                                            <span class="material-symbols-outlined">analytics</span>
                                            {{ $score }}
                                            @if($confidence !== null)
                                                <span class="pp-auto-review-conf">{{ $confidence }}%</span>
                                            @endif
                                        </span>
                                    </div>
                                    <p class="pp-auto-review-title">{{ $post->title ?: \Illuminate\Support\Str::limit($post->caption, 80) }}</p>
                                    @if($note !== '')
                                        <p class="pp-auto-review-note">{{ $note }}</p>
                                    @endif
                                    <p class="pp-auto-review-when">{{ isset($ar['reviewed_at']) ? \Illuminate\Support\Carbon::parse($ar['reviewed_at'])->diffForHumans() : ($post->updated_at?->diffForHumans() ?? '—') }}</p>
                                </div>
                                <div class="pp-auto-review-actions">
                                    <a href="{{ route('admin.automation.auto-review.show', $post->id) }}" class="pp-btn-primary text-xs py-1.5 px-3">View</a>
                                    <a href="{{ route('posts.edit', $post->id) }}" class="pp-btn-secondary text-xs py-1.5 px-3">Edit</a>
                                    <form action="{{ route('admin.automation.auto-review.review', $post->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="mode" value="sync">
                                        <input type="hidden" name="force" value="1">
                                        <button type="submit" class="pp-btn-ghost text-xs py-1.5 px-3" @disabled(! $enabled)>Re-run</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="px-4 pb-4">
                        <x-pp-pagination
                            :paginator="$reviewed"
                            :per-page="$perPage"
                            page-name="reviewed_page"
                            list-target="#pp-review-decisions-list" />
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
