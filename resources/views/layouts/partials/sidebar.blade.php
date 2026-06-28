@php
    $navItems = [
        ['route' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'match' => 'dashboard'],
        ['route' => 'posts.index', 'icon' => 'reorder', 'label' => 'Posts', 'match' => 'posts.index'],
        ['route' => 'posts.create', 'icon' => 'post_add', 'label' => 'Create Post', 'match' => 'posts.create'],
        ['route' => 'media.index', 'icon' => 'perm_media', 'label' => 'Media', 'match' => 'media.*'],
    ];

    if (auth()->user()->isAdmin()) {
        $navItems[] = ['route' => 'accounts.index', 'icon' => 'share_reviews', 'label' => 'Social Profiles', 'match' => 'accounts.*'];
    }

    $adminItems = [
        ['route' => 'admin.users', 'icon' => 'group', 'label' => 'Team', 'match' => 'admin.users'],
        ['route' => 'admin.schedule', 'icon' => 'calendar_month', 'label' => 'Schedule', 'match' => 'admin.schedule'],
        ['route' => 'admin.automation.index', 'icon' => 'smart_toy', 'label' => 'AI Automation', 'match' => 'admin.automation.*'],
        ['route' => 'admin.logs', 'icon' => 'terminal', 'label' => 'System Logs', 'match' => 'admin.logs'],
    ];
@endphp

<aside id="ppSidebar" class="pp-sidebar fixed left-0 top-0 h-full w-[260px] bg-[#131b2e] flex flex-col py-6 z-50 transition-transform duration-300">
    <div class="px-6 mb-8 flex items-center gap-3">
        <div class="w-10 h-10 bg-[#2563eb] rounded-xl flex items-center justify-center text-white shrink-0">
            <span class="material-symbols-outlined filled">rocket_launch</span>
        </div>
        <div>
            <h1 class="font-display text-white text-lg font-semibold leading-none">PixFlow</h1>
            <p class="text-[10px] text-[#c3c6d7] tracking-wider uppercase mt-0.5">Enterprise Plan</p>
        </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto">
        @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="pp-sidebar-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                <span class="material-symbols-outlined {{ request()->routeIs($item['match']) ? 'filled' : '' }}">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach

        @if(auth()->user()->isAdmin())
            <div class="pt-4 mt-4 border-t border-white/10 mx-4">
                <p class="text-[10px] uppercase tracking-wider text-[#737686] font-semibold mb-2 px-2">Admin</p>
                @foreach($adminItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="pp-sidebar-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                        <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="px-4 mt-auto">
        <a href="{{ route('posts.create') }}" class="pp-btn-primary w-full py-3 shadow-lg shadow-blue-900/20">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Create Post
        </a>
    </div>
</aside>
