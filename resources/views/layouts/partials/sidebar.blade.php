@php
    $user = auth()->user();
    $currentOrganization = $currentOrganization ?? $user?->currentOrganization;
    $userOrganizations = $user
        ? $user->organizations()->wherePivot('status', 'active')->orderBy('name')->get()
        : collect();

    $mainNav = [
        ['route' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'match' => ['dashboard']],
        ['route' => 'posts.index', 'icon' => 'layers', 'label' => 'Posts', 'match' => ['posts.index', 'posts.create', 'posts.edit']],
        ['route' => 'posts.calendar', 'icon' => 'calendar_month', 'label' => 'Calendar', 'match' => ['posts.calendar']],
        ['route' => 'media.index', 'icon' => 'perm_media', 'label' => 'Media Library', 'match' => ['media.index', 'media.create', 'media.options', 'media.upload', 'media.metadata', 'media.delete']],
    ];

    $channelNav = [
        ['route' => 'accounts.index', 'icon' => 'hub', 'label' => 'Social Profiles', 'match' => ['accounts.*', 'auth.redirect']],
    ];

    $adminNav = [
        ['route' => 'admin.users', 'icon' => 'group', 'label' => 'Team', 'match' => ['admin.users', 'admin.users.*', 'admin.delete-user']],
        ['route' => 'admin.schedule', 'icon' => 'schedule', 'label' => 'Queue Schedule', 'match' => ['admin.schedule', 'admin.schedule.*']],
        ['route' => 'admin.automation.index', 'icon' => 'smart_toy', 'label' => 'AI Automation', 'match' => ['admin.automation.*']],
        ['route' => 'admin.logs', 'icon' => 'terminal', 'label' => 'System Logs', 'match' => ['admin.logs', 'admin.logs.*']],
        ['route' => 'admin.settings', 'icon' => 'tune', 'label' => 'Firm Settings', 'match' => ['admin.settings', 'admin.settings.*']],
    ];

    $automationTabs = [
        ['route' => 'admin.automation.index', 'label' => 'Dashboard', 'icon' => 'dashboard', 'match' => ['admin.automation.index']],
        ['route' => 'admin.automation.categories', 'label' => 'Content', 'icon' => 'folder_open', 'match' => ['admin.automation.categories', 'admin.automation.topics']],
        ['route' => 'admin.automation.prompts', 'label' => 'Prompt Studio', 'icon' => 'psychology', 'match' => ['admin.automation.prompts']],
        ['route' => 'admin.automation.campaigns', 'label' => 'Campaigns', 'icon' => 'campaign', 'match' => ['admin.automation.campaigns', 'admin.automation.campaigns.create', 'admin.automation.campaigns.edit']],
        ['route' => 'admin.automation.runs', 'label' => 'Pipeline', 'icon' => 'timeline', 'match' => ['admin.automation.runs', 'admin.automation.runs.show']],
        ['route' => 'admin.automation.auto-review', 'label' => 'Auto Review', 'icon' => 'fact_check', 'match' => ['admin.automation.auto-review', 'admin.automation.auto-review.*']],
        ['route' => 'admin.automation.logs', 'label' => 'AI Logs', 'icon' => 'terminal', 'match' => ['admin.automation.logs']],
    ];

    $isNavActive = function (array $patterns): bool {
        return request()->routeIs(...$patterns);
    };

    $inAutomation = request()->routeIs('admin.automation.*');
@endphp

<aside id="ppSidebar" class="pp-sidebar">
    {{-- Brand --}}
    <div class="pp-sidebar-brand px-5 pt-6 pb-4 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 no-underline">
            <div class="w-10 h-10 bg-gradient-to-br from-[#2563eb] to-[#4648d4] rounded-xl flex items-center justify-center text-white shrink-0 shadow-lg shadow-blue-900/30">
                <span class="material-symbols-outlined filled text-[22px]">rocket_launch</span>
            </div>
            <div class="min-w-0">
                <h1 class="font-display text-white text-lg font-semibold leading-none truncate">PixFlow</h1>
                <p class="text-[10px] text-[#737686] tracking-wider uppercase mt-1 truncate">{{ $currentOrganization?->name ?? 'Social Scheduler' }}</p>
            </div>
        </a>

        @if($userOrganizations->count() > 1)
            <div class="mt-4 space-y-1">
                <p class="text-[10px] uppercase tracking-wider text-[#737686] px-1">Switch firm</p>
                @foreach($userOrganizations as $organization)
                    @if((int) $organization->id === (int) ($currentOrganization?->id))
                        <div class="px-3 py-2 rounded-lg bg-white/10 text-white text-xs font-semibold truncate">
                            {{ $organization->name }}
                        </div>
                    @else
                        <form method="POST" action="{{ route('organizations.switch', $organization) }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-[#c3c6d7] hover:bg-white/5 hover:text-white text-xs truncate">
                                {{ $organization->name }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- Navigation --}}
    <nav class="pp-sidebar-nav flex-1 overflow-y-auto px-3 pb-4">
        <p class="pp-sidebar-section-label">Main</p>
        @foreach($mainNav as $item)
            @php $active = $isNavActive($item['match']); @endphp
            <a href="{{ route($item['route']) }}"
               class="pp-sidebar-link {{ $active ? 'active' : '' }}">
                <span class="material-symbols-outlined {{ $active ? 'filled' : '' }}">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach

        @if($user->isAdmin())
            <p class="pp-sidebar-section-label mt-5">Channels</p>
            @foreach($channelNav as $item)
                @php $active = $isNavActive($item['match']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="pp-sidebar-link {{ $active ? 'active' : '' }}">
                    <span class="material-symbols-outlined {{ $active ? 'filled' : '' }}">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            @endforeach

            <p class="pp-sidebar-section-label mt-5">Administration</p>
            @foreach($adminNav as $item)
                @php
                    $active = $isNavActive($item['match']);
                    $isAutomationParent = ($item['route'] ?? '') === 'admin.automation.index';
                @endphp
                <a href="{{ route($item['route']) }}"
                   class="pp-sidebar-link {{ $active ? 'active' : '' }}">
                    <span class="material-symbols-outlined {{ $active ? 'filled' : '' }}">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
                @if($isAutomationParent && $inAutomation)
                    <div class="pp-sidebar-subnav" role="group" aria-label="Automation sections">
                        @foreach($automationTabs as $tab)
                            @php $tabActive = $isNavActive($tab['match']); @endphp
                            <a href="{{ route($tab['route']) }}"
                               class="pp-sidebar-sublink {{ $tabActive ? 'is-active' : '' }}">
                                <span class="material-symbols-outlined {{ $tabActive ? 'filled' : '' }}">{{ $tab['icon'] }}</span>
                                <span>{{ $tab['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endif
    </nav>

    {{-- Footer actions --}}
    <div class="pp-sidebar-footer px-4 py-4 shrink-0 border-t border-white/10">
        <a href="{{ route('posts.create') }}" class="pp-btn-primary w-full py-2.5 justify-center shadow-lg shadow-blue-900/25 mb-2">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Create Post
        </a>
        <a href="{{ route('profile.edit') }}" class="pp-sidebar-footer-link">
            <span class="material-symbols-outlined text-[18px]">settings</span>
            Profile Settings
        </a>
    </div>
</aside>
