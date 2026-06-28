@php
    $isEdit = isset($campaign);
    $c = $campaign ?? null;
    $channelImagePresets = $channelImagePresets ?? [];
    $channelImageStyles = $channelImageStyles ?? [];
    $selectedPlatforms = old('platforms', $c?->platforms ?? ['pinterest', 'facebook', 'instagram']);
    if (! is_array($selectedPlatforms)) {
        $selectedPlatforms = [];
    }
    $platformLabels = ['pinterest' => 'Pinterest', 'facebook' => 'Facebook', 'instagram' => 'Instagram'];
    $platformIcons = ['pinterest' => 'push_pin', 'facebook' => 'groups', 'instagram' => 'photo_camera'];
    $firstSelected = collect($selectedPlatforms)->first(fn ($p) => isset($platformLabels[$p])) ?? 'pinterest';
@endphp

<div class="pp-campaign-form-sections">
    <section class="pp-campaign-form-section">
        <div class="pp-campaign-settings-card-head">
            <span class="material-symbols-outlined">tune</span>
            <h3>Basics</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-automation-field label="Campaign name" for="campaign_name">
                <input type="text" name="name" id="campaign_name" class="pp-input" value="{{ old('name', $c?->name) }}" placeholder="e.g. Agent 2" required>
            </x-automation-field>
            <x-automation-field label="Content category" for="content_category_id">
                <select name="content_category_id" id="content_category_id" class="pp-input" required>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('content_category_id', $c?->content_category_id) == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </x-automation-field>
        </div>
    </section>

    <section class="pp-campaign-form-section">
        <div class="pp-campaign-settings-card-head">
            <span class="material-symbols-outlined">bar_chart</span>
            <h3>Daily volume</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-automation-field label="Posts per day (total)" for="posts_per_day">
                <input type="number" name="posts_per_day" id="posts_per_day" class="pp-input" value="{{ old('posts_per_day', $c?->posts_per_day ?? 1) }}" min="1" max="20" required>
            </x-automation-field>
            <x-automation-field label="Promotional ratio (%)" for="promotional_ratio">
                <input type="number" name="promotional_ratio" id="promotional_ratio" class="pp-input" value="{{ old('promotional_ratio', $c?->promotional_ratio ?? 15) }}" min="0" max="100" required>
            </x-automation-field>
        </div>
    </section>

    <section class="pp-campaign-form-section">
        <div class="pp-campaign-settings-card-head">
            <span class="material-symbols-outlined">schedule</span>
            <h3>Schedule</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-automation-field label="Timezone" for="default_timezone">
                @php $selectedTz = old('default_timezone', $c?->default_timezone ?? 'UTC'); @endphp
                <select name="default_timezone" id="default_timezone" class="pp-input" required>
                    @foreach($timezones as $tz => $label)
                        <option value="{{ $tz }}" @selected($selectedTz === $tz)>{{ $label }}</option>
                    @endforeach
                    @if($selectedTz && !array_key_exists($selectedTz, $timezones))
                        <option value="{{ $selectedTz }}" selected>{{ $selectedTz }}</option>
                    @endif
                </select>
            </x-automation-field>
            <x-automation-field label="Daily generation time" for="generation_time">
                <input type="time" name="generation_time" id="generation_time" class="pp-input" value="{{ old('generation_time', $c ? \Carbon\Carbon::parse($c->generation_time)->format('H:i') : '06:00') }}" required>
            </x-automation-field>
        </div>
    </section>

    <section class="pp-campaign-form-section">
        <div class="pp-campaign-settings-card-head">
            <span class="material-symbols-outlined">hub</span>
            <div>
                <h3>Channels</h3>
                <p class="pp-campaign-channels-sub">Enable platforms, then switch tabs to configure each one separately</p>
            </div>
        </div>

        <p class="pp-label mb-2">Step 1 — Enable channels</p>
        <div class="pp-channel-enable-grid mb-5" id="campaign-platform-picks">
            @foreach($platformLabels as $p => $label)
                @php $isOn = in_array($p, $selectedPlatforms, true); @endphp
                <div class="pp-channel-enable-card pp-channel-enable-card--{{ $p }} {{ $isOn ? 'is-enabled' : '' }}" data-enable-card="{{ $p }}">
                    <label class="pp-channel-enable-switch" title="Include {{ $label }} in campaign">
                        <input type="checkbox" name="platforms[]" value="{{ $p }}" class="campaign-platform-checkbox" data-platform="{{ $p }}"
                            @checked($isOn)>
                        <span class="pp-channel-enable-switch-ui" aria-hidden="true"></span>
                    </label>
                    <button type="button" class="pp-channel-enable-body" data-configure="{{ $p }}">
                        <span class="pp-channel-enable-icon material-symbols-outlined">{{ $platformIcons[$p] }}</span>
                        <span class="pp-channel-enable-name">{{ $label }}</span>
                        <span class="pp-channel-enable-status">{{ $isOn ? 'Enabled — click to configure' : 'Off' }}</span>
                    </button>
                </div>
            @endforeach
        </div>

        <div id="campaign-platform-empty" class="pp-campaign-platform-empty {{ count($selectedPlatforms) ? 'hidden' : '' }}">
            <span class="material-symbols-outlined text-[#c3c6d7]">info</span>
            <p>Enable at least one channel above to configure image style, account, and size.</p>
        </div>

        <div id="campaign-channel-config" class="{{ count($selectedPlatforms) ? '' : 'hidden' }}">
            <div class="pp-channel-config-header">
                <p class="pp-label mb-0">Step 2 — Configure channel</p>
                <div class="pp-channel-active-banner pp-channel-active-banner--{{ $firstSelected }}" id="campaign-active-channel-banner">
                    <span class="material-symbols-outlined pp-channel-active-banner-icon">{{ $platformIcons[$firstSelected] }}</span>
                    <span>Editing <strong id="campaign-active-channel-name">{{ $platformLabels[$firstSelected] }}</strong> settings</span>
                </div>
            </div>

            <div class="pp-campaign-channel-tabs" id="campaign-channel-tabs" role="tablist" aria-label="Choose channel to configure">
                @foreach($platformLabels as $p => $label)
                    @php $isSelected = in_array($p, $selectedPlatforms, true); @endphp
                    <button type="button"
                            class="pp-campaign-channel-tab pp-platform-tab--{{ $p }} {{ $isSelected && $p === $firstSelected ? 'is-active' : '' }} {{ $isSelected ? '' : 'is-hidden' }}"
                            data-channel-tab="{{ $p }}"
                            data-channel-label="{{ $label }}"
                            role="tab"
                            aria-selected="{{ $isSelected && $p === $firstSelected ? 'true' : 'false' }}"
                            aria-controls="campaign-panel-{{ $p }}">
                        <span class="material-symbols-outlined text-[18px]">{{ $platformIcons[$p] }}</span>
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>

            <div id="campaign-platform-panels" class="pp-campaign-channel-panels">
                @foreach($platformLabels as $p => $label)
                    @php
                        $isPlatformSelected = in_array($p, $selectedPlatforms, true);
                        $presetOptions = $channelImagePresets[$p] ?? [];
                        $selectedPreset = old(
                            "platform_image_presets.{$p}",
                            $c ? \App\Support\AutomationCampaignPresets::resolvePresetKey($c, $p) : \App\Support\AutomationCampaignPresets::defaultForPlatform($p)
                        );
                        $selectedStyle = old(
                            "platform_image_styles.{$p}",
                            $c ? \App\Support\AutomationImageStyles::resolveStyleKey($c, $p) : \App\Support\AutomationImageStyles::defaultForPlatform($p)
                        );
                        $styleOptions = $channelImageStyles;
                        $isActivePanel = $isPlatformSelected && $p === $firstSelected;
                    @endphp
                    <div id="campaign-panel-{{ $p }}"
                         class="pp-campaign-channel-panel pp-campaign-platform-panel--{{ $p }} {{ $isPlatformSelected ? '' : 'is-hidden' }} {{ $isActivePanel ? 'is-active' : '' }}"
                         data-platform-panel="{{ $p }}"
                         data-platform-label="{{ $label }}"
                         role="tabpanel"
                         aria-labelledby="tab-{{ $p }}">
                        <div class="pp-campaign-channel-panel-inner">
                            <div class="pp-campaign-channel-panel-head">
                                @include('admin.automation._platform-chip', ['platform' => $p])
                                <span class="pp-campaign-channel-panel-head-text">{{ $label }} configuration</span>
                            </div>
                            <div class="campaign-platform-fields space-y-4">
                            <div class="pp-image-style-scroll-wrap">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <p class="pp-label mb-0">Image style</p>
                                    <span class="pp-image-style-scroll-hint">Scroll for more →</span>
                                </div>
                                <div class="pp-image-style-scroll" data-style-grid="{{ $p }}">
                                    @foreach($styleOptions as $styleKey => $style)
                                        <label class="pp-image-style-card pp-image-style-card--compact {{ $selectedStyle === $styleKey ? 'is-selected' : '' }}">
                                            <input type="radio"
                                                   name="platform_image_styles[{{ $p }}]"
                                                   value="{{ $styleKey }}"
                                                   class="pp-image-style-radio campaign-platform-field sr-only"
                                                   data-platform="{{ $p }}"
                                                   @checked($selectedStyle === $styleKey)
                                                   @disabled(!$isPlatformSelected)>
                                            <div class="pp-image-style-preview">
                                                <img src="{{ asset($style['preview']) }}" alt="{{ $style['label'] }}" loading="lazy" width="120" height="72">
                                            </div>
                                            <div class="pp-image-style-meta">
                                                <span class="pp-image-style-label">{{ $style['label'] }}</span>
                                            </div>
                                            <span class="pp-image-style-check material-symbols-outlined">check_circle</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-automation-field label="Social account">
                                    <select name="platform_accounts[{{ $p }}]" class="pp-input campaign-platform-field" @disabled(!$isPlatformSelected)>
                                        <option value="">Auto (first active)</option>
                                        @foreach($accounts->filter(fn ($a) => $a->provider->value === $p) as $acc)
                                            <option value="{{ $acc->id }}" @selected(old("platform_accounts.{$p}", $c?->platform_accounts[$p] ?? null) == $acc->id)>{{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </x-automation-field>

                                <x-automation-field label="Posts per day (optional)">
                                    <input type="number" name="platform_counts[{{ $p }}]" class="pp-input campaign-platform-field" value="{{ old("platform_counts.{$p}", $c?->platform_counts[$p] ?? '') }}" placeholder="e.g. 1" min="0" max="20" @disabled(!$isPlatformSelected)>
                                </x-automation-field>
                            </div>

                            <x-automation-field label="Image size">
                                <select name="platform_image_presets[{{ $p }}]" class="pp-input campaign-platform-field platform-image-preset" data-platform="{{ $p }}" @disabled(!$isPlatformSelected)>
                                    @forelse($presetOptions as $preset)
                                        <option value="{{ $preset['key'] }}" @selected($selectedPreset === $preset['key'])>
                                            {{ $preset['name'] }} ({{ $preset['width'] }}×{{ $preset['height'] }})
                                        </option>
                                    @empty
                                        <option value="{{ $selectedPreset }}">{{ $selectedPreset }}</option>
                                    @endforelse
                                </select>
                            </x-automation-field>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pp-campaign-form-section pp-campaign-form-section--last">
        <label class="pp-campaign-active-row flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" class="mt-0.5 accent-[#004ac6]" @checked(old('is_active', $c?->is_active ?? true))>
            <span>
                <strong class="text-sm text-[#131b2e] block">Active campaign</strong>
                <span class="text-xs text-[#737686]">Runs on schedule and with Run now</span>
            </span>
        </label>
    </section>
</div>

@once
    @push('scripts')
    <script>
        (function () {
            const platformLabels = @json($platformLabels);
            const platformIcons = @json($platformIcons);
            const checkboxes = document.querySelectorAll('.campaign-platform-checkbox');
            const enableCards = document.querySelectorAll('[data-enable-card]');
            const tabs = document.querySelectorAll('[data-channel-tab]');
            const panels = document.querySelectorAll('[data-platform-panel]');
            const emptyState = document.getElementById('campaign-platform-empty');
            const configWrap = document.getElementById('campaign-channel-config');
            const tabsWrap = document.getElementById('campaign-channel-tabs');
            const banner = document.getElementById('campaign-active-channel-banner');
            const bannerName = document.getElementById('campaign-active-channel-name');
            const bannerIcon = banner?.querySelector('.pp-channel-active-banner-icon');

            function selectedPlatforms() {
                return Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.dataset.platform);
            }

            function updateBanner(platform) {
                if (!banner || !bannerName) return;
                banner.className = 'pp-channel-active-banner pp-channel-active-banner--' + platform;
                bannerName.textContent = platformLabels[platform] || platform;
                if (bannerIcon) {
                    bannerIcon.textContent = platformIcons[platform] || 'share';
                }
            }

            function syncEnableCards(activePlatform) {
                enableCards.forEach(card => {
                    const platform = card.dataset.enableCard;
                    const cb = card.querySelector('.campaign-platform-checkbox');
                    const on = cb?.checked ?? false;
                    const isConfiguring = on && platform === activePlatform;
                    card.classList.toggle('is-enabled', on);
                    card.classList.toggle('is-configuring', isConfiguring);
                    const status = card.querySelector('.pp-channel-enable-status');
                    if (status) {
                        if (!on) status.textContent = 'Off';
                        else if (isConfiguring) status.textContent = 'Configuring now';
                        else status.textContent = 'Enabled — click to configure';
                    }
                });
            }

            function activateTab(platform) {
                tabs.forEach(tab => {
                    const isTarget = tab.dataset.channelTab === platform;
                    tab.classList.toggle('is-active', isTarget);
                    tab.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                });
                panels.forEach(panel => {
                    panel.classList.toggle('is-active', panel.dataset.platformPanel === platform);
                });
                updateBanner(platform);
                syncEnableCards(platform);
            }

            function syncPlatformPanels(focusPlatform) {
                const selected = selectedPlatforms();
                let firstVisible = null;

                tabs.forEach(tab => {
                    const platform = tab.dataset.channelTab;
                    const show = selected.includes(platform);
                    tab.classList.toggle('is-hidden', !show);
                    if (show && firstVisible === null) firstVisible = platform;
                });

                panels.forEach(panel => {
                    const platform = panel.dataset.platformPanel;
                    const show = selected.includes(platform);
                    panel.classList.toggle('is-hidden', !show);
                    panel.querySelectorAll('.campaign-platform-field').forEach(field => {
                        field.disabled = !show;
                    });
                    if (!show) panel.classList.remove('is-active');
                });

                const hasChannels = selected.length > 0;
                emptyState?.classList.toggle('hidden', hasChannels);
                configWrap?.classList.toggle('hidden', !hasChannels);
                tabsWrap?.classList.toggle('hidden', !hasChannels);

                const activeTab = tabsWrap?.querySelector('.pp-campaign-channel-tab.is-active:not(.is-hidden)');
                const target = focusPlatform && selected.includes(focusPlatform)
                    ? focusPlatform
                    : (activeTab && !activeTab.classList.contains('is-hidden')
                        ? activeTab.dataset.channelTab
                        : firstVisible);

                if (target) activateTab(target);
                else syncEnableCards(null);
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    if (!tab.classList.contains('is-hidden')) {
                        activateTab(tab.dataset.channelTab);
                    }
                });
            });

            enableCards.forEach(card => {
                const cb = card.querySelector('.campaign-platform-checkbox');
                const body = card.querySelector('.pp-channel-enable-body');
                if (!cb || !body) return;

                cb.addEventListener('change', () => {
                    syncPlatformPanels(cb.checked ? cb.dataset.platform : null);
                });

                body.addEventListener('click', () => {
                    if (cb.checked) {
                        activateTab(cb.dataset.platform);
                    } else {
                        cb.checked = true;
                        syncPlatformPanels(cb.dataset.platform);
                    }
                });
            });

            document.querySelectorAll('.pp-image-style-scroll').forEach(grid => {
                grid.addEventListener('change', (e) => {
                    if (!e.target.classList.contains('pp-image-style-radio')) return;
                    grid.querySelectorAll('.pp-image-style-card').forEach(card => card.classList.remove('is-selected'));
                    e.target.closest('.pp-image-style-card')?.classList.add('is-selected');
                });
            });

            syncPlatformPanels();
        })();
    </script>
    @endpush
@endonce
