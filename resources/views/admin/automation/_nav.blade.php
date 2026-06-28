@php
    $tabs = [
        ['route' => 'admin.automation.index', 'label' => 'Dashboard', 'icon' => 'dashboard', 'match' => ['admin.automation.index']],
        ['route' => 'admin.automation.categories', 'label' => 'Content', 'icon' => 'folder_open', 'match' => ['admin.automation.categories', 'admin.automation.topics']],
        ['route' => 'admin.automation.prompts', 'label' => 'Prompt Studio', 'icon' => 'psychology', 'match' => ['admin.automation.prompts']],
        ['route' => 'admin.automation.campaigns', 'label' => 'Campaigns', 'icon' => 'campaign', 'match' => ['admin.automation.campaigns', 'admin.automation.campaigns.create', 'admin.automation.campaigns.edit']],
        ['route' => 'admin.automation.runs', 'label' => 'Pipeline', 'icon' => 'timeline', 'match' => ['admin.automation.runs', 'admin.automation.runs.show']],
        ['route' => 'admin.automation.logs', 'label' => 'AI Logs', 'icon' => 'terminal', 'match' => ['admin.automation.logs']],
    ];
    $isActive = fn (array $matches) => collect($matches)->contains(fn ($r) => request()->routeIs($r));
@endphp

<div class="pp-auto-nav-card pp-card mb-6 overflow-hidden">
    <div class="pp-auto-nav-scroll flex overflow-x-auto border-b border-[#e8eaf5]">
        @foreach($tabs as $tab)
            <a href="{{ route($tab['route']) }}"
               class="pp-auto-tab {{ $isActive($tab['match']) ? 'is-active' : '' }}">
                <span class="material-symbols-outlined {{ $isActive($tab['match']) ? 'filled' : '' }}">{{ $tab['icon'] }}</span>
                {{ $tab['label'] }}
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
