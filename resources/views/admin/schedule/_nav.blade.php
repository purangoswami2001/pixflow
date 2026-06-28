@php
    $channelTabs = $channelTabs ?? ['all' => 'All Channels'];
    $channelFilter = $channelFilter ?? 'all';
    $channelQuery = $channelQuery ?? fn ($key) => route('admin.schedule', array_filter(['channel' => $key !== 'all' ? $key : null]));
    $platformIcons = ['all' => 'grid_view', 'facebook' => 'groups', 'instagram' => 'photo_camera', 'pinterest' => 'push_pin'];
@endphp

<div class="pp-schedule-nav-card pp-card mb-6 overflow-hidden">
    <div class="flex overflow-x-auto border-b border-[#e8eaf5]">
        @foreach($channelTabs as $key => $label)
            <a href="{{ $channelQuery($key) }}"
               class="pp-schedule-tab {{ $channelFilter === $key ? 'is-active' : '' }} pp-schedule-tab--{{ $key }}">
                <span class="material-symbols-outlined {{ $channelFilter === $key ? 'filled' : '' }}">{{ $platformIcons[$key] ?? 'share' }}</span>
                {{ $label }}
            </a>
        @endforeach
    </div>
    <div class="pp-schedule-flow hidden md:flex">
        <span>Pick account</span>
        <span class="pp-schedule-flow-dot"></span>
        <span>Set weekly slots</span>
        <span class="pp-schedule-flow-dot"></span>
        <span>Approve posts</span>
        <span class="pp-schedule-flow-dot"></span>
        <span>Auto-publish</span>
    </div>
</div>
