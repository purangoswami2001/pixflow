<x-app-layout>
    <div class="pp-automation-page">
        @include('admin.automation._nav')

        <div class="pp-auto-toolbar">
            <a href="{{ route('admin.automation.logs') }}" class="pp-btn-secondary text-sm py-2">
                <span class="material-symbols-outlined text-[18px]">terminal</span>
                AI Logs
            </a>
        </div>

        <div class="pp-card overflow-hidden">
            @if($runs->total() === 0)
                @include('admin.automation._empty', [
                    'icon' => 'timeline',
                    'title' => 'No pipeline runs yet',
                    'description' => 'Create a campaign and click Run now to start generating posts.',
                    'actionLabel' => 'Go to campaigns',
                    'actionUrl' => route('admin.automation.campaigns'),
                ])
            @else
                <div class="overflow-x-auto">
                    <table class="pp-automation-table">
                        <thead>
                            <tr>
                                <th>Run</th>
                                <th>Campaign</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Started</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="pp-runs-list" data-pp-paginated-list>
                            @foreach($runs as $run)
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
                                <tr>
                                    <td><span class="font-mono font-bold text-[#004ac6]">#{{ $run->id }}</span></td>
                                    <td class="font-medium">{{ $run->campaign?->name }}</td>
                                    <td><span class="pp-status-badge {{ $statusClass }}">{{ $run->status === 'completed' ? 'success' : $run->status }}</span></td>
                                    <td>
                                        <div class="min-w-[8rem]">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-sm font-semibold">{{ $run->created_count }}/{{ $run->planned_count }}</span>
                                                @if($run->failed_count > 0)
                                                    <span class="text-xs text-red-600">({{ $run->failed_count }} failed)</span>
                                                @endif
                                            </div>
                                            <div class="pp-auto-progress">
                                                <div class="pp-auto-progress-bar" style="width: {{ $pct }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-xs text-[#737686]">{{ $run->started_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.automation.runs.show', $run) }}" class="pp-btn-secondary text-xs py-1.5 px-3">Details →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 pb-4">
                    <x-pp-pagination :paginator="$runs" :per-page="$perPage" list-target="#pp-runs-list" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
