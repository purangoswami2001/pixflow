<header class="sticky top-0 z-40 flex justify-between items-center px-6 w-full h-16 bg-white border-b border-[#c3c6d7]">
    <div class="flex items-center gap-4">
        <button type="button" class="lg:hidden p-2 text-[#434655] hover:bg-[#f2f3ff] rounded-full" onclick="document.getElementById('ppSidebar').classList.toggle('open')">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="relative hidden sm:block">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#737686] text-sm">search</span>
            <input type="text" class="pl-10 pr-4 py-2 bg-[#f2f3ff] border-none rounded-full text-sm w-64 focus:ring-2 focus:ring-[#2563eb]/20" placeholder="Search...">
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button type="button" class="p-2 text-[#434655] hover:bg-[#f2f3ff] rounded-full relative">
            <span class="material-symbols-outlined">notifications</span>
            <span class="absolute top-2 right-2 w-2 h-2 bg-[#ba1a1a] rounded-full border-2 border-white"></span>
        </button>
        <button type="button" class="p-2 text-[#434655] hover:bg-[#f2f3ff] rounded-full">
            <span class="material-symbols-outlined">help</span>
        </button>
        <div class="h-8 w-px bg-[#c3c6d7] mx-1 hidden sm:block"></div>
        <div class="relative group">
            <button type="button" class="flex items-center gap-3 cursor-pointer">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-semibold text-[#131b2e] leading-tight">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-[#737686]">{{ auth()->user()->isAdmin() ? 'Super Admin' : 'Creator' }}</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-[#2563eb] text-white flex items-center justify-center font-bold text-sm border-2 border-[#dbe1ff]">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
            </button>
            <div class="hidden group-hover:block absolute right-0 top-full mt-1 w-48 bg-white border border-[#c3c6d7] rounded-lg shadow-lg py-1 z-50">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-[#434655] hover:bg-[#f2f3ff]">Profile Settings</a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-[#ba1a1a] hover:bg-[#ffdad6]">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</header>
