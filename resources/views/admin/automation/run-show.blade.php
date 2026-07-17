<x-app-layout>
    <div class="pp-automation-page" id="pp-run-detail"
         data-run-id="{{ $run->id }}"
         data-feed-url="{{ route('admin.automation.runs.feed', $run) }}"
         data-retry-url="{{ url('admin/automation/run-items') }}"
         data-post-edit-url="{{ url('posts') }}"
         data-logs-url="{{ route('admin.automation.logs') }}">
        @include('admin.automation._nav')

        <div class="pp-auto-toolbar">
            <a href="{{ route('admin.automation.runs') }}" class="pp-btn-secondary text-sm py-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Pipeline
            </a>
            <span class="pp-live-indicator" id="pp-run-live-indicator" title="Auto-updating">
                <span class="pp-live-indicator-dot"></span>
                Live
            </span>
            <a href="{{ route('admin.automation.logs', ['run_id' => $run->id]) }}" class="pp-btn-secondary text-sm py-2">
                <span class="material-symbols-outlined text-[18px]">terminal</span>
                AI Logs
            </a>
        </div>

        @php
            $pct = $run->planned_count > 0 ? round(($run->created_count / $run->planned_count) * 100) : 0;
            $statusClass = match($run->status) {
                'completed' => 'pp-status-badge--success',
                'failed' => 'pp-status-badge--danger',
                'partial' => 'pp-status-badge--running',
                'running' => 'pp-status-badge--running',
                default => 'pp-status-badge--muted',
            };
        @endphp

        <div class="pp-card p-5 mb-6 bg-gradient-to-r from-[#faf8ff] to-white" id="pp-run-summary">
            <div class="flex flex-wrap justify-between gap-4 items-center mb-4">
                <div class="flex items-center gap-3">
                    <span class="pp-status-badge {{ $statusClass }} text-sm px-3 py-1" data-run-status>{{ $run->status === 'completed' ? 'success' : $run->status }}</span>
                    <span class="text-sm text-[#737686]" data-run-started>Started {{ $run->started_at?->diffForHumans() ?? 'not yet' }}</span>
                </div>
                <p class="font-display font-bold text-2xl text-[#131b2e]">
                    <span data-run-created>{{ $run->created_count }}</span><span class="text-[#737686] font-normal text-lg"> / <span data-run-planned>{{ $run->planned_count }}</span></span>
                </p>
            </div>
            <div class="pp-auto-progress h-2">
                <div class="pp-auto-progress-bar" data-run-progress style="width: {{ $pct }}%"></div>
            </div>
            <p class="text-sm text-red-600 mt-3 font-medium {{ $run->failed_count > 0 ? '' : 'hidden' }}" data-run-failed-wrap>
                <span data-run-failed>{{ $run->failed_count }}</span> item(s) failed — use Retry below
            </p>
        </div>

        <div class="space-y-5" id="pp-run-items">
            @forelse($run->items as $item)
                @include('admin.automation._run-item', ['item' => $item, 'pipelineSteps' => $pipelineSteps, 'run' => $run])
            @empty
                <div class="pp-card">
                    @include('admin.automation._empty', ['icon' => 'inbox', 'title' => 'No items', 'description' => 'This run has no recorded items.'])
                </div>
            @endforelse
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/automation-live.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.AutomationLive) {
                    window.AutomationLive.initRunDetail(document.getElementById('pp-run-detail'));
                }
            });
        </script>
    @endpush
</x-app-layout>
