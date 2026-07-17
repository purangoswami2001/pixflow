<x-app-layout>
    @php
        $providers = [
            'facebook' => [
                'name' => 'Facebook',
                'icon' => 'public',
                'color' => '#1877F2',
                'desc' => 'Publish posts and photos to a Facebook Page you manage.',
            ],
            'instagram' => [
                'name' => 'Instagram',
                'icon' => 'photo_camera',
                'color' => '#E4405F',
                'desc' => 'Share images, videos, and reels to a connected Instagram account.',
            ],
            'pinterest' => [
                'name' => 'Pinterest',
                'icon' => 'push_pin',
                'color' => '#BD081C',
                'desc' => 'Create pins on your Pinterest boards.',
            ],
        ];
    @endphp

    <x-pp-page-header title="Connect a Profile" description="Choose a network, then authorize your account.">
        <x-slot name="actions">
            <a href="{{ route('accounts.index') }}" class="pp-btn-secondary">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span class="pp-action-label">Back</span>
            </a>
        </x-slot>
    </x-pp-page-header>

    <div class="pp-connect-page">
        <ol class="pp-connect-steps" aria-label="Connection steps">
            <li class="pp-connect-step is-active" id="step1Item">
                <span class="pp-connect-step-num">1</span>
                <span class="pp-connect-step-label">Select</span>
            </li>
            <li class="pp-connect-step-line" id="stepConnector" aria-hidden="true"></li>
            <li class="pp-connect-step" id="step2Item">
                <span class="pp-connect-step-num" id="step2Num">2</span>
                <span class="pp-connect-step-label" id="step2Label">Authorize</span>
            </li>
        </ol>

        <section class="pp-connect-panel">
            <div class="pp-connect-panel-head">
                <h3>Which network do you want to connect?</h3>
                <p>Select one channel to continue.</p>
            </div>

            <div class="pp-connect-grid">
                @foreach($providers as $key => $p)
                    <button type="button"
                            class="pp-connect-card"
                            data-provider="{{ $key }}"
                            data-name="{{ $p['name'] }}"
                            data-color="{{ $p['color'] }}"
                            style="--pp-connect-color: {{ $p['color'] }}">
                        <span class="pp-connect-card__icon" style="background: color-mix(in srgb, {{ $p['color'] }} 14%, transparent); color: {{ $p['color'] }}">
                            <span class="material-symbols-outlined">{{ $p['icon'] }}</span>
                        </span>
                        <span class="pp-connect-card__name">{{ $p['name'] }}</span>
                        <span class="pp-connect-card__desc">{{ $p['desc'] }}</span>
                        <span class="pp-connect-card__check material-symbols-outlined">check_circle</span>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="pp-connect-panel hidden" id="connectStep2">
            @if($mockMode)
                <div class="pp-connect-panel-head">
                    <div class="pp-connect-auth-title">
                        <span class="material-symbols-outlined text-amber-500">science</span>
                        <h3>Connect sandbox — <span id="mockChannelName">—</span></h3>
                    </div>
                    <p>Global sandbox is on. Enter details to simulate a connected profile.</p>
                </div>
                <form action="{{ route('accounts.mock') }}" method="POST" class="pp-connect-form">
                    @csrf
                    <input type="hidden" name="mockProvider" id="mockProviderInput" value="">
                    <div>
                        <label class="pp-label" for="mockName">Display Name</label>
                        <input type="text" name="mockName" id="mockName" class="pp-input" required placeholder="Jane Meta Page" value="{{ old('mockName') }}">
                    </div>
                    <div>
                        <label class="pp-label" for="mockUsername">Username</label>
                        <input type="text" name="mockUsername" id="mockUsername" class="pp-input" placeholder="jane_page" value="{{ old('mockUsername') }}">
                    </div>
                    <div class="pp-connect-form-actions">
                        <button type="submit" class="pp-btn-primary bg-amber-500 hover:bg-amber-600 border-amber-500">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            Connect sandbox profile
                        </button>
                    </div>
                </form>
            @else
                <div class="pp-connect-panel-head">
                    <div class="pp-connect-auth-title">
                        <span class="material-symbols-outlined text-[var(--pp-primary)]">verified_user</span>
                        <h3>Authorize <span id="liveChannelName">—</span></h3>
                    </div>
                    <p>You'll be redirected to sign in and grant access. You can revoke it anytime.</p>
                </div>

                <div class="pp-connect-note is-meta" id="metaSetupNote">
                    <p class="pp-connect-note-title">Facebook / Instagram setup</p>
                    <p>Meta app must use the <strong>Manage everything on your Page</strong> use case. Enable <code>pages_manage_posts</code> in App Review before adding it to <code>FACEBOOK_OAUTH_SCOPES</code>. Instagram uses the same Meta app — whitelist the Facebook redirect URI once.</p>
                </div>

                <div class="pp-connect-note is-pinterest hidden" id="pinterestSetupNote">
                    <p class="pp-connect-note-title">Pinterest setup</p>
                    <p>You'll be redirected to Pinterest to authorize. After connecting, choose the board(s) where pins should publish.</p>
                </div>

                <div class="pp-connect-form-actions">
                    <a href="#" id="authorizeBtn" class="pp-btn-primary">
                        <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                        Continue to <span id="authorizeBtnLabel">authorize</span>
                    </a>
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
    <script>
        (function () {
            const mockMode = @json($mockMode);
            const redirectBase = @json(url('/auth'));
            let selected = @json($selectedProvider);

            const cards = document.querySelectorAll('.pp-connect-card');
            const step2 = document.getElementById('connectStep2');
            const step2Item = document.getElementById('step2Item');
            const stepConnector = document.getElementById('stepConnector');

            function applyProvider(provider, name, color) {
                selected = provider;
                cards.forEach(c => c.classList.toggle('is-selected', c.dataset.provider === provider));

                step2.classList.remove('hidden');
                step2Item?.classList.add('is-active');
                stepConnector?.classList.add('is-active');

                if (mockMode) {
                    document.getElementById('mockProviderInput').value = provider;
                    const nameEl = document.getElementById('mockChannelName');
                    if (nameEl) nameEl.textContent = name;
                } else {
                    ['liveChannelName', 'authorizeBtnLabel'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = name;
                    });
                    const btn = document.getElementById('authorizeBtn');
                    if (btn) {
                        btn.href = redirectBase + '/' + provider + '/redirect';
                        btn.style.background = color;
                        btn.style.borderColor = color;
                    }
                    const meta = document.getElementById('metaSetupNote');
                    const pin = document.getElementById('pinterestSetupNote');
                    if (meta) meta.classList.toggle('hidden', provider === 'pinterest');
                    if (pin) pin.classList.toggle('hidden', provider !== 'pinterest');
                }

                step2.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            cards.forEach(card => {
                card.addEventListener('click', () => {
                    applyProvider(card.dataset.provider, card.dataset.name, card.dataset.color);
                });
            });

            if (selected) {
                const card = document.querySelector('.pp-connect-card[data-provider="' + selected + '"]');
                if (card) applyProvider(card.dataset.provider, card.dataset.name, card.dataset.color);
            }
        })();
    </script>
    @endpush
</x-app-layout>
