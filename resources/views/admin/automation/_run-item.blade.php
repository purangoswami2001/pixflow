<article class="pp-card overflow-hidden" data-run-item-id="{{ $item->id }}" data-run-item-revision="{{ \App\Support\AutomationRunItemPresenter::revision($item) }}">
    <div class="px-5 py-4 border-b border-[#e8eaf5] flex flex-wrap justify-between gap-3 items-start bg-white" data-live-section="header">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                @include('admin.automation._platform-chip', ['platform' => $item->platform])
                <span class="text-xs text-[#737686] capitalize">{{ $item->topic_type }}</span>
                <span class="pp-status-badge {{ $item->status === 'post_created' ? 'pp-status-badge--success' : ($item->status === 'failed' ? 'pp-status-badge--danger' : 'pp-status-badge--running') }}" data-item-status-badge>
                    {{ str_replace('_', ' ', $item->status) }}
                </span>
            </div>
            <p class="font-semibold text-[#131b2e] leading-snug">{{ $item->topic?->title ?? '—' }}</p>
        </div>
        <div class="flex flex-wrap gap-2" data-item-actions>
            @if($item->status === 'failed')
                <form action="{{ route('admin.automation.run-items.retry', $item) }}" method="POST">
                    @csrf
                    <button type="submit" class="pp-btn-primary text-xs py-2 px-3">Retry</button>
                </form>
            @endif
            @if($item->post_id)
                <a href="{{ route('posts.edit', $item->post_id) }}" class="pp-btn-secondary text-xs py-2 px-3">Open post</a>
            @endif
            <a href="{{ route('admin.automation.logs', ['run_id' => $run->id, 'item_id' => $item->id]) }}" class="pp-btn-secondary text-xs py-2 px-3">Logs</a>
        </div>
    </div>

    <div data-run-item-live>
        @include('admin.automation._run-item-live', ['item' => $item, 'pipelineSteps' => $pipelineSteps])
    </div>
</article>
