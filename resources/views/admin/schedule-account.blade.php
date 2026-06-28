<x-app-layout>
    @php
        $provider = $account->provider->value;
        $weekQuery = fn (string $week) => route('admin.schedule.account', array_filter([
            'accountId' => $account->id,
            'channel' => $channelFilter !== 'all' ? $channelFilter : null,
            'week' => $week,
        ]));
        $maxDate = now()->addDays(30)->format('Y-m-d');
        $minDate = now()->format('Y-m-d');
        $dayCount = count($weekCalendar['days']);
        $gridCols = match (true) {
            $dayCount <= 1 => 'grid-cols-1',
            $dayCount === 2 => 'grid-cols-1 sm:grid-cols-2',
            $dayCount === 3 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
            $dayCount <= 5 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
            default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7',
        };
    @endphp

    <div class="pp-schedule-page">
        <div class="pp-schedule-account-banner pp-schedule-account-banner--{{ $provider }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <img src="{{ $account->avatar_url }}" alt="" class="pp-schedule-avatar pp-schedule-avatar--lg ring-4 ring-white/20">
                    <div class="min-w-0 text-white">
                        <p class="text-white/70 text-xs font-semibold uppercase tracking-wider mb-1">Publishing calendar</p>
                        <h1 class="font-display text-xl font-bold truncate">{{ $account->displayHandle() }}</h1>
                        <p class="text-white/80 text-sm truncate">{{ $account->name }}</p>
                        <div class="mt-2">@include('admin.automation._platform-chip', ['platform' => $provider])</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.schedule', array_filter(['channel' => $channelFilter !== 'all' ? $channelFilter : null])) }}"
                       class="pp-btn-ghost text-sm py-2">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        All accounts
                    </a>
                    <button type="button" onclick="openSlotsModal()" class="pp-btn-ghost text-sm py-2">
                        <span class="material-symbols-outlined text-[18px]">tune</span>
                        Slots
                        @if($queueSlots->count())
                            <span class="ml-1 min-w-[1.25rem] h-5 px-1.5 rounded-full bg-white/20 text-[11px] font-bold inline-flex items-center justify-center">{{ $queueSlots->count() }}</span>
                        @endif
                    </button>
                    <button type="button" onclick="openAddPostModal()" class="pp-schedule-cta-btn text-sm py-2">
                        <span class="material-symbols-outlined text-[18px]">post_add</span>
                        Pin post
                    </button>
                </div>
            </div>
        </div>

        <div class="pp-schedule-metrics mb-6">
            <div class="pp-schedule-metric pp-schedule-metric--blue">
                <div class="pp-schedule-metric-accent"></div>
                <div class="pp-schedule-metric-icon"><span class="material-symbols-outlined">alarm</span></div>
                <p class="pp-schedule-metric-label">Weekly slots</p>
                <p class="pp-schedule-metric-value">{{ $queueSlots->count() }}</p>
                <p class="pp-schedule-metric-sub">Recurring publish times</p>
            </div>
            <div class="pp-schedule-metric pp-schedule-metric--purple">
                <div class="pp-schedule-metric-accent"></div>
                <div class="pp-schedule-metric-icon"><span class="material-symbols-outlined">event</span></div>
                <p class="pp-schedule-metric-label">This week</p>
                <p class="pp-schedule-metric-value">{{ $scheduledPosts }}</p>
                <p class="pp-schedule-metric-sub">Scheduled posts</p>
            </div>
            <button type="button" onclick="openSlotsModal()" class="pp-schedule-metric pp-schedule-metric--action is-link text-left">
                <div class="pp-schedule-metric-icon"><span class="material-symbols-outlined">add_alarm</span></div>
                <p class="pp-schedule-metric-label">Quick action</p>
                <p class="pp-schedule-metric-value" style="font-size:1rem">Manage slots</p>
                <p class="pp-schedule-metric-sub">Add or edit weekly times →</p>
            </button>
        </div>

        <div class="pp-schedule-week-nav pp-card mb-6">
            @if($canGoPrevious)
                <a href="{{ $weekQuery($prevWeek) }}" class="pp-schedule-week-btn">
                    <span class="material-symbols-outlined">chevron_left</span>
                    <span class="hidden sm:inline">Previous</span>
                </a>
            @else
                <span class="pp-schedule-week-btn is-disabled" aria-disabled="true">
                    <span class="material-symbols-outlined">chevron_left</span>
                    <span class="hidden sm:inline">Previous</span>
                </span>
            @endif
            <div class="text-center px-4">
                <p class="text-[10px] font-bold text-[#004ac6] uppercase tracking-widest">Week view</p>
                <p class="font-display text-xl font-bold text-[#131b2e] mt-0.5">
                    {{ $weekCalendar['range_start']->format('M j') }} – {{ $weekCalendar['range_end']->format('M j, Y') }}
                </p>
                <p class="text-xs text-[#737686] mt-0.5">Upcoming slots & pinned posts</p>
            </div>
            <a href="{{ $weekQuery($nextWeek) }}" class="pp-schedule-week-btn">
                <span class="hidden sm:inline">Next</span>
                <span class="material-symbols-outlined">chevron_right</span>
            </a>
        </div>

        <div class="grid {{ $gridCols }} gap-4">
            @foreach($weekCalendar['days'] as $day)
                <div class="pp-schedule-day-card {{ $day['is_today'] ? 'is-today' : '' }}">
                    <div class="pp-schedule-day-head">
                        <div>
                            <p class="pp-schedule-day-name">{{ $day['day_name'] }}</p>
                            <p class="pp-schedule-day-date">{{ $day['date_label'] }}@if($day['is_today']) · Today @endif</p>
                        </div>
                        @if($day['is_today'])
                            <span class="pp-schedule-today-badge">Today</span>
                        @endif
                    </div>
                    <div class="pp-schedule-day-body">
                        @if(!$day['has_content'])
                            <p class="pp-schedule-day-empty">No slots</p>
                        @else
                            @foreach($day['times'] as $timeSlot)
                                <div class="pp-schedule-time-block">
                                    <div class="pp-schedule-time-head">
                                        <span class="pp-schedule-time-label">{{ $timeSlot['time'] }}</span>
                                        <span class="pp-schedule-capacity">{{ $timeSlot['capacity'] }} max</span>
                                    </div>
                                    <div class="pp-schedule-posts">
                                        @forelse($timeSlot['posts'] as $post)
                                            <div class="pp-schedule-post-item">
                                                <div class="min-w-0 flex-1">
                                                    <span class="pp-schedule-post-badge {{ $post['is_manual'] ? 'is-pinned' : 'is-auto' }}">
                                                        {{ $post['is_manual'] ? 'Pinned' : 'Auto' }}
                                                    </span>
                                                    <p class="pp-schedule-post-title">{{ $post['post_title'] }}</p>
                                                </div>
                                                @if($post['is_manual'])
                                                    <form action="{{ route('admin.schedule.release', $post['target_id']) }}" method="POST">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="pp-schedule-release-btn" title="Return to auto queue">
                                                            <span class="material-symbols-outlined">undo</span>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="pp-schedule-slot-open">Open slot</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach

                            @if(!empty($day['manual_posts']))
                                <div class="pp-schedule-manual-block">
                                    <p class="pp-schedule-manual-label">Pinned times</p>
                                    @foreach($day['manual_posts'] as $post)
                                        <div class="pp-schedule-post-item is-manual">
                                            <div class="min-w-0 flex-1">
                                                <span class="pp-schedule-post-badge is-pinned">
                                                    {{ $post['scheduled_at_local']->format('g:i A') }}
                                                </span>
                                                <p class="pp-schedule-post-title">{{ $post['post_title'] }}</p>
                                            </div>
                                            <form action="{{ route('admin.schedule.release', $post['target_id']) }}" method="POST">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="pp-schedule-release-btn" title="Return to auto queue">
                                                    <span class="material-symbols-outlined">undo</span>
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Manage Slots Modal --}}
    <div id="slotsModal" class="pp-modal hidden" aria-hidden="true">
        <div class="pp-modal-backdrop" onclick="closeSlotsModal()"></div>
        <div class="pp-modal-panel pp-modal-panel--wide">
            <div class="pp-schedule-modal-head">
                <div class="min-w-0">
                    <p class="pp-schedule-modal-eyebrow" id="slotsModalEyebrow">Queue slots</p>
                    <h3 class="pp-schedule-modal-title" id="slotsModalTitle">Manage weekly slots</h3>
                    <p class="pp-schedule-modal-sub" id="slotsModalSubtitle">Recurring times when approved posts auto-publish for {{ $account->displayHandle() }}.</p>
                </div>
                <button type="button" onclick="closeSlotsModal()" class="pp-schedule-modal-close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div id="slotsListView" class="pp-slots-view is-active">
                    @if($queueSlots->isEmpty())
                        @include('admin.schedule._empty', [
                            'icon' => 'event_busy',
                            'title' => 'No weekly slots yet',
                            'description' => 'Add recurring publish times so approved posts fill your queue automatically each week.',
                        ])
                        <div class="text-center mt-4">
                            <button type="button" onclick="showSlotForm('add')" class="pp-btn-primary text-sm py-2.5">
                                <span class="material-symbols-outlined text-[18px]">add_alarm</span>
                                Add first slot
                            </button>
                        </div>
                    @else
                        <div class="pp-slots-list">
                            @foreach($daysMap as $dow => $dayName)
                                @php $daySlots = $queueSlots->where('day_of_week', $dow); @endphp
                                @if($daySlots->isNotEmpty())
                                    <div class="pp-slots-day">
                                        <div class="pp-slots-day-header">
                                            <span class="pp-slots-day-name">{{ $dayName }}</span>
                                            <span class="pp-slots-day-count">{{ $daySlots->count() }} {{ Str::plural('slot', $daySlots->count()) }}</span>
                                        </div>
                                        <div class="pp-slots-day-items">
                                            @foreach($daySlots as $slot)
                                                <div class="pp-slot-row">
                                                    <div class="pp-slot-row-time">{{ $slot['time'] }}</div>
                                                    <div class="pp-slot-row-meta">
                                                        <span class="pp-slot-chip">
                                                            <span class="material-symbols-outlined">public</span>
                                                            {{ $slot['timezone_label'] }}
                                                        </span>
                                                        <span class="pp-slot-chip pp-slot-chip--capacity">
                                                            <span class="material-symbols-outlined">layers</span>
                                                            {{ $slot['posts_per_slot'] }} {{ Str::plural('post', $slot['posts_per_slot']) }} max
                                                        </span>
                                                    </div>
                                                    <div class="pp-slot-row-actions">
                                                        <button type="button" class="pp-slot-icon-btn" title="Edit slot" onclick='editSlot(@json($slot))'>
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                        <form action="{{ route('admin.schedule.delete', $slot['id']) }}" method="POST"
                                                            data-pp-confirm="This weekly time slot will be removed. Posts in the auto queue will reschedule."
                                                            data-pp-confirm-title="Delete slot?"
                                                            data-pp-confirm-variant="danger"
                                                            data-pp-confirm-button="Delete">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="pp-slot-icon-btn pp-slot-icon-btn--danger" title="Delete slot">
                                                                <span class="material-symbols-outlined">delete</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <div id="slotsFormView" class="pp-slots-view">
                    <form id="slotForm" action="{{ route('admin.schedule.save') }}" method="POST" class="space-y-5">
                        @csrf
                        <input type="hidden" name="social_account_id" value="{{ $account->id }}">
                        <input type="hidden" name="_method" id="slotFormMethod" value="PUT" disabled>

                        <div>
                            <label class="pp-label">Day of week</label>
                            <div class="pp-slots-form-days">
                                @foreach($daysMap as $val => $label)
                                    <label class="pp-slots-day-pick">
                                        <input type="radio" name="day_of_week" value="{{ $val }}" class="slot-day-input" {{ $val === 1 ? 'checked' : '' }} required>
                                        <span>{{ substr($label, 0, 3) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="pp-label" for="slotTime">Time</label>
                                <input type="time" name="time" id="slotTime" class="pp-input" value="09:00" required>
                            </div>
                            <div>
                                <label class="pp-label" for="slotTimezone">Timezone</label>
                                <select name="timezone" id="slotTimezone" class="pp-input" required>
                                    @foreach($timezones as $tz => $label)
                                        <option value="{{ $tz }}" @selected($tz === 'UTC')>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="pp-label" for="slotCapacity">Posts per slot</label>
                                <input type="number" name="posts_per_slot" id="slotCapacity" class="pp-input" min="1" max="20" value="1" required>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="pp-btn-primary py-2.5 px-5" id="slotFormSubmit">
                                <span class="material-symbols-outlined text-[18px]">save</span>
                                <span id="slotFormSubmitLabel">Save slot</span>
                            </button>
                            <button type="button" onclick="showSlotsList()" class="pp-btn-secondary py-2.5 px-5">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="pp-schedule-modal-foot" id="slotsModalFooter">
                <p class="text-xs text-[#737686]">{{ $queueSlots->count() }} active {{ Str::plural('slot', $queueSlots->count()) }} for this channel</p>
                <button type="button" onclick="showSlotForm('add')" class="pp-btn-primary text-sm py-2" id="slotsAddBtn">
                    <span class="material-symbols-outlined text-[18px]">add_alarm</span>
                    Add slot
                </button>
            </div>
        </div>
    </div>

    {{-- Add Post Modal --}}
    <div id="addPostModal" class="pp-modal hidden" aria-hidden="true">
        <div class="pp-modal-backdrop" onclick="closeAddPostModal()"></div>
        <div class="pp-modal-panel">
            <div class="pp-schedule-modal-head">
                <div>
                    <p class="pp-schedule-modal-eyebrow">Pin to calendar</p>
                    <h3 class="pp-schedule-modal-title">Schedule post</h3>
                    <p class="pp-schedule-modal-sub">Pick a date within the next 30 days and a publish time.</p>
                </div>
                <button type="button" onclick="closeAddPostModal()" class="pp-schedule-modal-close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form action="{{ route('admin.schedule.post') }}" method="POST" class="flex-1 overflow-y-auto p-6 space-y-4">
                @csrf
                <input type="hidden" name="social_account_id" value="{{ $account->id }}">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="pp-label">Date</label>
                        <input type="date" name="schedule_date" class="pp-input" required min="{{ $minDate }}" max="{{ $maxDate }}">
                    </div>
                    <div>
                        <label class="pp-label">Time</label>
                        <input type="time" name="time" class="pp-input" value="09:00" required>
                    </div>
                </div>
                <div>
                    <label class="pp-label">Timezone</label>
                    <select name="timezone" class="pp-input" required>
                        @foreach($timezones as $tz => $label)
                            <option value="{{ $tz }}" @selected($tz === 'UTC')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="pp-label">Select from auto queue</label>
                    <div id="addPostLoading" class="text-sm text-[#737686] py-6 text-center hidden">
                        <span class="material-symbols-outlined animate-spin text-[#004ac6]">progress_activity</span>
                        Loading queued posts…
                    </div>
                    <div id="addPostEmpty" class="pp-schedule-queue-empty hidden">No posts in the auto queue for this account.</div>
                    <div id="addPostList" class="space-y-2 max-h-64 overflow-y-auto"></div>
                </div>
                <button type="submit" id="addPostSubmitBtn" class="pp-btn-primary w-full py-3" disabled>Schedule post</button>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const accountId = {{ $account->id }};
        const queuedPostsUrl = @json(route('admin.schedule.queued-posts'));
        const slotSaveUrl = @json(route('admin.schedule.save'));
        const slotUpdateUrlTemplate = @json(route('admin.schedule.update', ['id' => '__ID__']));

        const slotsModal = document.getElementById('slotsModal');
        const addPostModal = document.getElementById('addPostModal');
        const slotsListView = document.getElementById('slotsListView');
        const slotsFormView = document.getElementById('slotsFormView');
        const slotsModalFooter = document.getElementById('slotsModalFooter');
        const slotForm = document.getElementById('slotForm');
        const slotFormMethod = document.getElementById('slotFormMethod');

        function openModal(el) {
            el.classList.remove('hidden');
            el.setAttribute('aria-hidden', 'false');
            document.body.classList.add('pp-modal-open');
            requestAnimationFrame(() => el.classList.add('is-open'));
        }

        function closeModal(el, afterClose) {
            el.classList.remove('is-open');
            document.body.classList.remove('pp-modal-open');
            setTimeout(() => {
                el.classList.add('hidden');
                el.setAttribute('aria-hidden', 'true');
                afterClose?.();
            }, 200);
        }

        function openSlotsModal() {
            showSlotsList();
            openModal(slotsModal);
        }

        function closeSlotsModal() {
            closeModal(slotsModal, showSlotsList);
        }

        function showSlotsList() {
            slotsListView.classList.add('is-active');
            slotsFormView.classList.remove('is-active');
            slotsModalFooter?.classList.remove('hidden');
            document.getElementById('slotsModalTitle').textContent = 'Manage weekly slots';
            document.getElementById('slotsModalSubtitle').textContent = 'Recurring times when approved posts auto-publish for {{ $account->displayHandle() }}.';
            document.getElementById('slotsModalEyebrow').textContent = 'Queue slots';
        }

        function showSlotForm(mode) {
            slotsListView.classList.remove('is-active');
            slotsFormView.classList.add('is-active');
            slotsModalFooter?.classList.add('hidden');

            if (mode === 'add') {
                slotForm.action = slotSaveUrl;
                slotFormMethod.disabled = true;
                document.getElementById('slotsModalTitle').textContent = 'Add weekly slot';
                document.getElementById('slotsModalSubtitle').textContent = 'Posts in the auto queue will fill this time every week.';
                document.getElementById('slotsModalEyebrow').textContent = 'New slot';
                document.getElementById('slotFormSubmitLabel').textContent = 'Create slot';
                slotForm.querySelector('input[name="day_of_week"][value="1"]')?.click();
                document.getElementById('slotTime').value = '09:00';
                document.getElementById('slotTimezone').value = 'UTC';
                document.getElementById('slotCapacity').value = '1';
            }
        }

        function editSlot(slot) {
            showSlotForm('edit');
            slotForm.action = slotUpdateUrlTemplate.replace('__ID__', slot.id);
            slotFormMethod.disabled = false;
            slotFormMethod.value = 'PUT';
            document.getElementById('slotsModalTitle').textContent = 'Edit slot';
            document.getElementById('slotsModalSubtitle').textContent = `${slot.day_name} at ${slot.time}`;
            document.getElementById('slotsModalEyebrow').textContent = 'Edit slot';
            document.getElementById('slotFormSubmitLabel').textContent = 'Save changes';

            const dayInput = slotForm.querySelector(`input[name="day_of_week"][value="${slot.day_of_week}"]`);
            if (dayInput) dayInput.checked = true;
            document.getElementById('slotTime').value = slot.time_value;
            document.getElementById('slotTimezone').value = slot.timezone;
            document.getElementById('slotCapacity').value = slot.posts_per_slot;
        }

        async function openAddPostModal() {
            document.getElementById('addPostSubmitBtn').disabled = true;
            openModal(addPostModal);
            await loadQueuedPosts();
        }

        function closeAddPostModal() {
            closeModal(addPostModal);
        }

        async function loadQueuedPosts() {
            const loading = document.getElementById('addPostLoading');
            const empty = document.getElementById('addPostEmpty');
            const list = document.getElementById('addPostList');
            loading.classList.remove('hidden');
            empty.classList.add('hidden');
            list.innerHTML = '';

            const res = await fetch(`${queuedPostsUrl}?account_id=${accountId}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            loading.classList.add('hidden');

            if (!data.posts?.length) {
                empty.classList.remove('hidden');
                return;
            }

            data.posts.forEach(post => {
                const label = document.createElement('label');
                label.className = 'pp-schedule-queue-pick';
                label.innerHTML = `
                    <input type="radio" name="post_target_id" value="${post.id}" required onchange="document.getElementById('addPostSubmitBtn').disabled=false">
                    <div class="min-w-0">
                        <p class="font-semibold text-sm text-[#131b2e]">${post.title}</p>
                        <p class="text-xs text-[#737686]">by ${post.author} · ${post.created_at}</p>
                    </div>`;
                list.appendChild(label);
            });
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (!slotsModal.classList.contains('hidden')) closeSlotsModal();
                else if (!addPostModal.classList.contains('hidden')) closeAddPostModal();
            }
        });

        @if(session('open_slots_modal'))
        document.addEventListener('DOMContentLoaded', () => openSlotsModal());
        @endif
    </script>
    @endpush
</x-app-layout>
