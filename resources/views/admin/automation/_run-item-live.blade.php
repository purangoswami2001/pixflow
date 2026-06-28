<div class="px-5 py-5 bg-[#fafbff] border-b border-[#e8eaf5] overflow-x-auto" data-live-section="stepper">
    @include('admin.automation._pipeline-stepper', ['item' => $item, 'pipelineSteps' => $pipelineSteps])
</div>

<div class="p-5 space-y-4 bg-white" data-live-section="details">
    @if($item->error_message)
        <div class="pp-automation-alert pp-automation-alert--danger">
            <span class="material-symbols-outlined text-[20px]">error</span>
            <p class="text-sm font-medium">{{ $item->error_message }}</p>
        </div>
    @endif

    @if(!empty($item->metadata['pipeline']))
        <details class="pp-automation-log-panel">
            <summary>Pipeline artifacts (JSON)</summary>
            <pre class="pp-automation-log-pre mt-3">{{ json_encode($item->metadata['pipeline'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endif

    @if(!empty($item->metadata['generation_steps']))
        <details class="pp-automation-log-panel">
            <summary>Generation steps ({{ count($item->metadata['generation_steps']) }})</summary>
            <div class="space-y-3 mt-4">
                @foreach($item->metadata['generation_steps'] as $log)
                    <div class="pp-automation-log-entry">
                        <div class="flex flex-wrap gap-2 items-center mb-3">
                            <span class="pp-automation-step-badge">{{ $log['step'] ?? 'step' }}</span>
                            @if(!empty($log['provider']))<span class="pp-automation-badge">{{ $log['provider'] }}</span>@endif
                            @if(!empty($log['duration_ms']))<span class="text-xs text-[#737686]">{{ $log['duration_ms'] }}ms</span>@endif
                        </div>
                        @if(!empty($log['system_prompt']))
                            <p class="text-xs font-bold text-[#434655] mb-1">System</p>
                            <pre class="pp-automation-log-pre text-[10px] mb-3">{{ $log['system_prompt'] }}</pre>
                        @endif
                        @if(!empty($log['user_prompt']))
                            <p class="text-xs font-bold text-[#434655] mb-1">User prompt</p>
                            <pre class="pp-automation-log-pre text-[10px] mb-3">{{ $log['user_prompt'] }}</pre>
                        @endif
                        @if(!empty($log['result_json']))
                            <p class="text-xs font-bold text-[#434655] mb-1">Response</p>
                            <pre class="pp-automation-log-pre text-[10px]">{{ json_encode($log['result_json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @endif
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
