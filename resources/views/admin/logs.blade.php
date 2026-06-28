<x-app-layout>
    <x-pp-page-header title="System Logs" description="Review API transactions, payloads, and publishing audit trail.">
        <x-slot name="breadcrumb">
            <span>Admin</span><span>/</span><span class="text-[#004ac6]">Publish Logs</span>
        </x-slot>
        <x-slot name="actions">
            <form action="{{ route('admin.logs.clear') }}" method="POST"
                data-pp-confirm="All publish logs will be cleared from the system."
                data-pp-confirm-title="Clear all logs?"
                data-pp-confirm-variant="danger"
                data-pp-confirm-button="Clear all">
                @csrf
                <button type="submit" class="pp-btn-secondary text-[#ba1a1a] border-[#ba1a1a]/30 hover:bg-[#ffdad6]">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                    Clear Logs
                </button>
            </form>
        </x-slot>
    </x-pp-page-header>

    <div class="border-b border-[#c3c6d7] mb-6 flex overflow-x-auto">
        @foreach(['all' => 'All Logs', 'success' => 'Successful', 'failed' => 'Failed'] as $key => $lbl)
            <a href="{{ route('admin.logs', ['status' => $key]) }}" class="pp-tab {{ $statusFilter === $key ? 'active' : '' }}">{{ $lbl }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 space-y-2 max-h-[640px] overflow-y-auto">
            @if($logs->isEmpty())
                <div class="pp-card p-12 text-center text-[#737686]">
                    <span class="material-symbols-outlined text-5xl mb-3 block opacity-40">terminal</span>
                    <p class="text-sm">No publish logs matching this filter.</p>
                </div>
            @else
                @foreach($logs as $log)
                    <a href="{{ route('admin.logs', ['status' => $statusFilter, 'selected_log' => $log->id]) }}"
                       class="pp-card p-4 flex items-center justify-between gap-3 hover:shadow-md transition-shadow no-underline {{ $selectedLog && $selectedLog->id == $log->id ? 'ring-2 ring-[#004ac6]' : '' }}">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $log->status === 'success' ? 'bg-emerald-500' : 'bg-[#ba1a1a]' }}"></span>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-[#131b2e] truncate">{{ $log->postTarget->post->title ?? 'Campaign' }}</p>
                                <p class="text-[11px] text-[#737686]">{{ $log->postTarget->socialAccount?->user?->name ?? ($log->postTarget->resolvedPublishLabel()) }} • {{ ucfirst($log->provider->value) }}</p>
                            </div>
                        </div>
                        <span class="text-[11px] text-[#737686] shrink-0">{{ $log->created_at->format('M d, H:i:s') }}</span>
                    </a>
                @endforeach
            @endif
        </div>

        <div class="lg:col-span-2">
            <div class="pp-card p-6 sticky top-24 max-h-[640px] overflow-y-auto">
                @if($selectedLog)
                    <div class="flex justify-between items-center border-b border-[#c3c6d7] pb-3 mb-4">
                        <h3 class="font-display font-semibold text-[#131b2e]">Payload Inspector</h3>
                        <a href="{{ route('admin.logs', ['status' => $statusFilter]) }}" class="text-[#737686] hover:text-[#131b2e]">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    </div>
                    <div class="space-y-4 text-sm">
                        <div>
                            <p class="text-[10px] font-semibold uppercase text-[#737686] mb-1">Status</p>
                            <span class="pp-status-chip {{ $selectedLog->status === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-[#ffdad6] text-[#93000a]' }}">{{ $selectedLog->status }}</span>
                        </div>
                        @if($selectedLog->error_message)
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-[#737686] mb-1">Error</p>
                                <div class="pp-alert-error text-xs">{{ $selectedLog->error_message }}</div>
                            </div>
                        @endif
                        <div>
                            <p class="text-[10px] font-semibold uppercase text-[#737686] mb-1">API Request</p>
                            <pre class="bg-[#131b2e] text-[#eef0ff] p-3 rounded-lg text-[10px] overflow-x-auto">{{ json_encode($selectedLog->request_payload, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase text-[#737686] mb-1">API Response</p>
                            <pre class="bg-[#131b2e] text-[#eef0ff] p-3 rounded-lg text-[10px] overflow-x-auto">{{ json_encode($selectedLog->response_payload, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                @else
                    <div class="text-center py-12 text-[#737686]">
                        <span class="material-symbols-outlined text-4xl mb-2 block opacity-40">code</span>
                        <p class="text-sm">Select a log entry to inspect payloads.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
