<x-app-layout>
    <div class="pp-automation-page" id="pp-log-stream"
         data-feed-url="{{ route('admin.automation.logs.feed') }}"
         data-logs-page-url="{{ route('admin.automation.logs') }}"
         data-run-id="{{ $runId ?? '' }}"
         data-item-id="{{ $itemId ?? '' }}">
        @include('admin.automation._nav')

        @include('admin.automation._mobile-bar', [
            'default' => 'stream',
            'back' => [
                'url' => route('admin.automation.runs'),
                'label' => 'Pipeline',
            ],
            'tabs' => [
                ['id' => 'stream', 'label' => 'Stream', 'icon' => 'terminal'],
                ['id' => 'filters', 'label' => 'Filter', 'icon' => 'filter_alt'],
            ],
            'radioName' => 'pp-auto-logs',
        ])

        <div class="pp-auto-panel" data-pp-auto-panel-id="filters">
            <form method="GET" class="pp-card p-4 sm:p-5 mb-0 sm:mb-6" id="pp-log-filters">
                <p class="text-sm font-semibold text-[var(--pp-text)] mb-3">Filter logs</p>
                <div class="flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4 items-stretch sm:items-end">
                    <div class="flex-1 min-w-0">
                        <label class="pp-label">Run ID</label>
                        <input type="number" name="run_id" value="{{ $runId }}" class="pp-input w-full" placeholder="12">
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="pp-label">Item ID</label>
                        <input type="number" name="item_id" value="{{ $itemId }}" class="pp-input w-full" placeholder="30">
                    </div>
                    <button type="submit" class="pp-btn-primary text-sm py-2.5 justify-center">Apply filter</button>
                    @if($runId || $itemId)
                        <a href="{{ route('admin.automation.logs') }}" class="pp-btn-secondary text-sm py-2.5 justify-center">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="pp-auto-panel is-open" data-pp-auto-panel-id="stream">
            <div class="pp-card overflow-hidden">
                <div class="pp-auto-card-head">
                    <div>
                        <p class="pp-auto-card-head-title">Log stream</p>
                        <p class="pp-auto-card-head-sub" id="pp-log-meta">{{ $logFileName }} · {{ count($lines) }} entries</p>
                    </div>
                    <span class="pp-automation-badge pp-live-badge">Auto-refresh</span>
                </div>
                <div class="divide-y divide-[var(--pp-border-soft)] max-h-[70vh] overflow-y-auto font-mono text-xs" id="pp-log-entries">
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
