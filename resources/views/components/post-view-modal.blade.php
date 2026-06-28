@props(['posts', 'isAdmin' => false])

@php
    $platformFieldsService = app(\App\Services\Posts\PlatformPostFieldsService::class);
    $channelLabels = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'pinterest' => 'Pinterest',
    ];
    $postTypeLabels = [
        'facebook_post' => 'Facebook Post',
        'instagram_post' => 'Instagram Post',
        'instagram_story' => 'Instagram Story',
        'instagram_reel' => 'Instagram Reel',
        'pinterest_pin' => 'Pinterest Pin',
    ];

    $postsJson = $posts->mapWithKeys(function ($post) use ($isAdmin, $platformFieldsService, $channelLabels, $postTypeLabels) {
        $target = $post->targets->first();
        $status = $post->status->value;
        $user = auth()->user();
        $canEdit = $post->canBeEditedBy($user);
        $canDelete = $post->canBeDeletedBy($user);
        $targetFailed = $target && $target->status->value === 'failed';
        $canRetry = $status === 'failed' && $targetFailed
            && ($isAdmin || $post->user_id === $user->id);
        $canReview = $isAdmin && $status === 'pending_approval';

        return [$post->id => [
            'id' => $post->id,
            'title' => $post->title,
            'caption' => $post->caption,
            'status' => $status,
            'hashtags' => $post->hashtags ?? [],
            'admin_note' => $post->admin_note,
            'created_at' => $post->created_at->format('M d, Y g:i A'),
            'author' => $post->user->name ?? 'Unknown',
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'delete_url' => route('posts.delete', $post->id),
            'can_review' => $canReview,
            'review_url' => route('posts.review', $post->id),
            'can_publish_now' => $isAdmin && $status === 'approved',
            'publish_now_url' => route('posts.publish-now', $post->id),
            'can_retry' => $canRetry,
            'retry_url' => $target ? route('posts.retry', $target->id) : null,
            'edit_url' => route('posts.edit', $post->id),
            'targets' => $post->targets->map(function ($t) use ($platformFieldsService, $channelLabels, $postTypeLabels) {
                $mediaDims = $t->displayDimensions();
                $provider = $t->resolvedProvider();

                return [
                    'provider' => $provider,
                    'channel_label' => $channelLabels[$provider] ?? ucfirst($provider),
                    'account' => $t->resolvedAccountHandle(),
                    'account_name' => $t->socialAccount?->name ?? ($t->connection_snapshot['account_name'] ?? 'Account'),
                    'destination_name' => $t->socialDestination?->name ?? ($t->connection_snapshot['destination_name'] ?? null),
                    'publish_label' => $t->resolvedPublishLabel(),
                    'post_type' => $t->post_type->value,
                    'post_type_label' => $postTypeLabels[$t->post_type->value] ?? ucfirst(str_replace('_', ' ', $t->post_type->value)),
                    'media_type' => $t->archivedMediaType(),
                    'scheduled_at_display' => $t->scheduled_at
                        ? $t->scheduled_at->timezone($t->timezone ?: 'UTC')->format('D, M j, Y g:i A') . ' (' . ($t->timezone ?: 'UTC') . ')'
                        : null,
                    'status' => $t->status->value,
                    'media_url' => $t->resolvedMediaUrl(),
                    'media_type_mime' => $t->resolvedMimeType(),
                    'media_width' => $mediaDims['width'],
                    'media_height' => $mediaDims['height'],
                    'preset_label' => $t->archivedPresetLabel(),
                    'error_message' => $t->error_message,
                    'platform_fields' => $platformFieldsService->displaySummary($t->platform_payload, $provider),
                ];
            })->values()->all(),
            'metadata' => $post->metadata ?? [],
        ]];
    });
    $statusLabels = [
        'published' => 'Published',
        'approved' => 'Approved / Queued',
        'pending_approval' => 'Pending Approval',
        'needs_revision' => 'Needs Revision',
        'rejected' => 'Rejected',
        'draft' => 'Draft',
        'failed' => 'Failed',
    ];
@endphp

<div id="postViewModal" class="pp-modal hidden" aria-hidden="true">
    <div class="pp-modal-backdrop" onclick="closePostViewModal()"></div>
    <div class="pp-modal-panel">
            <div class="px-5 py-4 border-b border-[#c3c6d7] flex items-start justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold text-[#737686] uppercase tracking-wide mb-0.5" id="postViewEyebrow">Post details</p>
                    <h3 id="postViewTitle" class="font-display text-base font-semibold text-[#131b2e] truncate"></h3>
                    <p id="postViewMeta" class="text-[10px] text-[#737686] mt-0.5"></p>
                </div>
                <button type="button" onclick="closePostViewModal()" class="p-1.5 hover:bg-[#f2f3ff] rounded-full text-[#737686] shrink-0">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto" id="postViewBody"></div>
            <div class="px-5 py-3 border-t border-[#c3c6d7] flex flex-wrap gap-2 shrink-0 bg-[#faf8ff]" id="postViewActions"></div>
    </div>
</div>

@push('scripts')
<script>
    const postDetailsData = @json($postsJson);
    const postStatusLabels = @json($statusLabels);

    const postTypeLabels = {
        facebook_post: 'Facebook Post',
        instagram_post: 'Instagram Post',
        instagram_story: 'Instagram Story',
        instagram_reel: 'Instagram Reel',
        pinterest_pin: 'Pinterest Pin',
    };

    const channelAccent = {
        facebook: 'text-[#1877F2] bg-[#1877F2]/10 border-[#1877F2]/25',
        instagram: 'text-[#E4405F] bg-gradient-to-r from-[#833AB4]/10 via-[#E4405F]/10 to-[#FCAF45]/10 border-[#E4405F]/25',
        pinterest: 'text-[#BD081C] bg-[#BD081C]/10 border-[#BD081C]/25',
    };

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function mediaFrameStyle(t, maxHeight = '520px') {
        const w = t.media_width || 4;
        const h = t.media_height || 3;
        return `aspect-ratio: ${w} / ${h}; max-height: ${maxHeight}; width: 100%;`;
    }

    function mediaElement(t) {
        if (!t.media_url) {
            return `<div class="pp-media-frame" style="${mediaFrameStyle(t, '280px')}"><span class="material-symbols-outlined text-3xl text-[#737686]">text_fields</span></div>`;
        }
        const asset = t.media_type_mime && t.media_type_mime.startsWith('video/')
            ? `<video src="${t.media_url}" class="pp-media-frame__asset" controls playsinline></video>`
            : `<img src="${t.media_url}" class="pp-media-frame__asset cursor-zoom-in" alt="" data-pp-lightbox="${t.media_url}" title="View full image">`;
        return `<div class="pp-media-frame" style="${mediaFrameStyle(t)}">${asset}</div>`;
    }

    function buildFeedPreview(post, t) {
        const hashtags = (post.hashtags || []).join(' ');
        const caption = escapeHtml(post.caption) + (hashtags ? ' <span class="text-[#004ac6]">' + escapeHtml(hashtags) + '</span>' : '');
        const account = escapeHtml(t.publish_label || t.account);
        const accountName = escapeHtml(t.account_name);
        const initials = accountName.substring(0, 1).toUpperCase();
        const media = mediaElement(t);

        if (t.provider === 'instagram') {
            return `
                <div class="feed-preview feed-preview-instagram">
                    <div class="px-3 py-2.5 flex items-center gap-2 border-b border-[#efefef]">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-[#833AB4] via-[#E4405F] to-[#FCAF45] p-[2px]">
                            <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-[11px] font-bold text-[#E4405F]">${initials}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-[#262626] truncate">${account}</p>
                            <p class="text-[10px] text-[#8e8e8e]">${escapeHtml(t.post_type_label || postTypeLabels[t.post_type] || 'Post')}</p>
                        </div>
                        <span class="material-symbols-outlined text-[#262626] text-[18px]">more_horiz</span>
                    </div>
                    <div class="bg-black">${media}</div>
                    <div class="px-3 py-2 flex gap-3 text-[#262626]">
                        <span class="material-symbols-outlined text-[22px]">favorite_border</span>
                        <span class="material-symbols-outlined text-[22px]">chat_bubble_outline</span>
                        <span class="material-symbols-outlined text-[22px]">send</span>
                        <span class="material-symbols-outlined text-[22px] ml-auto">bookmark_border</span>
                    </div>
                    <div class="px-3 pb-3 text-xs text-[#262626] leading-relaxed">
                        <span class="font-semibold">${account}</span> ${caption}
                    </div>
                </div>`;
        }

        if (t.provider === 'pinterest') {
            return `
                <div class="feed-preview feed-preview-pinterest">
                    <div class="bg-[#efefef]">${media}</div>
                    <div class="p-3">
                        <p class="text-sm font-semibold text-[#111] mb-1 line-clamp-2">${escapeHtml(post.title)}</p>
                        <p class="text-xs text-[#111] line-clamp-3 mb-2">${caption}</p>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-[#BD081C]/10 text-[#BD081C] flex items-center justify-center text-[10px] font-bold">${initials}</div>
                            <span class="text-xs font-semibold text-[#111]">${account}</span>
                        </div>
                    </div>
                </div>`;
        }

        return `
            <div class="feed-preview feed-preview-facebook">
                <div class="px-3 py-2.5 flex items-center gap-2">
                    <div class="w-9 h-9 rounded-full bg-[#1877F2] text-white flex items-center justify-center text-xs font-bold">${initials}</div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-[#050505] truncate">${accountName}</p>
                        <p class="text-[10px] text-[#65676b]">${escapeHtml(post.created_at)} · <span class="material-symbols-outlined text-[11px] align-middle">public</span></p>
                    </div>
                    <span class="material-symbols-outlined text-[#65676b]">more_horiz</span>
                </div>
                <p class="px-3 pb-2 text-sm text-[#050505] whitespace-pre-wrap">${caption}</p>
                <div class="bg-[#f0f2f5]">${media}</div>
                <div class="px-3 py-2 flex justify-around border-t border-[#dadde1] text-[#65676b] text-xs">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">thumb_up</span> Like</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">chat_bubble_outline</span> Comment</span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">share</span> Share</span>
                </div>
            </div>`;
    }

    function buildDetailsPanel(post, t) {
        const rows = [];

        rows.push(`<div class="flex flex-wrap gap-2 mb-3">
            <span class="pp-status-chip text-[10px] border ${channelAccent[t.provider] || 'bg-[#f2f3ff] text-[#434655] border-[#c3c6d7]'}">${escapeHtml(t.channel_label || t.provider)}</span>
            <span class="pp-status-chip text-[10px] bg-[#f2f3ff] text-[#434655]">${escapeHtml(t.post_type_label || t.post_type)}</span>
            ${t.media_type ? `<span class="pp-status-chip text-[10px] bg-[#f2f3ff] text-[#434655]">${escapeHtml(t.media_type)}</span>` : ''}
        </div>`);

        rows.push(`<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div><p class="text-[10px] font-semibold uppercase text-[#737686] mb-0.5">Account</p><p class="text-[#131b2e]">${escapeHtml(t.publish_label || t.account)}</p></div>
            ${t.destination_name ? `<div><p class="text-[10px] font-semibold uppercase text-[#737686] mb-0.5">${t.provider === 'pinterest' ? 'Board' : 'Destination'}</p><p class="text-[#131b2e]">${escapeHtml(t.destination_name)}</p></div>` : ''}
            ${t.provider === 'pinterest' ? `<div><p class="text-[10px] font-semibold uppercase text-[#737686] mb-0.5">Pin title</p><p class="text-[#131b2e]">${escapeHtml(post.title)}</p></div>` : ''}
            <div><p class="text-[10px] font-semibold uppercase text-[#737686] mb-0.5">Status</p><p class="text-[#131b2e]">${escapeHtml(postStatusLabels[post.status] || post.status)}</p></div>
        </div>`);

        rows.push(`<div class="mt-3"><p class="text-[10px] font-semibold uppercase text-[#737686] mb-1">Caption</p><p class="text-xs text-[#434655] whitespace-pre-wrap leading-relaxed">${escapeHtml(post.caption)}</p></div>`);

        if ((post.hashtags || []).length) {
            rows.push(`<div class="mt-3"><p class="text-[10px] font-semibold uppercase text-[#737686] mb-1">Hashtags</p><p class="text-xs text-[#004ac6]">${escapeHtml((post.hashtags || []).join(' '))}</p></div>`);
        }

        if (t.preset_label) {
            rows.push(`<p class="text-xs text-[#737686] mt-3"><span class="font-semibold">Media size:</span> ${escapeHtml(t.preset_label)} (${t.media_width}×${t.media_height})</p>`);
        }

        if (t.scheduled_at_display) {
            rows.push(`<p class="text-xs text-[#434655] mt-2"><span class="font-semibold">Scheduled:</span> ${escapeHtml(t.scheduled_at_display)}</p>`);
        }

        if ((t.platform_fields || []).length) {
            const fieldsHtml = t.platform_fields.map(f =>
                `<div class="py-2 border-b border-[#c3c6d7]/50 last:border-0">
                    <p class="text-[10px] font-semibold text-[#737686]">${escapeHtml(f.label)}</p>
                    <p class="text-xs text-[#131b2e] break-words whitespace-pre-wrap">${escapeHtml(f.value)}</p>
                </div>`
            ).join('');
            rows.push(`<div class="mt-4"><p class="text-[10px] font-semibold uppercase text-[#737686] mb-2">${escapeHtml(t.channel_label)} settings</p><div class="pp-card p-3 bg-[#faf8ff]">${fieldsHtml}</div></div>`);
        }

        if (t.error_message) {
            rows.push(`<div class="mt-3 p-3 rounded-lg bg-[#ffdad6] text-[#93000a] text-xs">${escapeHtml(t.error_message)}</div>`);
        }

        if (post.admin_note) {
            rows.push(`<div class="mt-3 p-3 bg-[#f2f3ff] rounded-lg border border-[#c3c6d7] text-xs italic text-[#434655]"><span class="font-semibold not-italic">Admin note:</span> ${escapeHtml(post.admin_note)}</div>`);
        }

        return `<div class="p-4 space-y-1">${rows.join('')}</div>`;
    }

    function buildReviewForm(post) {
        return `
            <div class="p-4 border-t border-[#c3c6d7] bg-[#fff8e6]">
                <p class="text-xs font-semibold text-[#131b2e] mb-3 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-amber-600">rate_review</span>
                    Admin review
                </p>
                <form action="${post.review_url}" method="POST" class="space-y-3">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <textarea name="admin_note" rows="2" class="pp-input w-full text-sm" placeholder="Optional note for the creator…"></textarea>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="action" value="approve" class="pp-btn-primary text-sm py-2 px-4 flex-1 min-w-[100px]">Approve</button>
                        <button type="submit" name="action" value="revision" class="pp-btn-secondary text-sm py-2 px-4 flex-1 min-w-[100px]">Request revision</button>
                        <button type="submit" name="action" value="reject" class="pp-btn-secondary text-sm py-2 px-4 text-[#93000a] border-[#ffdad6] flex-1 min-w-[100px]">Reject</button>
                    </div>
                </form>
            </div>`;
    }

    function openPostViewModal(postId) {
        const post = postDetailsData[postId];
        if (!post) return;

        const t = (post.targets || [])[0] || { provider: 'facebook', account: '—', account_name: 'Account', channel_label: 'Facebook' };

        document.getElementById('postViewEyebrow').textContent = post.can_review ? 'Review post' : 'Post details';
        document.getElementById('postViewTitle').textContent = post.title;
        document.getElementById('postViewMeta').textContent =
            `${post.author} · ${postStatusLabels[post.status] || post.status} · ${escapeHtml(t.channel_label || t.provider)}`;

        document.getElementById('postViewBody').innerHTML = `
            <div class="p-4 bg-[#f8fafc] border-b border-[#c3c6d7]">
                ${buildFeedPreview(post, t)}
            </div>
            ${buildDetailsPanel(post, t)}
            ${post.can_review ? buildReviewForm(post) : ''}`;

        let actionsHtml = `<button type="button" onclick="closePostViewModal()" class="pp-btn-secondary flex-1 text-sm py-2 min-w-[80px]">Close</button>`;

        if (post.can_delete) {
            actionsHtml = `
                <form action="${post.delete_url}" method="POST" class="shrink-0"
                    data-pp-confirm="This post will be permanently deleted. This cannot be undone."
                    data-pp-confirm-title="Delete post?"
                    data-pp-confirm-variant="danger"
                    data-pp-confirm-button="Delete">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="pp-btn-secondary text-sm py-2 px-3 text-[#93000a] border-[#ffdad6] flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">delete</span> Delete
                    </button>
                </form>` + actionsHtml;
        }

        if (post.can_publish_now) {
            actionsHtml = `
                <form action="${post.publish_now_url}" method="POST" class="flex-1 min-w-[120px]"
                    data-pp-confirm="This post will publish immediately."
                    data-pp-confirm-title="Publish now?"
                    data-pp-confirm-button="Publish">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="pp-btn-primary w-full text-sm py-2 flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">send</span> Post now
                    </button>
                </form>` + actionsHtml;
        }

        if (post.can_retry) {
            actionsHtml = `
                <form action="${post.retry_url}" method="POST" class="flex-1 min-w-[120px]"
                    data-pp-confirm="Try publishing this post again right now?"
                    data-pp-confirm-title="Retry publishing"
                    data-pp-confirm-button="Retry">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="pp-btn-primary w-full text-sm py-2 flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">refresh</span> Retry
                    </button>
                </form>` + actionsHtml;
        }

        if (post.can_edit) {
            actionsHtml = `<a href="${post.edit_url}" class="pp-btn-primary flex-1 text-sm py-2 text-center min-w-[100px]">Edit post</a>` + actionsHtml;
        }

        document.getElementById('postViewActions').innerHTML = actionsHtml;

        document.getElementById('postViewModal').classList.remove('hidden');
        requestAnimationFrame(() => document.getElementById('postViewModal').classList.add('is-open'));
        document.body.classList.add('pp-modal-open');
    }

    function closePostViewModal() {
        const modal = document.getElementById('postViewModal');
        modal.classList.remove('is-open');
        document.body.classList.remove('pp-modal-open');
        setTimeout(() => modal.classList.add('hidden'), 220);
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closePostViewModal();
    });
</script>
@endpush
