<div class="p-4 hover:bg-[#0f172a]/[0.02] transition-colors pp-log-entry" data-log-hash="{{ $entry['hash'] }}">
    <div class="flex flex-wrap gap-2 items-center mb-2 not-italic font-sans">
        <span class="text-[#737686]">{{ $entry['at'] }}</span>
        <span class="pp-automation-step-badge">{{ $entry['message'] }}</span>
        @if(!empty($entry['context']['item_id']))
            <a href="{{ route('admin.automation.logs', ['item_id' => $entry['context']['item_id']]) }}" class="text-[#004ac6] font-semibold">#{{ $entry['context']['item_id'] }}</a>
        @endif
        @if(!empty($entry['context']['provider']))
            <span class="pp-automation-badge">{{ $entry['context']['provider'] }}</span>
        @endif
        @if(!empty($entry['context']['duration_ms']))
            <span class="text-xs text-[#737686]">{{ $entry['context']['duration_ms'] }}ms</span>
        @endif
    </div>
    @if(!empty($entry['context']))
        <details>
            <summary class="cursor-pointer text-[#004ac6] font-sans font-semibold text-xs mb-2">Expand payload</summary>
            <pre class="pp-automation-log-pre">{{ json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @elseif(strlen($entry['raw'] ?? '') > 120)
        <details>
            <summary class="cursor-pointer text-[#737686] font-sans text-xs">Full entry</summary>
            <pre class="pp-automation-log-pre mt-2">{{ $entry['raw'] }}</pre>
        </details>
    @endif
</div>
