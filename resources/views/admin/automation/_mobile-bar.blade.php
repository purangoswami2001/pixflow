{{-- Mobile segment control: radio + CSS :has() so panels switch without JS. --}}
@php
    $tabs = $tabs ?? [];
    $default = $default ?? 'list';
    $back = $back ?? null;
    $radioName = $radioName ?? ('pp-auto-panel-' . substr(md5(($tabs[0]['id'] ?? 'x') . implode('-', array_column($tabs, 'id')) . $default), 0, 10));
@endphp

@if(count($tabs) || $back)
<nav class="pp-auto-mbar" data-pp-auto-mbar data-pp-auto-default="{{ $default }}" data-pp-auto-radio="{{ $radioName }}" aria-label="Section switcher">
    @if($back)
        <a href="{{ $back['url'] }}" class="pp-auto-mbar-back" title="{{ $back['label'] ?? 'Back' }}">
            <span class="material-symbols-outlined">arrow_back</span>
            <span class="pp-auto-mbar-back-label">{{ $back['label'] ?? 'Back' }}</span>
        </a>
    @endif
    @foreach($tabs as $tab)
        @php $tid = $tab['id'] ?? ''; @endphp
        <label class="pp-auto-mbar-btn {{ $tid === $default ? 'is-active' : '' }}">
            <input type="radio"
                   class="pp-auto-panel-radio sr-only"
                   name="{{ $radioName }}"
                   value="{{ $tid }}"
                   data-pp-auto-panel="{{ $tid }}"
                   @checked($tid === $default)>
            @if(!empty($tab['icon']))
                <span class="material-symbols-outlined" aria-hidden="true">{{ $tab['icon'] }}</span>
            @endif
            <span class="pp-auto-mbar-label">{{ $tab['label'] ?? $tid }}</span>
        </label>
    @endforeach
</nav>
@endif
