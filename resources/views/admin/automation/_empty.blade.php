@props(['icon' => 'inbox', 'title', 'description', 'actionLabel' => null, 'actionUrl' => null])

<div class="pp-auto-empty">
    <div class="pp-auto-empty-icon">
        <span class="material-symbols-outlined">{{ $icon }}</span>
    </div>
    <h3 class="pp-auto-empty-title">{{ $title }}</h3>
    <p class="pp-auto-empty-desc">{{ $description }}</p>
    @if($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="pp-btn-primary mt-2">{{ $actionLabel }}</a>
    @endif
    {{ $slot ?? '' }}
</div>
