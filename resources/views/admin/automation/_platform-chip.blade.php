@php
    $platform = $platform ?? 'pinterest';
    $styles = [
        'pinterest' => ['icon' => 'push_pin', 'class' => 'pp-platform--pinterest'],
        'facebook' => ['icon' => 'groups', 'class' => 'pp-platform--facebook'],
        'instagram' => ['icon' => 'photo_camera', 'class' => 'pp-platform--instagram'],
    ];
    $s = $styles[$platform] ?? ['icon' => 'share', 'class' => ''];
@endphp
<span class="pp-platform-chip {{ $s['class'] }} {{ $size ?? '' }}">
    <span class="material-symbols-outlined">{{ $s['icon'] }}</span>
    <span class="capitalize">{{ $platform }}</span>
</span>
