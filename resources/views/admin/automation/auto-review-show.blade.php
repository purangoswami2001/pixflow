<x-app-layout>
    @php
        $decision = $report['decision'] ?? '—';
        $badge = match ($decision) {
            'approve' => 'pp-status-badge--success',
            'reject' => 'pp-status-badge--danger',
            'revision' => 'pp-status-badge--running',
            default => 'pp-status-badge--muted',
        };
        $scores = is_array($report['scores'] ?? null) ? $report['scores'] : [];
        $reasons = array_values(array_filter((array) ($report['reasons'] ?? [])));
        $checklist = array_values(array_filter((array) ($report['revision_checklist'] ?? [])));
        $gates = is_array($report['deterministic_gates'] ?? null) ? $report['deterministic_gates'] : [];
        $image = is_array($report['image_findings'] ?? null) ? $report['image_findings'] : [];
        $metaFindings = is_array($report['metadata_findings'] ?? null) ? $report['metadata_findings'] : [];
        $rawAi = is_array($report['raw_ai'] ?? null) ? $report['raw_ai'] : null;
        $confidence = isset($report['confidence']) ? (float) $report['confidence'] : null;
        $target = $post->targets->first();
        $mediaUrl = $target?->platform_media_url;
        $mediaType = $target?->media_snapshot['media_type'] ?? null;
    @endphp

    <div class="pp-automation-page">
        @include('admin.automation._nav')

        <div class="pp-auto-toolbar">
            <a href="{{ route('admin.automation.auto-review') }}" class="pp-btn-secondary text-sm py-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Auto Review
            </a>
            <a href="{{ route('posts.edit', $post->id) }}" class="pp-btn-secondary text-sm py-2">Edit post</a>
            <form action="{{ route('admin.automation.auto-review.review', $post->id) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="mode" value="sync">
                <input type="hidden" name="force" value="1">
                <button type="submit" class="pp-btn-primary text-sm py-2" @disabled(! $enabled)>Re-run agent</button>
            </form>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-6">
                <div class="pp-card p-5">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <span class="pp-status-badge {{ $badge }} text-sm px-3 py-1">{{ $decision }}</span>
                        <span class="text-sm font-semibold text-[#131b2e]">Score {{ $report['overall_score'] ?? '—' }}</span>
                        @if($confidence !== null)
                            <span class="text-sm text-[#737686]">Confidence {{ round($confidence * 100) }}%</span>
                        @endif
                        <span class="text-xs text-[#737686] ml-auto">
                            {{ isset($report['reviewed_at']) ? \Illuminate\Support\Carbon::parse($report['reviewed_at'])->toDayDateTimeString() : ($post->updated_at?->toDayDateTimeString() ?? '—') }}
                        </span>
                    </div>

                    <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686] mb-1">Review thoughts</p>
                    <div class="rounded-xl border border-[#e8eaf5] bg-[#fafbff] p-4 text-sm text-[#131b2e] whitespace-pre-wrap leading-relaxed">
                        {{ $report['admin_note'] ?: ($post->admin_note ?: 'No written review note.') }}
                    </div>

                    @if($reasons !== [])
                        <div class="mt-5">
                            <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686] mb-2">What was analyzed / reasons</p>
                            <ul class="space-y-2">
                                @foreach($reasons as $reason)
                                    <li class="flex gap-2 text-sm text-[#434655]">
                                        <span class="material-symbols-outlined text-[18px] text-[#004ac6] shrink-0 mt-0.5">analytics</span>
                                        <span>{{ $reason }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($checklist !== [])
                        <div class="mt-5">
                            <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686] mb-2">Revision checklist</p>
                            <ul class="space-y-2">
                                @foreach($checklist as $item)
                                    <li class="flex gap-2 text-sm text-[#434655]">
                                        <span class="material-symbols-outlined text-[18px] text-amber-600 shrink-0 mt-0.5">checklist</span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                @if($scores !== [])
                    <div class="pp-card p-5">
                        <p class="pp-auto-card-head-title mb-4">Score breakdown</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($scores as $key => $value)
                                @php $pct = max(0, min(100, (int) $value)); @endphp
                                <div class="rounded-xl border border-[#e8eaf5] p-3">
                                    <div class="flex justify-between text-sm mb-2">
                                        <span class="font-medium text-[#434655] capitalize">{{ str_replace('_', ' ', $key) }}</span>
                                        <span class="font-semibold text-[#131b2e]">{{ $pct }}</span>
                                    </div>
                                    <div class="pp-auto-progress h-1.5">
                                        <div class="pp-auto-progress-bar" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($gates !== [] || $image !== [] || $metaFindings !== [])
                    <div class="pp-card p-5 space-y-5">
                        <p class="pp-auto-card-head-title">Analysis details</p>

                        @if($gates !== [])
                            <div>
                                <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686] mb-2">Deterministic gates</p>
                                <div class="space-y-2">
                                    @foreach($gates as $gate)
                                        @php
                                            $sev = $gate['severity'] ?? 'info';
                                            $sevClass = match ($sev) {
                                                'reject' => 'border-red-200 bg-red-50 text-red-900',
                                                'revision' => 'border-amber-200 bg-amber-50 text-amber-900',
                                                default => 'border-[#e8eaf5] bg-[#fafbff] text-[#434655]',
                                            };
                                        @endphp
                                        <div class="rounded-lg border px-3 py-2 text-sm {{ $sevClass }}">
                                            <span class="font-semibold uppercase text-[10px] tracking-wide">{{ $sev }}</span>
                                            @if(!empty($gate['code']))
                                                <span class="font-mono text-[11px] opacity-70 ml-1">{{ $gate['code'] }}</span>
                                            @endif
                                            <p class="mt-1">{{ $gate['message'] ?? '—' }}</p>
                                            @if(!empty($gate['fix']))
                                                <p class="mt-1 text-xs opacity-80">Fix: {{ $gate['fix'] }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($image !== [])
                            <div>
                                <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686] mb-2">Image findings · score {{ $image['score'] ?? '—' }}</p>
                                @if(!empty($image['issues']))
                                    <ul class="list-disc pl-5 text-sm text-[#434655] space-y-1 mb-2">
                                        @foreach((array) $image['issues'] as $issue)
                                            <li>{{ $issue }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if(!empty($image['fixes']))
                                    <p class="text-xs font-semibold text-[#737686] mb-1">Suggested fixes</p>
                                    <ul class="list-disc pl-5 text-sm text-[#434655] space-y-1">
                                        @foreach((array) $image['fixes'] as $fix)
                                            <li>{{ $fix }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if(empty($image['issues']) && empty($image['fixes']))
                                    <p class="text-sm text-[#737686]">No image issues recorded.</p>
                                @endif
                            </div>
                        @endif

                        @if($metaFindings !== [])
                            <div>
                                <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686] mb-2">Metadata findings · score {{ $metaFindings['score'] ?? '—' }}</p>
                                @if(!empty($metaFindings['issues']))
                                    <ul class="list-disc pl-5 text-sm text-[#434655] space-y-1 mb-2">
                                        @foreach((array) $metaFindings['issues'] as $issue)
                                            <li>{{ $issue }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if(!empty($metaFindings['fixes']))
                                    <p class="text-xs font-semibold text-[#737686] mb-1">Suggested fixes</p>
                                    <ul class="list-disc pl-5 text-sm text-[#434655] space-y-1">
                                        @foreach((array) $metaFindings['fixes'] as $fix)
                                            <li>{{ $fix }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if(empty($metaFindings['issues']) && empty($metaFindings['fixes']))
                                    <p class="text-sm text-[#737686]">No metadata issues recorded.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                @if($rawAi)
                    <details class="pp-card p-5">
                        <summary class="cursor-pointer font-semibold text-sm text-[#131b2e]">Raw AI response</summary>
                        <pre class="pp-automation-log-pre mt-4 text-[11px]">{{ json_encode($rawAi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif
            </div>

            <div class="space-y-6">
                <div class="pp-card overflow-hidden">
                    <div class="pp-auto-card-head">
                        <div>
                            <p class="pp-auto-card-head-title">Post media</p>
                            <p class="pp-auto-card-head-sub">{{ $target?->provider?->value ?? '—' }} · {{ $post->status?->value ?? $post->status }}</p>
                        </div>
                    </div>
                    <div class="bg-[#f4f5fb] aspect-square flex items-center justify-center">
                        @if($mediaUrl && $mediaType === 'video')
                            <video src="{{ $mediaUrl }}" controls class="w-full h-full object-contain bg-black"></video>
                        @elseif($mediaUrl)
                            <img src="{{ $mediaUrl }}" alt="" class="w-full h-full object-contain">
                        @else
                            <span class="text-sm text-[#737686]">No media</span>
                        @endif
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        <div>
                            <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686]">Title</p>
                            <p class="text-[#131b2e]">{{ $post->title ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686]">Caption</p>
                            <p class="text-[#434655] whitespace-pre-wrap text-xs leading-relaxed">{{ $post->caption ?: '—' }}</p>
                        </div>
                        @if(!empty($post->hashtags))
                            <div>
                                <p class="text-[10px] uppercase tracking-wide font-semibold text-[#737686]">Hashtags</p>
                                <p class="text-xs text-[#004ac6]">{{ collect($post->hashtags)->map(fn ($h) => str_starts_with($h, '#') ? $h : '#'.$h)->implode(' ') }}</p>
                            </div>
                        @endif
                        <div class="text-xs text-[#737686] pt-2 border-t border-[#e8eaf5]">
                            Provider: {{ $report['provider'] ?? '—' }}
                            · Agent: {{ $report['agent'] ?? 'AutoReviewAgent' }}
                            · by {{ $report['reviewed_by'] ?? 'auto-review-agent' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
