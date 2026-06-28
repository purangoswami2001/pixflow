<x-app-layout>
    <div class="pp-automation-page" id="pp-log-stream"
         data-feed-url="{{ route('admin.automation.logs.feed') }}"
         data-logs-page-url="{{ route('admin.automation.logs') }}"
         data-run-id="{{ $runId ?? '' }}"
         data-item-id="{{ $itemId ?? '' }}">
        <x-pp-page-header title="AI Generation Logs" description="Full prompts and pipeline events from the automation log file.">
            <x-slot name="breadcrumb"><span>Admin</span><span>/</span><span class="text-[#004ac6]">AI Logs</span></x-slot>
            <x-slot name="actions">
                <span class="pp-live-indicator" id="pp-log-live-indicator" title="Auto-updating">
                    <span class="pp-live-indicator-dot"></span>
                    Live
                </span>
                <a href="{{ route('admin.automation.runs') }}" class="pp-btn-secondary text-sm py-2">Pipeline runs</a>
            </x-slot>
        </x-pp-page-header>
        @include('admin.automation._nav')

        <form method="GET" class="pp-card p-5 mb-6" id="pp-log-filters">
            <p class="text-sm font-semibold text-[#131b2e] mb-3">Filter logs</p>
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="pp-label">Run ID</label>
                    <input type="number" name="run_id" value="{{ $runId }}" class="pp-input w-36" placeholder="12">
                </div>
                <div>
                    <label class="pp-label">Item ID</label>
                    <input type="number" name="item_id" value="{{ $itemId }}" class="pp-input w-36" placeholder="30">
                </div>
                <button type="submit" class="pp-btn-primary text-sm py-2">Apply filter</button>
                @if($runId || $itemId)
                    <a href="{{ route('admin.automation.logs') }}" class="pp-btn-secondary text-sm py-2">Clear</a>
                @endif
            </div>
        </form>

        <div class="pp-card overflow-hidden">
            <div class="pp-auto-card-head">
                <div>
                    <p class="pp-auto-card-head-title">Log stream</p>
                    <p class="pp-auto-card-head-sub" id="pp-log-meta">{{ $logFileName }} · {{ count($lines) }} entries</p>
                </div>
                <span class="pp-automation-badge pp-live-badge">Auto-refresh</span>
            </div>
            <div class="divide-y divide-[#e8eaf5] max-h-[70vh] overflow-y-auto font-mono text-xs" id="pp-log-entries">
                @forelse($lines as $entry)
                    @include('admin.automation._log-entry', ['entry' => $entry])
                @empty
                    <div id="pp-log-empty">
                        @include('admin.automation._empty', [
                            'icon' => 'terminal',
                            'title' => 'No log entries',
                            'description' => 'Run a campaign with queue:work active to generate logs.',
                        ])
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/automation-live.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.AutomationLive) {
                    window.AutomationLive.initLogStream(document.getElementById('pp-log-stream'));
                }
            });
        </script>
    @endpush
</x-app-layout>
