@props(['label', 'hint' => null, 'for' => null])
<div {{ $attributes->merge(['class' => '']) }}>
    <label @if($for) for="{{ $for }}" @endif class="pp-label block">{{ $label }}</label>
    @if($hint)
        <p class="text-xs text-[var(--pp-text-muted)] leading-relaxed mt-0.5 mb-2">{{ $hint }}</p>
    @endif
    {{ $slot }}
</div>
