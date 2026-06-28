@props(['icon' => 'event_busy', 'title', 'description', 'actionLabel' => null, 'actionUrl' => null])

<div class="pp-schedule-empty">
    <div class="pp-schedule-empty-icon">
        <span class="material-symbols-outlined">{{ $icon }}</span>
    </div>
    <h3 class="pp-schedule-empty-title">{{ $title }}</h3>
    <p class="pp-schedule-empty-desc">{{ $description }}</p>
    @if($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="pp-btn-primary mt-4">{{ $actionLabel }}</a>
    @endif
    {{ $slot ?? '' }}
</div>
