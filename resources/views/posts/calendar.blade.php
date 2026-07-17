<x-app-layout>
    @php
        $providerTheme = [
            'facebook' => ['bg' => 'pp-cal-chip pp-cal-chip--facebook'],
            'instagram' => ['bg' => 'pp-cal-chip pp-cal-chip--instagram'],
            'pinterest' => ['bg' => 'pp-cal-chip pp-cal-chip--pinterest'],
        ];
        $defaultTheme = ['bg' => 'pp-cal-chip pp-cal-chip--default'];
        $prevUrl = route('posts.calendar', ['month' => $month == 1 ? 12 : $month - 1, 'year' => $month == 1 ? $year - 1 : $year]);
        $nextUrl = route('posts.calendar', ['month' => $month == 12 ? 1 : $month + 1, 'year' => $month == 12 ? $year + 1 : $year]);
        $agendaDays = collect($daysGrid)->filter(fn ($day) => $day['isCurrentMonth'] && $day['targets']->isNotEmpty());
        $canEditSelected = $selectedTarget
            ? $selectedTarget->post->canBeEditedBy(auth()->user())
            : false;
        $isPublishedSelected = $selectedTarget
            && in_array($selectedTarget->status->value, ['published', 'publishing'], true);
    @endphp

    <x-pp-page-header title="Schedule" description="Monthly view of scheduled and published posts across channels.">
        <x-slot name="actions">
            <div class="pp-cal-month-nav" role="group" aria-label="Month navigation">
                <a href="{{ $prevUrl }}" class="pp-btn-secondary pp-cal-nav-btn" title="Previous month" aria-label="Previous month">
                    <span class="material-symbols-outlined">chevron_left</span>
                </a>
                <span class="pp-cal-month-label">{{ $monthName }}</span>
                <a href="{{ $nextUrl }}" class="pp-btn-secondary pp-cal-nav-btn" title="Next month" aria-label="Next month">
                    <span class="material-symbols-outlined">chevron_right</span>
                </a>
            </div>
        </x-slot>
    </x-pp-page-header>

    <div class="pp-cal-legend mb-3 sm:mb-4">
        <span class="pp-cal-legend-item"><span class="pp-cal-chip pp-cal-chip--facebook">FB</span> Facebook</span>
        <span class="pp-cal-legend-item"><span class="pp-cal-chip pp-cal-chip--instagram">IG</span> Instagram</span>
        <span class="pp-cal-legend-item"><span class="pp-cal-chip pp-cal-chip--pinterest">Pin</span> Pinterest</span>
    </div>

    <div class="pp-cal-layout">
        <div class="pp-cal-board pp-card overflow-hidden">
            <div class="pp-cal-weekdays">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                    <div class="pp-cal-dow">
                        <span class="pp-cal-dow-full">{{ $dow }}</span>
                        <span class="pp-cal-dow-short">{{ substr($dow, 0, 1) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pp-cal-days">
                @foreach($daysGrid as $day)
                    @php
                        $count = $day['targets']->count();
                        $isEmptyCurrent = $day['isCurrentMonth'] && $count === 0;
                    @endphp
                    <div class="pp-cal-day {{ $day['isCurrentMonth'] ? 'is-current' : 'is-muted' }} {{ $day['isToday'] ? 'is-today' : '' }} {{ $isEmptyCurrent ? 'is-empty' : '' }}">
                        <div class="pp-cal-day-head">
                            <span class="pp-cal-day-num">{{ $day['date']->format('j') }}</span>
                            @if($count > 0)
                                <span class="pp-cal-day-count">{{ $count }}</span>
                            @endif
                        </div>

                        @if($isEmptyCurrent)
                            <p class="pp-cal-day-empty">No posts</p>
                        @else
                            <div class="pp-cal-day-events">
                                @foreach($day['targets'] as $target)
                                    @php $t = $providerTheme[$target->provider->value] ?? $defaultTheme; @endphp
                                    <a href="{{ route('posts.calendar', ['month' => $month, 'year' => $year, 'selected_target' => $target->id]) }}"
                                       class="{{ $t['bg'] }} {{ $selectedTarget && $selectedTarget->id === $target->id ? 'is-selected' : '' }}"
                                       title="{{ ucfirst($target->provider->value) }}{{ $target->isVideoMedia() ? ' · Video' : '' }} · {{ $target->post->title }}">
                                        @if($target->isVideoMedia())
                                            <span class="material-symbols-outlined">videocam</span>
                                        @endif
                                        <span class="pp-cal-chip-text">{{ $target->post->title }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Mobile-friendly agenda of days that have posts --}}
        <section class="pp-cal-agenda" aria-label="Posts this month">
            <div class="pp-cal-agenda-head">
                <h3>This month</h3>
                <p>{{ $agendaDays->sum(fn ($d) => $d['targets']->count()) }} posts</p>
            </div>

            @if($agendaDays->isEmpty())
                <div class="pp-cal-agenda-empty">
                    <span class="material-symbols-outlined">event_busy</span>
                    <p>No posts scheduled this month.</p>
                </div>
            @else
                <div class="pp-cal-agenda-list">
                    @foreach($agendaDays as $day)
                        <div class="pp-cal-agenda-day">
                            <div class="pp-cal-agenda-date">
                                <span class="pp-cal-agenda-dow">{{ $day['date']->format('D') }}</span>
                                <span class="pp-cal-agenda-num {{ $day['isToday'] ? 'is-today' : '' }}">{{ $day['date']->format('j') }}</span>
                            </div>
                            <div class="pp-cal-agenda-items">
                                @foreach($day['targets'] as $target)
                                    @php $t = $providerTheme[$target->provider->value] ?? $defaultTheme; @endphp
                                    <a href="{{ route('posts.calendar', ['month' => $month, 'year' => $year, 'selected_target' => $target->id]) }}"
                                       class="pp-cal-agenda-item {{ $selectedTarget && $selectedTarget->id === $target->id ? 'is-selected' : '' }}">
                                        <span class="{{ $t['bg'] }} pp-cal-agenda-provider">{{ strtoupper(substr($target->provider->value, 0, 2)) }}</span>
                                        <span class="pp-cal-agenda-title">{{ $target->post->title }}</span>
                                        <span class="material-symbols-outlined">chevron_right</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    @if($selectedTarget)
        @php $st = $providerTheme[$selectedTarget->provider->value] ?? $defaultTheme; @endphp
        <a href="{{ route('posts.calendar', ['month' => $month, 'year' => $year]) }}"
           class="pp-cal-backdrop is-open" aria-label="Close details"></a>

        <aside class="pp-cal-detail is-open" role="dialog" aria-modal="true" aria-label="Post details">
            <div class="pp-cal-detail-handle" aria-hidden="true"></div>
            <div class="pp-cal-detail-head">
                <h2>Post Details</h2>
                <a href="{{ route('posts.calendar', ['month' => $month, 'year' => $year]) }}"
                   class="pp-cal-detail-close" title="Close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </a>
            </div>

            <div class="pp-cal-detail-body">
                <div>
                    <p class="pp-label">Title</p>
                    <p class="pp-cal-detail-title">{{ $selectedTarget->post->title }}</p>
                </div>

                @if($selectedTarget->hasDisplayableMedia())
                    <div class="pp-cal-detail-media">
                        @if($selectedTarget->isVideoMedia())
                            <video
                                src="{{ $selectedTarget->resolvedMediaUrl() }}"
                                class="w-full max-h-56 object-contain bg-black"
                                controls
                                playsinline
                                preload="metadata"
                            >
                                Your browser does not support this video.
                            </video>
                        @else
                            <img src="{{ $selectedTarget->resolvedMediaUrl() }}" class="max-w-full max-h-48 object-contain cursor-zoom-in" alt=""
                                 data-pp-lightbox="{{ $selectedTarget->resolvedMediaUrl() }}" title="View full image">
                        @endif
                    </div>
                @endif

                @if($selectedTarget->post->caption)
                    <div>
                        <p class="pp-label">Caption</p>
                        <div class="pp-cal-detail-caption">{{ $selectedTarget->post->caption }}</div>
                    </div>
                @endif

                <div class="pp-cal-detail-grid">
                    <div>
                        <p class="pp-label">Network</p>
                        <span class="{{ $st['bg'] }}">{{ ucfirst($selectedTarget->provider->value) }}</span>
                    </div>
                    <div>
                        <p class="pp-label">{{ $selectedTarget->scheduled_at ? 'Scheduled' : 'Published' }}</p>
                        @php $calAt = $selectedTarget->calendarAt(); @endphp
                        <p class="pp-cal-detail-when">
                            {{ $calAt ? $calAt->timezone($selectedTarget->timezone ?: config('app.timezone'))->format('M j · g:i A') : '—' }}
                        </p>
                    </div>
                </div>

                <div>
                    <p class="pp-label">Status</p>
                    @if($selectedTarget->status->value === 'published')
                        <span class="pp-status-chip bg-emerald-50 text-emerald-700">Published</span>
                    @elseif($selectedTarget->status->value === 'failed')
                        <span class="pp-status-chip bg-[#ffdad6] text-[#93000a]">Failed</span>
                    @else
                        <span class="pp-status-chip bg-[var(--pp-primary-muted)] text-[var(--pp-primary)]">Scheduled</span>
                    @endif
                </div>

                <div class="pp-cal-detail-actions">
                    <a href="{{ route('posts.index', ['view' => $selectedTarget->post_id]) }}" class="pp-btn-primary text-sm">
                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                        View post
                    </a>
                    @if($canEditSelected && ! $isPublishedSelected)
                        <a href="{{ route('posts.edit', $selectedTarget->post_id) }}" class="pp-btn-secondary text-sm">
                            <span class="material-symbols-outlined text-[16px]">edit</span>
                            Edit
                        </a>
                    @endif
                </div>
            </div>
        </aside>
    @endif
</x-app-layout>
