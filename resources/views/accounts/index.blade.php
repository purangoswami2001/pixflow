<x-app-layout>
    @php
        $providerColors = [
            'facebook' => ['color' => '#1877F2', 'icon' => 'public', 'grad' => 'linear-gradient(135deg,#1877F2,#0a5bcc)'],
            'instagram' => ['color' => '#E4405F', 'icon' => 'photo_camera', 'grad' => 'linear-gradient(135deg,#833AB4,#E4405F 55%,#FCAF45)'],
            'pinterest' => ['color' => '#BD081C', 'icon' => 'push_pin', 'grad' => 'linear-gradient(135deg,#BD081C,#8c0615)'],
        ];
    @endphp

    <x-pp-page-header title="Social Profiles" description="Connect, sync, and manage your pages, accounts, and boards.">
        <x-slot name="actions">
            <a href="{{ route('accounts.connect') }}" class="pp-btn-primary">
                <span class="material-symbols-outlined text-[18px]">add_link</span>
                <span class="pp-action-label">Connect Profile</span>
            </a>
        </x-slot>
    </x-pp-page-header>

    @if(config('app.debug'))
    <div class="pp-accounts-env mb-4 sm:mb-5">
        <div class="pp-accounts-env-main">
            <span class="material-symbols-outlined">{{ $mockMode ? 'science' : 'cloud_done' }}</span>
            <div class="min-w-0">
                <p class="pp-accounts-env-kicker">Environment</p>
                <p class="pp-accounts-env-value">{{ $mockMode ? 'Global Sandbox' : 'Global Live' }}</p>
            </div>
        </div>
        <div class="pp-accounts-env-chips">
            @foreach(['facebook', 'instagram', 'pinterest'] as $platform)
                @php $sandbox = $platformSandboxes[$platform] ?? false; @endphp
                <span class="pp-accounts-env-chip {{ $sandbox ? 'is-sandbox' : 'is-live' }}">
                    <span class="material-symbols-outlined">{{ $providerColors[$platform]['icon'] }}</span>
                    <span class="pp-accounts-env-chip-name">{{ ucfirst($platform) }}</span>
                    <span class="pp-accounts-env-chip-mode">{{ $sandbox ? 'Sandbox' : 'Live' }}</span>
                </span>
            @endforeach
        </div>
    </div>
    @endif

    @if(config('app.debug') && ($platformSandboxes['pinterest'] ?? false))
        <details class="pp-callout pp-callout--warning pp-callout--collapse mb-4 sm:mb-5">
            <summary>
                <span class="material-symbols-outlined pp-callout-icon shrink-0">info</span>
                <span class="font-semibold">Pinterest Trial / Sandbox mode</span>
                <span class="material-symbols-outlined pp-callout-chevron">expand_more</span>
            </summary>
            <p class="text-xs leading-relaxed opacity-90 mt-2 pl-8">Boards on pinterest.com are not visible in Trial mode — only API sandbox boards appear. After connecting, use <strong>Select boards</strong> to choose where pins publish.</p>
        </details>
    @endif

    <section class="pp-accounts-section">
        <div class="pp-accounts-section-head">
            <div>
                <h3 class="pp-accounts-section-title">Connected Accounts</h3>
                <p class="pp-accounts-section-sub">
                    @if($connectedAccounts->isEmpty())
                        No profiles yet
                    @else
                        {{ $connectedAccounts->count() }} connected
                    @endif
                </p>
            </div>
        </div>

        @if($connectedAccounts->isEmpty())
            <div class="pp-accounts-empty">
                <span class="material-symbols-outlined">link_off</span>
                <h3>No connected profiles</h3>
                <p>Connect your first social network to start scheduling and publishing posts.</p>
                <a href="{{ route('accounts.connect') }}" class="pp-btn-primary">
                    <span class="material-symbols-outlined text-[18px]">add_link</span>
                    Connect Profile
                </a>
            </div>
        @else
            <div class="pp-accounts-grid">
                @foreach($connectedAccounts as $account)
                    @php
                        $provider = $account->provider->value;
                        $style = $providerColors[$provider] ?? ['color' => '#004ac6', 'icon' => 'share', 'grad' => 'linear-gradient(135deg,#004ac6,#003ea8)'];
                        $activeBoards = $account->destinations->where('status', 'active');
                        $destination = $activeBoards->first();
                        $missingScopes = $account->missingPublishScopes();
                        $publishLabel = $provider === 'pinterest' && $activeBoards->count() > 1
                            ? $activeBoards->pluck('name')->join(', ')
                            : ($destination?->name
                                ?? ($account->last_error
                                    ? null
                                    : ($provider === 'pinterest' ? 'No boards yet' : 'Not synced yet')));
                    @endphp
                    <article class="pp-account-card">
                        <div class="pp-account-card__accent" style="background: {{ $style['grad'] }}"></div>
                        <div class="pp-account-card__body">
                            <div class="pp-account-card__top">
                                <img src="{{ $account->avatar_url }}" alt="{{ $account->name }}" class="pp-account-card__avatar">
                                <div class="pp-account-card__identity min-w-0">
                                    <div class="pp-account-card__title-row">
                                        <p class="pp-account-card__name">{{ $account->name }}</p>
                                        <span class="pp-account-card__badge" style="--pp-provider: {{ $style['color'] }}">
                                            <span class="material-symbols-outlined">{{ $style['icon'] }}</span>
                                            {{ ucfirst($provider) }}
                                        </span>
                                    </div>
                                    <p class="pp-account-card__handle">{{ $account->displayHandle() }}</p>
                                </div>
                            </div>

                            <div class="pp-account-card__meta">
                                <div class="pp-account-card__meta-row">
                                    <span class="pp-account-card__meta-label">Status</span>
                                    @if($account->status->value === 'active')
                                        <span class="pp-status-chip bg-emerald-50 text-emerald-700">
                                            <span class="pp-account-card__dot is-ok"></span> Active
                                        </span>
                                    @elseif($account->status->value === 'expired')
                                        <span class="pp-status-chip bg-amber-50 text-amber-700">Expired</span>
                                    @else
                                        <span class="pp-status-chip bg-[#ffdad6] text-[#93000a]">Error</span>
                                    @endif
                                </div>
                                <div class="pp-account-card__meta-row">
                                    <span class="pp-account-card__meta-label">Publish to</span>
                                    <span class="pp-account-card__meta-value {{ !$destination && !$account->last_error ? 'is-warn' : '' }} {{ $account->last_error ? 'is-danger' : '' }}">
                                        {{ $account->last_error ?: ($publishLabel ?? '—') }}
                                    </span>
                                </div>
                            </div>

                            @if($missingScopes !== [])
                                <p class="pp-account-card__warn">
                                    <strong>Cannot publish yet.</strong> Missing: {{ implode(', ', $missingScopes) }}.
                                </p>
                            @endif

                            <div class="pp-account-card__actions">
                                @if($provider === 'pinterest')
                                    <a href="{{ route('accounts.pinterest.boards', $account->id) }}" class="pp-btn-secondary pp-account-card__btn">
                                        <span class="material-symbols-outlined">dashboard</span>
                                        {{ $activeBoards->isNotEmpty() ? 'Boards' : 'Select boards' }}
                                    </a>
                                @else
                                    <form action="{{ route('accounts.sync', $account->id) }}" method="POST" class="flex-1">
                                        @csrf
                                        <button type="submit" class="pp-btn-secondary pp-account-card__btn">
                                            <span class="material-symbols-outlined">sync</span>
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
                                    <button type="submit" class="pp-btn-secondary pp-account-card__btn is-danger">
                                        <span class="material-symbols-outlined">link_off</span>
                                        Disconnect
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-app-layout>
