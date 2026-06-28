@php
    $pipeline = $item->metadata['pipeline'] ?? [];
    $steps = $pipelineSteps ?? \App\Support\AutomationPromptSchema::pipelineSteps();
    $activeStep = \App\Support\AutomationRunItemPresenter::activeStep($item);
@endphp
<div class="pp-pipeline-stepper">
    @foreach($steps as $i => $step)
        @php
            $key = $step['key'];
            $done = match ($key) {
                'idea' => !empty($pipeline['idea']),
                'copy' => !empty($pipeline['copy']),
                'image_prompt' => !empty($pipeline['image_prompt']),
                'design_prompt' => !empty($pipeline['design_prompt']) || !empty($item->design_prompt),
                'image' => !empty($pipeline['image']),
                'media_asset_id' => !empty($pipeline['media_asset_id']),
                default => false,
            };
            $isActive = $activeStep === $key;
        @endphp
        <div class="pp-pipeline-step {{ $done ? 'is-done' : '' }} {{ $isActive ? 'is-active' : '' }}">
            <div class="pp-pipeline-step-icon">
                @if($done)
                    <span class="material-symbols-outlined text-[16px]">check</span>
                @else
                    <span class="material-symbols-outlined text-[16px]">{{ $step['icon'] }}</span>
                @endif
            </div>
            <span class="pp-pipeline-step-label">{{ $step['label'] }}</span>
        </div>
        @if(!$loop->last)
            <div class="pp-pipeline-connector {{ $done ? 'is-done' : '' }}"></div>
        @endif
    @endforeach
</div>
