<x-app-layout>
    @php
        $providerIcons = ['facebook' => 'face', 'instagram' => 'photo_camera', 'pinterest' => 'push_pin'];
        $providerColors = [
            'facebook' => ['chip' => 'bg-[#1877F2]/10 text-[#1877F2]', 'border' => 'border-[#1877F2]/20'],
            'instagram' => ['chip' => 'bg-[#E4405F]/10 text-[#E4405F]', 'border' => 'border-[#E4405F]/20'],
            'pinterest' => ['chip' => 'bg-[#BD081C]/10 text-[#BD081C]', 'border' => 'border-[#BD081C]/20'],
        ];
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-[#131b2e]">Dashboard</h1>
            <p class="text-sm text-[#737686] mt-0.5">Hi, {{ explode(' ', $userName)[0] }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('posts.index') }}" class="pp-btn-secondary text-sm py-2">Posts</a>
            <a href="{{ route('posts.create') }}" class="pp-btn-primary text-sm py-2">
                <span class="material-symbols-outlined text-[18px]">add</span>
                New post
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="pp-card p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#004ac6]/10 text-[#004ac6] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">share</span>
            </div>
            <div>
                <p class="font-display text-2xl font-semibold text-[#131b2e] leading-none">{{ $connectedAccountsCount }}</p>
                <p class="text-[11px] text-[#737686] mt-1">Profiles</p>
            </div>
        </div>
        <div class="pp-card p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#4648d4]/10 text-[#4648d4] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">schedule</span>
            </div>
            <div>
                <p class="font-display text-2xl font-semibold text-[#131b2e] leading-none">{{ $scheduledThisWeek }}</p>
                <p class="text-[11px] text-[#737686] mt-1">This week</p>
            </div>
        </div>
        <div class="pp-card p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
            </div>
            <div>
                <p class="font-display text-2xl font-semibold text-[#131b2e] leading-none">{{ $publishedCount }}</p>
                <p class="text-[11px] text-[#737686] mt-1">Published</p>
            </div>
        </div>
        <div class="pp-card p-4 flex items-center gap-3 {{ $failedCount > 0 ? 'ring-1 ring-[#ba1a1a]/30' : '' }}">
            <div class="w-10 h-10 rounded-xl bg-[#ba1a1a]/10 text-[#ba1a1a] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">error</span>
            </div>
            <div>
                <p class="font-display text-2xl font-semibold text-[#131b2e] leading-none">{{ $failedCount }}</p>
                <p class="text-[11px] text-[#737686] mt-1">Failed</p>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <span class="pp-status-chip bg-[#1877F2]/10 text-[#1877F2] text-xs gap-1">
            <span class="material-symbols-outlined text-[14px]">face</span> {{ $facebookCount }}
        </span>
        <span class="pp-status-chip bg-[#E4405F]/10 text-[#E4405F] text-xs gap-1">
            <span class="material-symbols-outlined text-[14px]">photo_camera</span> {{ $instagramCount }}
        </span>
        <span class="pp-status-chip bg-[#BD081C]/10 text-[#BD081C] text-xs gap-1">
            <span class="material-symbols-outlined text-[14px]">push_pin</span> {{ $pinterestCount }}
        </span>
        @if($isAdmin && $pendingCount > 0)
            <a href="{{ route('posts.index', ['status' => 'pending_approval']) }}" class="pp-status-chip bg-amber-50 text-amber-700 text-xs ml-auto hover:bg-amber-100 transition-colors">
                {{ $pendingCount }} pending review
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Next 5 upcoming --}}
        <div class="lg:col-span-2 pp-card overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b border-[#c3c6d7] flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-sm font-semibold text-[#131b2e]">Next 5 scheduled</h2>
                    <p class="text-[10px] text-[#737686]">Soonest publish times</p>
                </div>
                <a href="{{ route('posts.index', ['status' => 'queued']) }}" class="text-xs font-semibold text-[#004ac6] hover:underline">View all</a>
            </div>
            @if($upcomingPosts->isEmpty())
                <div class="p-12 text-center flex-1 flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-4xl text-[#c3c6d7] mb-2">event_busy</span>
                    <p class="text-sm text-[#737686]">No upcoming scheduled posts</p>
                </div>
            @else
                <div class="divide-y divide-[#c3c6d7] flex-1">
                    @foreach($upcomingPosts as $index => $target)
                        @php
                            $provider = $target->provider->value;
                            $style = $providerColors[$provider] ?? ['chip' => 'bg-[#004ac6]/10 text-[#004ac6]', 'border' => 'border-[#c3c6d7]'];
                            $mediaUrl = $target->resolvedMediaUrl();
                            $isVideo = $target->isVideoMedia();
                            $tz = $target->timezone ?: 'UTC';
                            $local = $target->scheduled_at->timezone($tz);
                        @endphp
                        <div class="flex items-center gap-3 p-4 hover:bg-[#faf8ff] transition-colors">
                            <div class="w-8 h-8 rounded-full bg-[#004ac6]/10 text-[#004ac6] flex items-center justify-center text-xs font-bold shrink-0">
                                {{ $index + 1 }}
                            </div>
                            <div class="w-14 h-14 rounded-xl overflow-hidden bg-[#131b2e] shrink-0 border {{ $style['border'] }} flex items-center justify-center">
                                @if($mediaUrl)
                                    @if($isVideo)
                                        <video src="{{ $mediaUrl }}" class="max-w-full max-h-full object-contain" muted playsinline preload="metadata"></video>
                                    @else
                                        <img src="{{ $mediaUrl }}" alt="" class="max-w-full max-h-full object-contain" loading="lazy">
                                    @endif
                                @else
                                    <div class="w-full h-full flex items-center justify-center {{ $style['chip'] }}">
                                        <span class="material-symbols-outlined text-[22px]">{{ $providerIcons[$provider] ?? 'share' }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="pp-status-chip {{ $style['chip'] }} text-[9px] py-0 px-1.5 capitalize">{{ $provider }}</span>
                                </div>
                                <p class="text-sm font-semibold text-[#131b2e] truncate">{{ $target->post->title }}</p>
                                <p class="text-xs text-[#737686] truncate">{{ $target->resolvedPublishLabel() }}</p>
                            </div>
                            <div class="text-right shrink-0 pl-2 border-l border-[#c3c6d7]/60">
                                <p class="text-xs font-semibold text-[#131b2e]">{{ $local->format('D, M j') }}</p>
                                <p class="text-sm font-semibold text-[#004ac6]">{{ $local->format('g:i A') }}</p>
                                <p class="text-[10px] text-[#737686]">{{ $tz }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-5">
            {{-- Interactive 7-day calendar --}}
            <div class="pp-card overflow-hidden">
                <div class="px-5 py-3 border-b border-[#c3c6d7]">
                    <h2 class="text-sm font-semibold text-[#131b2e]">Schedule by day</h2>
                    <p class="text-[10px] text-[#737686] mt-0.5" id="calendarDayLabel">—</p>
                </div>
                <div class="p-3 grid grid-cols-7 gap-1.5 border-b border-[#c3c6d7]">
                    @foreach($calendarDays as $day)
                        @php
                            $key = $day['date']->format('Y-m-d');
                            $count = $day['targets']->count();
                        @endphp
                        <button type="button"
                            data-calendar-day="{{ $key }}"
                            class="calendar-day-btn text-center rounded-xl py-2 px-0.5 transition-all border border-transparent hover:border-[#004ac6]/30 focus:outline-none focus:ring-2 focus:ring-[#004ac6]/30 {{ $day['date']->isToday() ? 'is-today bg-[#004ac6] text-white' : ($count ? 'bg-[#f2f3ff]' : 'bg-white') }}">
                            <p class="text-[9px] uppercase opacity-80">{{ $day['date']->format('D') }}</p>
                            <p class="text-sm font-semibold leading-tight">{{ $day['date']->format('j') }}</p>
                            @if($count)
                                <p class="text-[9px] mt-0.5 font-semibold calendar-day-count {{ $day['date']->isToday() ? 'text-white/90' : 'text-[#004ac6]' }}">{{ $count }}</p>
                            @else
                                <p class="text-[9px] mt-0.5 opacity-40">·</p>
                            @endif
                        </button>
                    @endforeach
                </div>
                <div id="calendarDayPosts" class="p-3 min-h-[140px] max-h-64 overflow-y-auto space-y-2">
                    {{-- filled by JS --}}
                </div>
            </div>

            <div class="pp-card overflow-hidden">
                <div class="px-5 py-3 border-b border-[#c3c6d7]">
                    <h2 class="text-sm font-semibold text-[#131b2e]">Activity</h2>
                </div>
                <div class="divide-y divide-[#c3c6d7]">
                    @forelse($recentLogs as $log)
                        <div class="flex items-center gap-2.5 px-4 py-2.5">
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $log->status === 'success' ? 'bg-emerald-500' : 'bg-[#ba1a1a]' }}"></span>
                            <span class="text-xs text-[#434655] truncate flex-1">{{ $log->postTarget->post->title ?? 'Post' }}</span>
                            <span class="text-[10px] text-[#737686] shrink-0">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-[#737686] text-center py-6">No activity yet</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const calendarDaysData = @json($calendarDaysJson);
        const defaultCalendarKey = @json($defaultCalendarKey);
        const providerIcons = @json($providerIcons);

        function renderCalendarDay(dayKey) {
            const day = calendarDaysData.find(d => d.key === dayKey);
            const labelEl = document.getElementById('calendarDayLabel');
            const listEl = document.getElementById('calendarDayPosts');

            document.querySelectorAll('.calendar-day-btn').forEach(btn => {
                const key = btn.dataset.calendarDay;
                const meta = calendarDaysData.find(d => d.key === key);
                const isSelected = key === dayKey;
                const isToday = meta?.is_today;
                const hasPosts = (meta?.count ?? 0) > 0;

                btn.className = 'calendar-day-btn text-center rounded-xl py-2 px-0.5 transition-all border focus:outline-none';

                if (isSelected && isToday) {
                    btn.classList.add('ring-2', 'ring-[#004ac6]', 'bg-[#004ac6]', 'text-white', 'border-transparent');
                } else if (isSelected) {
                    btn.classList.add('ring-2', 'ring-[#004ac6]', 'border-[#004ac6]', 'bg-[#004ac6]/10');
                } else if (isToday) {
                    btn.classList.add('bg-[#004ac6]', 'text-white', 'border-transparent');
                } else if (hasPosts) {
                    btn.classList.add('bg-[#f2f3ff]', 'border-transparent', 'hover:border-[#004ac6]/30');
                } else {
                    btn.classList.add('bg-white', 'border-transparent', 'hover:border-[#004ac6]/30');
                }
            });

            if (!day) {
                labelEl.textContent = '—';
                listEl.innerHTML = '<p class="text-xs text-[#737686] text-center py-6">No data</p>';
                return;
            }

            labelEl.textContent = day.label + (day.is_today ? ' · Today' : '');

            if (!day.posts.length) {
                listEl.innerHTML = '<p class="text-xs text-[#737686] text-center py-8">No posts scheduled</p>';
                return;
            }

            listEl.innerHTML = day.posts.map(post => {
                const mediaHtml = post.media_url
                    ? (post.is_video
                        ? `<video src="${post.media_url}" class="max-w-full max-h-full object-contain" muted playsinline></video>`
                        : `<img src="${post.media_url}" class="max-w-full max-h-full object-contain" alt="">`)
                    : `<span class="material-symbols-outlined text-[18px]">${providerIcons[post.provider] || 'share'}</span>`;

                return `
                    <div class="flex items-center gap-2.5 p-2.5 rounded-lg bg-[#faf8ff] border border-[#c3c6d7]/60">
                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-[#131b2e] shrink-0 flex items-center justify-center border border-[#c3c6d7]">${mediaHtml}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[#131b2e] truncate">${escapeHtml(post.title)}</p>
                            <p class="text-[10px] text-[#737686] truncate">${escapeHtml(post.account)}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-semibold text-[#004ac6]">${escapeHtml(post.time)}</p>
                            <p class="text-[9px] text-[#737686] capitalize">${escapeHtml(post.provider)}</p>
                        </div>
                    </div>`;
            }).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        document.querySelectorAll('.calendar-day-btn').forEach(btn => {
            btn.addEventListener('click', () => renderCalendarDay(btn.dataset.calendarDay));
        });

        renderCalendarDay(defaultCalendarKey);
    </script>
    @endpush
</x-app-layout>
