@php
    $guideTitle = $title ?? 'How this works';
    $guideSteps = $steps ?? [];
    $collapsed = $collapsed ?? false;
    $variant = $variant ?? 'default';
@endphp
@if($variant === 'wizard' && count($guideSteps))
    <div class="pp-auto-wizard mb-8">
        @foreach($guideSteps as $i => $step)
            <div class="pp-auto-wizard-step">
                <div class="pp-auto-wizard-num">{{ $i + 1 }}</div>
                <div>
                    <p class="pp-auto-wizard-title">{{ $step['title'] ?? '' }}</p>
                    @if(!empty($step['text']))
                        <p class="pp-auto-wizard-text">{{ $step['text'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <details class="pp-auto-guide mb-6" {{ $collapsed ? '' : 'open' }}>
        <summary class="pp-auto-guide-summary">
            <div class="pp-auto-guide-icon">
                <span class="material-symbols-outlined">tips_and_updates</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="pp-auto-guide-title">{{ $guideTitle }}</p>
                @if($collapsed)
                    <p class="pp-auto-guide-hint">Tap to expand guide</p>
                @endif
            </div>
            <span class="material-symbols-outlined pp-auto-guide-chevron">expand_more</span>
        </summary>
        @if(count($guideSteps))
            <ol class="pp-auto-guide-steps">
                @foreach($guideSteps as $step)
                    <li>
                        @if(!empty($step['title']))
                            <strong>{{ $step['title'] }}</strong>
                            @if(!empty($step['text']))<span> — {{ $step['text'] }}</span>@endif
                        @else
                            {{ $step }}
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </details>
@endif
