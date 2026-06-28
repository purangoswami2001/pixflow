<x-app-layout>
    @php
        $statusOptions = $isAdmin ? [
            'all' => 'All statuses',
            'pending_approval' => 'Pending approval',
            'queued' => 'In queue',
            'published' => 'Published',
            'failed' => 'Failed',
            'needs_revision' => 'Needs revision',
            'rejected' => 'Rejected',
            'draft' => 'Drafts',
        ] : [
            'all' => 'All statuses',
            'pending_approval' => 'Pending',
            'needs_revision' => 'Needs revision',
            'rejected' => 'Rejected',
            'queued' => 'Approved',
            'published' => 'Published',
            'failed' => 'Failed',
            'draft' => 'Drafts',
        ];
        $statusStyles = [
            'published' => 'bg-emerald-50 text-emerald-700',
            'approved' => 'bg-[#2563eb]/10 text-[#004ac6]',
            'pending_approval' => 'bg-amber-50 text-amber-700',
            'needs_revision' => 'bg-[#ffdad6] text-[#93000a]',
            'rejected' => 'bg-[#ffdad6] text-[#93000a]',
            'draft' => 'bg-[#e0e3e5] text-[#444749]',
            'failed' => 'bg-[#ffdad6] text-[#93000a]',
            'publishing' => 'bg-[#2563eb]/10 text-[#004ac6]',
        ];
        $statusLabels = [
            'published' => 'Published',
            'approved' => 'In Queue',
            'pending_approval' => 'Pending',
            'needs_revision' => 'Revision',
            'rejected' => 'Rejected',
            'draft' => 'Draft',
            'failed' => 'Failed',
            'publishing' => 'Publishing',
        ];
        $feedThemes = [
            'facebook' => ['border' => 'border-[#1877F2]/25', 'header' => 'bg-[#1877F2]/[0.06]', 'accent' => 'text-[#1877F2]'],
            'instagram' => ['border' => 'border-[#E4405F]/25', 'header' => 'bg-gradient-to-r from-[#833AB4]/10 via-[#E4405F]/10 to-[#FCAF45]/10', 'accent' => 'text-[#E4405F]'],
            'pinterest' => ['border' => 'border-[#BD081C]/25', 'header' => 'bg-[#BD081C]/[0.06]', 'accent' => 'text-[#BD081C]'],
        ];
        $defaultTheme = ['border' => 'border-[#c3c6d7]', 'header' => 'bg-[#f2f3ff]', 'accent' => 'text-[#004ac6]'];
        $hasFilters = $search !== '' || $statusFilter !== 'all' || $channelFilter !== 'all';
    @endphp

    <x-pp-page-header
        title="Posts"
        :description="$isAdmin ? 'Review team creatives, approve posts, and monitor the publishing pipeline.' : 'Create creatives for your organization and track approval status.'"
    >
        <x-slot name="actions">
            <a href="{{ route('posts.create') }}" class="pp-btn-primary">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Create Post
            </a>
        </x-slot>
    </x-pp-page-header>

    <form method="GET" action="{{ route('posts.index') }}" class="pp-card p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[220px]">
                <label for="postSearch" class="text-xs font-semibold text-[#737686] uppercase tracking-wide block mb-1.5">Search</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#737686] text-[18px] pointer-events-none">search</span>
                    <input type="search" name="search" id="postSearch" value="{{ $search }}"
                        placeholder="Title, caption, tags, account, author…"
                        class="pp-input w-full pl-10 py-2 text-sm">
                </div>
            </div>
            <div class="w-full sm:w-44">
                <label for="postStatus" class="text-xs font-semibold text-[#737686] uppercase tracking-wide block mb-1.5">Status</label>
                <select name="status" id="postStatus" class="pp-input w-full py-2 text-sm">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($statusFilter === $value)>
                            {{ $label }}
                            @if($value === 'pending_approval' && $pendingCount > 0)
                                ({{ $pendingCount }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-40">
                <label for="postChannel" class="text-xs font-semibold text-[#737686] uppercase tracking-wide block mb-1.5">Channel</label>
                <select name="channel" id="postChannel" class="pp-input w-full py-2 text-sm">
                    <option value="all" @selected($channelFilter === 'all')>All channels</option>
                    @foreach($channels as $channel)
                        <option value="{{ $channel['key'] }}" @selected($channelFilter === $channel['key'])>{{ $channel['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="per_page" value="{{ request('per_page', $perPage) }}">
            <div class="flex gap-2 w-full sm:w-auto">
                <button type="submit" class="pp-btn-primary py-2 text-sm flex-1 sm:flex-none">
                    <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                    Apply
                </button>
                @if($hasFilters)
                    <a href="{{ route('posts.index', ['per_page' => request('per_page', $perPage)]) }}" class="pp-btn-secondary py-2 text-sm flex-1 sm:flex-none text-center">
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    @if($posts->isEmpty())
        <div class="pp-card p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-[#c3c6d7] mb-4 block">post_add</span>
            <h3 class="font-display text-lg font-semibold text-[#131b2e] mb-2">No posts found</h3>
            <p class="text-sm text-[#737686] mb-6">No posts match your filters.@if($hasFilters) Try adjusting search or filters.@endif</p>
            <a href="{{ route('posts.create') }}" class="pp-btn-primary">Create a post</a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($posts as $post)
                @php
                    $target = $post->targets->first();
                    $status = $post->status->value;
                    $chipClass = $statusStyles[$status] ?? 'bg-[#f2f3ff] text-[#434655]';
                    $provider = $target?->resolvedProvider();
                    $theme = $feedThemes[$provider] ?? $defaultTheme;
                    $accountLabel = $target?->resolvedPublishLabel() ?? '@account';
                    $isVideo = $target?->isVideoMedia() ?? false;
                    $canEdit = $post->canBeEditedBy(auth()->user());
                    $canDelete = $post->canBeDeletedBy(auth()->user());
                    $canReview = $isAdmin && $status === 'pending_approval';
                    $canRetry = $status === 'failed' && $target
                        && $target->status->value === 'failed'
                        && ($isAdmin || $post->user_id === auth()->id());
                    $scheduleInfo = $target ? ($scheduleMap[$target->id] ?? null) : null;
                @endphp
                <article class="feed-card pp-card overflow-hidden flex flex-col border {{ $theme['border'] }} hover:shadow-md transition-shadow {{ $status === 'needs_revision' ? 'ring-1 ring-[#ffdad6]' : '' }}">
                    {{-- Channel feed header --}}
                    <div class="px-2.5 py-2 flex items-center justify-between gap-1 border-b border-[#c3c6d7]/50 {{ $theme['header'] }}">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <div class="w-6 h-6 rounded-full bg-white border border-[#c3c6d7] flex items-center justify-center shrink-0 {{ $theme['accent'] }}">
                                <x-platform-icon :provider="$provider" class="w-3.5 h-3.5" />
                            </div>
                            <span class="text-[10px] font-semibold text-[#131b2e] truncate" title="{{ $accountLabel }}">{{ $accountLabel }}</span>
                        </div>
                        <span class="pp-status-chip {{ $chipClass }} text-[9px] py-0 px-1.5 shrink-0">{{ $statusLabels[$status] ?? $status }}</span>
                        @if(($post->metadata['source'] ?? '') === 'automation')
                            <span class="pp-status-chip bg-violet-50 text-violet-700 text-[8px] py-0 px-1 shrink-0" title="AI Agent">AI</span>
                        @endif
                    </div>

                    {{-- Media (preset aspect, full image visible) --}}
                    <div class="relative">
                        <x-pp-media-frame
                            :media="null"
                            :channel="$provider"
                            :fallback-url="$target?->resolvedMediaUrl()"
                            :fallback-mime="$target?->resolvedMimeType()"
                            :fallback-dimensions="$target?->displayDimensions()"
                            max-height="240px"
                            class="feed-media"
                        />
                        @if($isVideo)
                            <span class="absolute bottom-1.5 right-1.5 pp-status-chip bg-black/70 text-white text-[8px]">VIDEO</span>
                        @endif
                    </div>

                    {{-- Channel-style action row --}}
                    @if($provider === 'instagram')
                        <div class="px-2.5 py-1.5 flex gap-2 border-b border-[#c3c6d7]/40 text-[#131b2e]">
                            <span class="material-symbols-outlined text-[16px]">favorite_border</span>
                            <span class="material-symbols-outlined text-[16px]">chat_bubble_outline</span>
                            <span class="material-symbols-outlined text-[16px]">send</span>
                        </div>
                    @elseif($provider === 'facebook')
                        <div class="px-2.5 py-1 border-b border-[#c3c6d7]/40 flex justify-around text-[#65676b] text-[10px]">
                            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[14px]">thumb_up</span> Like</span>
                            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[14px]">chat_bubble_outline</span> Comment</span>
                        </div>
                    @endif

                    <div class="p-2.5 flex-1 flex flex-col min-h-0">
                        <p class="text-[11px] font-semibold text-[#131b2e] truncate mb-0.5">{{ $post->title }}</p>
                        <p class="text-[10px] text-[#434655] line-clamp-2 leading-snug flex-1">
                            @if($provider === 'instagram')
                                <span class="font-semibold text-[#131b2e]">{{ strtok(ltrim($target?->resolvedAccountHandle() ?? '@account', '@'), ' ') }}</span>
                            @endif
                            {{ $post->caption }}
                        </p>

                        @if($isAdmin && $post->user)
                            <p class="text-[9px] text-[#737686] mt-1 truncate">by {{ $post->user->name }}</p>
                        @endif

                        @if($status === 'approved' && $scheduleInfo)
                            <p class="text-[9px] mt-1.5 {{ ($scheduleInfo['is_manual'] ?? false) ? 'text-[#004ac6]' : 'text-[#737686]' }} truncate">
                                <span class="material-symbols-outlined text-[11px] align-middle">{{ ($scheduleInfo['is_manual'] ?? false) ? 'event' : 'schedule' }}</span>
                                {{ $scheduleInfo['scheduled_at_local']->timezone($scheduleInfo['timezone'])->format('M j, g:i A') }}
                            </p>
                        @endif

                        @if($status === 'failed' && $target?->error_message)
                            <p class="text-[9px] text-[#93000a] mt-1 line-clamp-2" title="{{ $target->error_message }}">{{ $target->error_message }}</p>
                        @endif

                        @if($post->admin_note && in_array($status, ['needs_revision', 'rejected', 'pending_approval']))
                            <p class="text-[9px] text-[#93000a] mt-1 line-clamp-1 italic" title="{{ $post->admin_note }}">Note: {{ $post->admin_note }}</p>
                        @endif

                        @if($canReview)
                            <button type="button" onclick="openPostViewModal({{ $post->id }})" class="mt-2 w-full pp-btn-primary py-1.5 text-[10px] flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">rate_review</span>
                                Review
                            </button>
                        @endif

                        @if($isAdmin && $status === 'approved' && $target)
                            <form action="{{ route('posts.publish-now', $post->id) }}" method="POST" class="mt-2"
                                data-pp-confirm="This post will leave the queue and publish immediately."
                                data-pp-confirm-title="Publish now?"
                                data-pp-confirm-button="Publish">
                                @csrf
                                <button type="submit" class="w-full pp-btn-primary py-1.5 text-[10px] flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">send</span>
                                    Post now
                                </button>
                            </form>
                        @endif

                        @if($canRetry)
                            <form action="{{ route('posts.retry', $target->id) }}" method="POST" class="mt-2"
                                data-pp-confirm="Try publishing this post again right now?"
                                data-pp-confirm-title="Retry publishing"
                                data-pp-confirm-button="Retry">
                                @csrf
                                <button type="submit" class="w-full pp-btn-primary py-1.5 text-[10px] flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">refresh</span>
                                    Retry
                                </button>
                            </form>
                        @endif

                        <div class="flex items-center gap-1 mt-2 pt-2 border-t border-[#c3c6d7]/60">
                            @unless($canReview)
                                <button type="button" onclick="openPostViewModal({{ $post->id }})" class="pp-btn-secondary flex-1 text-[10px] py-1.5 px-1">
                                    View
                                </button>
                            @endunless
                            @if($canEdit)
                                <a href="{{ route('posts.edit', $post->id) }}" class="pp-btn-primary flex-1 text-[10px] py-1.5 px-1 text-center">
                                    Edit
                                </a>
                            @endif
                            @if($canDelete)
                                <form action="{{ route('posts.delete', $post->id) }}" method="POST" class="shrink-0"
                                    data-pp-confirm="This post will be permanently deleted. This cannot be undone."
                                    data-pp-confirm-title="Delete post?"
                                    data-pp-confirm-variant="danger"
                                    data-pp-confirm-button="Delete">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="pp-btn-secondary text-[10px] py-1.5 px-1.5 text-[#93000a]" title="Delete">
                                        <span class="material-symbols-outlined text-[14px]">delete</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <x-pp-pagination :paginator="$posts" :per-page="$perPage" :per-page-options="[12, 20, 40, 60]" />
    @endif

    @if($posts->isNotEmpty())
        <x-post-view-modal :posts="$posts" :is-admin="$isAdmin" />
    @endif
</x-app-layout>
