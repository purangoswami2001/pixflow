<x-app-layout>
    <x-pp-page-header title="Social Profiles" description="Connect, sync, and manage your social network pages, accounts, and boards.">
        <x-slot name="actions">
            @if($mockMode)
                <span class="pp-status-chip bg-amber-50 text-amber-700 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">science</span> Global Sandbox
                </span>
            @else
                <span class="pp-status-chip bg-emerald-50 text-emerald-700 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">verified</span> Global Live
                </span>
            @endif
            @foreach(['facebook' => '#1877F2', 'instagram' => '#E4405F', 'pinterest' => '#BD081C'] as $platform => $color)
                @if($platformSandboxes[$platform] ?? false)
                    <span class="pp-status-chip bg-amber-50 text-amber-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">science</span> {{ ucfirst($platform) }} Sandbox
                    </span>
                @else
                    <span class="pp-status-chip bg-emerald-50 text-emerald-700 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">verified</span> {{ ucfirst($platform) }} Live
                    </span>
                @endif
            @endforeach
        </x-slot>
    </x-pp-page-header>

    @if($platformSandboxes['pinterest'] ?? false)
        <div class="pp-card p-4 mb-6 border border-amber-200 bg-amber-50/80 text-sm text-amber-950">
            <p class="font-semibold mb-1">Pinterest Trial / Sandbox mode</p>
            <p class="text-xs leading-relaxed">Boards on <strong>pinterest.com</strong> are not visible in Trial mode — only API sandbox boards appear. After connecting, use <strong>Select board</strong> to pick where pins are published.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="pp-card overflow-hidden">
                <div class="pp-card-header">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#131b2e]">Connected Accounts</h3>
                </div>
                <div class="p-6">
                    @if($connectedAccounts->isEmpty())
                        <div class="text-center py-12 text-[#737686]">
                            <span class="material-symbols-outlined text-5xl mb-3 block opacity-40">link_off</span>
                            <p class="font-semibold text-[#131b2e] mb-1">No connected profiles</p>
                            <p class="text-sm max-w-sm mx-auto">Connect a profile using the panel on the right to start scheduling posts.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($connectedAccounts as $account)
                                @php
                                    $provider = $account->provider->value;
                                    $providerColors = [
                                        'facebook' => ['chip' => 'bg-[#1877F2]/10 text-[#1877F2]', 'icon' => 'face'],
                                        'instagram' => ['chip' => 'bg-[#E4405F]/10 text-[#E4405F]', 'icon' => 'photo_camera'],
                                        'pinterest' => ['chip' => 'bg-[#BD081C]/10 text-[#BD081C]', 'icon' => 'push_pin'],
                                    ];
                                    $style = $providerColors[$provider] ?? ['chip' => 'bg-[#f2f3ff] text-[#434655]', 'icon' => 'share'];
                                    $activeBoards = $account->destinations->where('status', 'active');
                                    $destination = $activeBoards->first();
                                @endphp
                                <div class="pp-card overflow-hidden">
                                    <div class="p-4">
                                        <div class="flex items-start gap-3 mb-4">
                                            <img src="{{ $account->avatar_url }}" alt="{{ $account->name }}" class="w-12 h-12 rounded-full border border-[#c3c6d7] object-cover shrink-0">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-[#131b2e] truncate">{{ $account->name }}</p>
                                        <p class="text-sm text-[#737686] truncate">{{ $account->displayHandle() }}</p>
                                                    </div>
                                                    <span class="pp-status-chip {{ $style['chip'] }} shrink-0 flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-[14px]">{{ $style['icon'] }}</span>
                                                        {{ ucfirst($provider) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 text-xs mb-2">
                                            <span class="text-[#737686]">Status</span>
                                            @if($account->status->value === 'active')
                                                <span class="pp-status-chip bg-emerald-50 text-emerald-700 flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Active
                                                </span>
                                            @elseif($account->status->value === 'expired')
                                                <span class="pp-status-chip bg-amber-50 text-amber-700">Expired</span>
                                            @else
                                                <span class="pp-status-chip bg-[#ffdad6] text-[#93000a]">Error</span>
                                            @endif
                                        </div>

                                        @if($provider === 'pinterest' && $activeBoards->count() > 1)
                                            <p class="text-[11px] text-[#434655] leading-snug">
                                                <span class="text-[#737686]">Boards:</span>
                                                {{ $activeBoards->pluck('name')->join(', ') }}
                                            </p>
                                        @elseif($destination)
                                            <p class="text-[11px] text-[#434655] truncate">
                                                <span class="text-[#737686]">Publish to:</span> {{ $destination->name }}
                                            </p>
                                        @elseif($account->last_error)
                                            <p class="text-[11px] text-[#93000a] leading-snug">{{ $account->last_error }}</p>
                                        @elseif($account->provider->value === 'pinterest')
                                            <p class="text-[11px] text-amber-700">No boards connected yet. Click Manage boards.</p>
                                        @else
                                            <p class="text-[11px] text-amber-700">No publish destination synced yet. Click Sync.</p>
                                        @endif

                                        @php $missingScopes = $account->missingPublishScopes(); @endphp
                                        @if($missingScopes !== [])
                                            <p class="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2 mt-2 leading-snug">
                                                <strong>Cannot publish yet.</strong> Missing: {{ implode(', ', $missingScopes) }}.
                                                Enable in Meta App Review, update OAuth scopes in <code class="text-[10px]">.env</code>, then reconnect.
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex border-t border-[#c3c6d7] bg-[#faf8ff] divide-x divide-[#c3c6d7]">
                                        @if($provider === 'pinterest')
                                            <a href="{{ route('accounts.pinterest.boards', $account->id) }}" class="flex-1 py-3 text-xs font-semibold text-[#BD081C] flex items-center justify-center gap-1.5 hover:bg-[#BD081C]/5 transition-colors">
                                                <span class="material-symbols-outlined text-[16px]">dashboard</span>
                                                {{ $activeBoards->isNotEmpty() ? 'Manage boards' : 'Select boards' }}
                                            </a>
                                        @else
                                            <form action="{{ route('accounts.sync', $account->id) }}" method="POST" class="flex-1">
                                                @csrf
                                                <button type="submit" class="w-full py-3 text-xs font-semibold text-[#004ac6] flex items-center justify-center gap-1.5 hover:bg-[#f2f3ff] transition-colors">
                                                    <span class="material-symbols-outlined text-[16px]">sync</span>
                                                    Sync
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('accounts.disconnect', $account->id) }}" method="POST" class="flex-1"
                                            data-pp-confirm="This profile will be disconnected. Scheduled posts may fail until you reconnect."
                                            data-pp-confirm-title="Disconnect profile?"
                                            data-pp-confirm-variant="danger"
                                            data-pp-confirm-button="Disconnect">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full py-3 text-xs font-semibold text-[#ba1a1a] flex items-center justify-center gap-1.5 hover:bg-[#ffdad6]/20 transition-colors">
                                                <span class="material-symbols-outlined text-[16px]">link_off</span>
                                                Disconnect
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="pp-card p-6">
                <h3 class="font-display font-semibold text-[#131b2e] mb-1">Connect Live Profile</h3>
                <p class="text-sm text-[#737686] mb-4">Authorize with your real social accounts via OAuth.</p>
                <div class="space-y-2">
                    <div class="mb-3 p-3 rounded-lg bg-[#1877F2]/5 border border-[#1877F2]/20 text-xs text-[#434655] leading-relaxed">
                        <p class="font-semibold text-[#1877F2] mb-1">Facebook / Instagram setup</p>
                        <p>Meta app must use the <strong>Manage everything on your Page</strong> use case. Enable <code class="text-[#131b2e]">pages_manage_posts</code> in App Dashboard → App Review before adding it to <code class="text-[#131b2e]">FACEBOOK_OAUTH_SCOPES</code> in <code class="text-[#131b2e]">.env</code>. Instagram uses the <strong>same Meta app</strong> — only whitelist the Facebook redirect URI once.</p>
                    </div>
                    <a href="{{ route('auth.redirect', ['provider' => 'facebook']) }}" class="pp-btn-secondary w-full py-3 justify-start text-[#1877F2] border-[#1877F2]/30">
                        <span class="material-symbols-outlined">face</span> Connect Facebook
                    </a>
                    <a href="{{ route('auth.redirect', ['provider' => 'instagram']) }}" class="pp-btn-secondary w-full py-3 justify-start text-[#E4405F] border-[#E4405F]/30">
                        <span class="material-symbols-outlined">photo_camera</span> Connect Instagram
                    </a>
                    <a href="{{ route('auth.redirect', ['provider' => 'pinterest']) }}" class="pp-btn-secondary w-full py-3 justify-start text-[#BD081C] border-[#BD081C]/30">
                        <span class="material-symbols-outlined">push_pin</span> Connect Pinterest
                    </a>
                </div>
            </div>

            @if($mockMode)
                <div class="pp-card p-6 border-l-4 border-l-amber-400">
                    <h3 class="font-display font-semibold text-[#131b2e] mb-1">Sandbox Connector</h3>
                    <p class="text-sm text-[#737686] mb-4">Simulate connections for testing.</p>
                    <form action="{{ route('accounts.mock') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Platform</label>
                            <select name="mockProvider" class="pp-input py-2 text-sm">
                                <option value="facebook">Facebook Page</option>
                                <option value="instagram">Instagram Account</option>
                                <option value="pinterest">Pinterest Board</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Display Name</label>
                            <input type="text" name="mockName" class="pp-input py-2 text-sm" required placeholder="Jane Meta Page">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Username</label>
                            <input type="text" name="mockUsername" class="pp-input py-2 text-sm" placeholder="jane_page">
                        </div>
                        <button type="submit" class="pp-btn-primary w-full bg-amber-500 hover:bg-amber-600">
                            <span class="material-symbols-outlined text-[18px]">add</span> Connect Mock
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
