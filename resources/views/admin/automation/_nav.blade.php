@php
    $tabs = [
        ['route' => 'admin.automation.index', 'label' => 'Dashboard', 'icon' => 'dashboard', 'match' => ['admin.automation.index']],
        ['route' => 'admin.automation.categories', 'label' => 'Content', 'icon' => 'folder_open', 'match' => ['admin.automation.categories', 'admin.automation.topics']],
        ['route' => 'admin.automation.prompts', 'label' => 'Prompt Studio', 'icon' => 'psychology', 'match' => ['admin.automation.prompts']],
        ['route' => 'admin.automation.campaigns', 'label' => 'Campaigns', 'icon' => 'campaign', 'match' => ['admin.automation.campaigns', 'admin.automation.campaigns.create', 'admin.automation.campaigns.edit']],
        ['route' => 'admin.automation.runs', 'label' => 'Pipeline', 'icon' => 'timeline', 'match' => ['admin.automation.runs', 'admin.automation.runs.show']],
        ['route' => 'admin.automation.auto-review', 'label' => 'Auto Review', 'icon' => 'fact_check', 'match' => ['admin.automation.auto-review', 'admin.automation.auto-review.*']],
        ['route' => 'admin.automation.logs', 'label' => 'AI Logs', 'icon' => 'terminal', 'match' => ['admin.automation.logs']],
    ];
    $isActive = fn (array $matches) => collect($matches)->contains(fn ($r) => request()->routeIs($r));
@endphp

{{-- Horizontal tabs: full labels on desktop; mobile shows label only on the active tab --}}
<div class="pp-auto-nav-card pp-card mb-4 sm:mb-6 overflow-hidden">
    <div class="pp-auto-nav-scroll flex overflow-x-auto border-b border-[var(--pp-border-soft)] -mx-px">
        @foreach($tabs as $tab)
            @php $active = $isActive($tab['match']); @endphp
            <a href="{{ route($tab['route']) }}"
               class="pp-auto-tab {{ $active ? 'is-active' : '' }}"
               @if($active) aria-current="page" @endif
               title="{{ $tab['label'] }}">
                <span class="material-symbols-outlined {{ $active ? 'filled' : '' }}">{{ $tab['icon'] }}</span>
                <span class="pp-auto-tab-label">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
    <div class="pp-auto-flow hidden md:flex">
        @foreach(['Content', 'Prompts', 'Campaign', 'Generate', 'Approve'] as $i => $step)
            @if($i > 0)<span class="pp-auto-flow-dot"></span>@endif
            <span class="pp-auto-flow-step">{{ $step }}</span>
        @endforeach
    </div>
</div>
