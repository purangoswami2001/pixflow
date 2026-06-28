<x-app-layout>
    @php
        $editing = $editing ?? false;
        $formAction = $editing ? route('posts.update', $post->id) : route('posts.store');
    @endphp

    <x-pp-page-header
        :title="$editing ? 'Edit Post' : 'Create Post'"
        :description="$editing ? 'Update your creative and resubmit for admin approval.' : 'Pick channel and media, then complete details for approval.'"
    />

    @if(!auth()->user()->isAdmin())
        <div class="mb-6 p-4 bg-[#f2f3ff] border border-[#c3c6d7] rounded-xl flex gap-3 text-sm text-[#434655]">
            <span class="material-symbols-outlined text-[#004ac6] shrink-0">info</span>
            <p>You are creating content for your organization's social profiles. Publishing accounts are managed by your admin. Once submitted, an admin will review and approve before the post enters the queue.</p>
    </div>
    @endif

    <form action="{{ $formAction }}" method="POST" id="postForm">
        @csrf
        @if($editing)
            @method('PUT')
        @endif
        <input type="hidden" name="channel" id="postChannel">
        <input type="hidden" name="media_type" id="postMediaType">
        <input type="hidden" name="preset_key" id="postPresetKey">
        <input type="hidden" name="selectedMediaId" id="selectedMediaId">

        <div class="wizard-card">
            <div class="px-6 pt-6">
                <div class="h-1.5 bg-[#f2f3ff] rounded-full overflow-hidden">
                    <div class="h-full bg-[#004ac6] rounded-full transition-all duration-300" id="progressBar" style="width:16%"></div>
            </div>
                <div class="flex justify-between mt-4 flex-wrap gap-2 text-xs" id="stepLabels">
                    @foreach(['Channel','Type','Size','Media','Details','Review'] as $i => $label)
                        <span class="step-label {{ $i === 0 ? 'active font-bold text-[#004ac6]' : 'text-[#737686]' }}" data-step="{{ $i + 1 }}">{{ $label }}</span>
                    @endforeach
                </div>
            </div>

            <div class="p-6 lg:p-8">
                <div class="wizard-step" id="step-1">
                    <h4 class="font-display text-xl font-semibold text-[#131b2e] mb-1">Select channel</h4>
                    <p class="text-sm text-[#737686] mb-6">Choose where you want to publish this post.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($channels as $channel)
                            @php $connected = $accounts->where('provider.value', $channel['key'])->isNotEmpty(); @endphp
                            <button type="button" class="select-card channel-select {{ $connected ? '' : 'opacity-50' }}"
                                data-channel="{{ $channel['key'] }}" {{ $connected ? '' : 'disabled' }}>
                                <span class="material-symbols-outlined text-3xl text-[#004ac6] mb-2 block">{{ $channel['icon'] }}</span>
                                <span class="font-semibold text-[#131b2e] block">{{ $channel['name'] }}</span>
                                <span class="text-xs text-[#737686]">{{ $connected ? 'Connected' : 'Connect account first' }}</span>
                            </button>
                            @endforeach
                    </div>
                    @if($accounts->isEmpty())
                        <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
                            @if(auth()->user()->isAdmin())
                                No social profiles connected. <a href="{{ route('accounts.index') }}" class="font-semibold underline">Connect a profile</a> to start publishing.
                            @else
                                No social profiles are connected yet. Ask your admin to connect organization profiles before you can create posts.
                            @endif
                        </div>
                    @endif
                    </div>
                    
                <div class="wizard-step hidden" id="step-2">
                    <h4 class="font-display text-xl font-semibold text-[#131b2e] mb-1">Select media type</h4>
                    <p class="text-sm text-[#737686] mb-6">Choose image or video for <strong id="postChannelLabel">—</strong>.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="postTypeOptions"></div>
                    </div>

                <div class="wizard-step hidden" id="step-3">
                    <h4 class="font-display text-xl font-semibold text-[#131b2e] mb-1">Select size format</h4>
                    <p class="text-sm text-[#737686] mb-6">Pick the recommended dimensions (if available).</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="postPresetOptions"></div>
                    <div id="postNoPreset" class="hidden mt-4 p-4 bg-[#f2f3ff] rounded-xl border border-[#c3c6d7] text-sm text-[#434655]">
                        No fixed size for this combination. Continue to pick your media.
                    </div>
                </div>

                <div class="wizard-step hidden" id="step-4">
                    <div class="flex justify-between items-start flex-wrap gap-3 mb-6">
                        <div>
                            <h4 class="font-display text-xl font-semibold text-[#131b2e] mb-1">Choose media</h4>
                            <p class="text-sm text-[#737686]">Showing assets matching your configuration.</p>
            </div>
                        <a href="{{ route('media.index') }}" target="_blank" class="pp-btn-secondary text-sm py-2">
                            <span class="material-symbols-outlined text-[16px]">add</span> Upload new media
                        </a>
                    </div>
                    <div id="mediaLoading" class="text-center py-12 text-[#737686] hidden">
                        <span class="inline-block w-5 h-5 border-2 border-[#004ac6] border-t-transparent rounded-full animate-spin mr-2"></span> Loading media...
                    </div>
                    <div id="mediaEmpty" class="text-center py-12 hidden">
                        <span class="material-symbols-outlined text-5xl text-[#c3c6d7] mb-3 block">perm_media</span>
                        <p class="text-sm text-[#737686] mb-4">No media found for this configuration.</p>
                        <a href="{{ route('media.index') }}" class="pp-btn-primary text-sm">Add media first</a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4" id="mediaGrid"></div>
                </div>

                <div class="wizard-step hidden" id="step-5">
                    <h4 class="font-display text-xl font-semibold text-[#131b2e] mb-1">Post details</h4>
                    <p class="text-sm text-[#737686] mb-6">Select account, then complete channel-specific options.</p>
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                        <div class="lg:col-span-3 space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Account</label>
                                <select name="selectedAccount" id="postAccount" class="pp-input" required>
                                    <option value="">— Select account —</option>
                                </select>
                            </div>
                            <div id="boardSelectWrap" class="hidden">
                                <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Pinterest board</label>
                                <select name="selectedDestination" id="postDestination" class="pp-input">
                                    <option value="">— Select board —</option>
                                </select>
                                <p class="text-[11px] text-[#737686] mt-1">Choose which board this pin will publish to.</p>
                            </div>
                            <div id="postTitleWrap">
                                <label class="text-xs font-semibold text-[#737686] uppercase block mb-1" id="postTitleLabel">Pin title</label>
                                <input type="text" name="title" id="postTitle" class="pp-input" placeholder="e.g. Summer Sale Launch">
                                <p class="text-[11px] text-[#737686] mt-1" id="postTitleHint">Shown on Pinterest (max 100 characters).</p>
            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold text-[#737686] uppercase">Caption / description</label>
                                    <div class="relative group" id="aiSuggestWrap">
                                        @if($aiConfigured)
                                            <button type="button" id="aiSuggestBtn" onclick="toggleAiBriefPanel()" class="pp-btn-secondary text-xs py-1 px-2.5 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]">auto_awesome</span>
                                                Generate with AI
                                            </button>
                        @else
                                            <button type="button" disabled class="pp-btn-secondary text-xs py-1 px-2.5 flex items-center gap-1 opacity-50 cursor-not-allowed" aria-disabled="true">
                                                <span class="material-symbols-outlined text-[14px]">auto_awesome</span>
                                                Generate with AI
                                            </button>
                                            <div class="pointer-events-none absolute right-0 top-full z-20 mt-2 w-64 rounded-lg border border-[#c3c6d7] bg-[#131b2e] px-3 py-2 text-[11px] leading-relaxed text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                                Add an OpenAI or Gemini API key in your <code class="text-[#93c5fd]">.env</code> configuration to start generating with AI.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @if($aiConfigured)
                                <div id="aiBriefPanel" class="hidden mb-3 rounded-xl border border-[#c3c6d7] bg-[#f2f3ff] p-4 space-y-3">
                                    <div>
                                        <label for="aiBriefInput" class="text-xs font-semibold text-[#434655] uppercase tracking-wide block mb-1">Describe your post</label>
                                        <textarea id="aiBriefInput" rows="3" class="pp-input text-sm" placeholder="e.g. Summer sale on handmade candles, 20% off this weekend, cozy vibes for home decor lovers"></textarea>
                                        <p class="text-[11px] text-[#737686] mt-1">AI will draft a title, caption, and hashtags using {{ $aiProvider }}.</p>
                                            </div>
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" class="pp-btn-secondary text-xs py-1.5 px-3" onclick="toggleAiBriefPanel(false)">Cancel</button>
                                        <button type="button" id="aiGenerateBtn" onclick="generateWithAi()" class="pp-btn-primary text-xs py-1.5 px-3 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">auto_awesome</span>
                                            Generate
                                        </button>
                                    </div>
                                </div>
                                @endif
                                <textarea name="caption" id="postCaption" rows="4" class="pp-input" placeholder="Write your post copy..." required></textarea>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Hashtags</label>
                                <input type="text" name="hashtagsInput" id="postHashtags" class="pp-input" placeholder="#summer, #sale">
                                <p class="text-[11px] text-[#737686] mt-1">Appended to caption when publishing.</p>
            </div>

                            <div id="platformFieldsSection" class="hidden border-t border-[#c3c6d7] pt-4 mt-2">
                                <h5 class="text-xs font-bold uppercase tracking-wider text-[#004ac6] mb-3" id="platformFieldsHeading">Channel options</h5>
                                <div id="platformFieldsContainer" class="space-y-4"></div>
                        </div>
                        </div>
                        <div class="lg:col-span-2">
                            <div class="pp-card p-5 h-full">
                                <h4 class="font-semibold text-[#131b2e] mb-4">Selected media</h4>
                                <div class="aspect-square rounded-xl overflow-hidden bg-[#f2f3ff] mb-4" id="selectedMediaPreview"></div>
                                <button type="button" class="pp-btn-secondary w-full text-sm" onclick="goToStep(4)">Change media</button>
                            </div>
                    </div>
                </div>
            </div>

                <div class="wizard-step hidden" id="step-6">
                    <h4 class="font-display text-xl font-semibold text-[#131b2e] mb-1">Review & submit</h4>
                    <p class="text-sm text-[#737686] mb-6">Confirm everything looks correct before sending for approval.</p>

                    <label class="pp-card p-4 mb-6 flex justify-between items-center cursor-pointer hover:border-[#004ac6]/30 transition-colors">
                    <div>
                            <span class="font-semibold text-[#131b2e] block">Save as draft</span>
                            <span class="text-xs text-[#737686]">Skip approval queue and save for later.</span>
                    </div>
                        <input type="checkbox" name="is_draft" value="1" id="isDraftSwitch" class="rounded border-[#c3c6d7] text-[#004ac6] w-5 h-5">
                    </label>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                        <div class="pp-card p-5 space-y-4">
                            <div class="flex items-center justify-between gap-2 border-b border-[#c3c6d7] pb-3">
                                <h4 class="font-semibold text-[#131b2e]">Configuration</h4>
                                <span id="reviewChannelBadge" class="pp-status-chip text-[10px] border border-[#c3c6d7] text-[#434655]">—</span>
                            </div>

                            <dl class="text-sm space-y-3">
                                <div class="grid grid-cols-[110px_1fr] gap-2 items-start">
                                    <dt class="text-[#737686]">Channel</dt>
                                    <dd class="font-semibold text-[#131b2e]" id="reviewChannel">—</dd>
                                </div>
                                <div class="grid grid-cols-[110px_1fr] gap-2 items-start">
                                    <dt class="text-[#737686]">Media type</dt>
                                    <dd class="font-semibold text-[#131b2e]" id="reviewType">—</dd>
                                </div>
                                <div class="grid grid-cols-[110px_1fr] gap-2 items-start">
                                    <dt class="text-[#737686]">Size</dt>
                                    <dd class="font-semibold text-[#131b2e]" id="reviewSize">—</dd>
                            </div>
                                <div class="grid grid-cols-[110px_1fr] gap-2 items-start">
                                    <dt class="text-[#737686]">Account</dt>
                                    <dd class="font-semibold text-[#131b2e] text-right break-words" id="reviewAccount">—</dd>
                            </div>
                                <div class="grid grid-cols-[110px_1fr] gap-2 items-start hidden" id="reviewBoardRow">
                                    <dt class="text-[#737686]">Board</dt>
                                    <dd class="font-semibold text-[#131b2e] text-right break-words" id="reviewBoard">—</dd>
                        </div>
                                <div class="grid grid-cols-[110px_1fr] gap-2 items-start hidden" id="reviewTitleRow">
                                    <dt class="text-[#737686]" id="reviewTitleLabel">Pin title</dt>
                                    <dd class="font-semibold text-[#131b2e] break-words" id="reviewTitle">—</dd>
                    </div>
                                <div class="grid grid-cols-[110px_1fr] gap-2 items-start">
                                    <dt class="text-[#737686]">Media file</dt>
                                    <dd class="text-[#434655] break-words text-xs" id="reviewMediaName">—</dd>
                            </div>
                            </dl>

                            <div class="border-t border-[#c3c6d7] pt-4">
                                <p class="text-[10px] font-semibold uppercase text-[#737686] mb-2">Caption</p>
                                <p class="text-sm text-[#434655] whitespace-pre-wrap leading-relaxed" id="reviewCaptionText">—</p>
                                <p class="text-xs text-[#004ac6] mt-2 break-words hidden" id="reviewHashtagsText"></p>
                            </div>

                            <div id="reviewPlatformFieldsSection" class="hidden border-t border-[#c3c6d7] pt-4">
                                <p class="text-[10px] font-semibold uppercase text-[#737686] mb-2" id="reviewPlatformFieldsHeading">Channel options</p>
                                <dl class="text-sm space-y-2" id="reviewPlatformFieldsList"></dl>
                        </div>
                    </div>

                        <div>
                            <p class="text-[10px] font-semibold uppercase text-[#737686] tracking-wide mb-2">Feed preview</p>
                            <div id="reviewPreviewWrap" class="max-w-md mx-auto lg:mx-0 lg:ml-auto"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between border-t border-[#c3c6d7] p-4 px-6 lg:px-8">
                <button type="button" class="pp-btn-secondary invisible" id="prevBtn" onclick="navigateStep(-1)">Back</button>
                <button type="button" class="pp-btn-primary px-6" id="nextBtn" onclick="navigateStep(1)">Continue</button>
                <button type="submit" class="pp-btn-primary px-6 hidden bg-emerald-600 hover:bg-emerald-700" id="submitBtn">
                    <span class="material-symbols-outlined text-[18px]">send</span>
                    <span id="submitBtnLabel">{{ $editing ? 'Resubmit for Approval' : 'Submit for Approval' }}</span>
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
        const optionsUrl = @json(route('media.options'));
        const filterUrl = @json(route('posts.media-filter'));
        const channels = @json(array_values($channels));
        const accounts = @json($accountsJson);
        const editPayload = @json($editPayload);
        const platformFields = @json($platformFields);

        const isEditingPost = @json($editing);

        let currentStep = 1;
        const totalSteps = 6;
        let selectedChannel = null;
        let selectedMediaType = null;
        let selectedPreset = null;
        let availablePresets = [];
        let skipSizeStep = false;
        let selectedMedia = null;

        function channelName(key) {
            return channels.find(c => c.key === key)?.name || key;
        }

        async function fetchJson(url) {
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            return res.json();
        }

        function goToStep(step) {
            if (step < 1 || step > totalSteps) return;

            document.querySelectorAll('.wizard-step').forEach(el => el.classList.add('hidden'));
            document.getElementById('step-' + step).classList.remove('hidden');

            document.querySelectorAll('.step-label').forEach(el => {
                const s = parseInt(el.dataset.step, 10);
                el.classList.toggle('active', s === step);
                el.classList.toggle('text-[#737686]', s !== step);
                el.classList.toggle('text-[#004ac6]', s === step);
                el.classList.toggle('font-bold', s === step);
            });

            document.getElementById('progressBar').style.width = ((step / totalSteps) * 100) + '%';
            currentStep = step;

            document.getElementById('prevBtn').classList.toggle('invisible', step === 1);
            document.getElementById('nextBtn').classList.toggle('hidden', step === totalSteps);
            document.getElementById('submitBtn').classList.toggle('hidden', step !== totalSteps);

            if (step === 5) {
                updatePlatformLabels();
                renderPlatformFields();
            }
            if (step === 6) updateReview();
        }

        function navigateStep(dir) {
            if (dir === 1) {
                if (!validateStep(currentStep)) return;

                if (currentStep === 2 && skipSizeStep) {
                    goToStep(4);
                    loadFilteredMedia();
                    return;
                }
                if (currentStep === 3) {
                    goToStep(4);
                    loadFilteredMedia();
                    return;
                }
            }

            if (dir === -1) {
                if (currentStep === 4 && skipSizeStep) { goToStep(2); return; }
                if (currentStep === 5) { goToStep(4); return; }
            }

            goToStep(currentStep + dir);
        }

        function ppToast(message, type = 'warning', title = null) {
            window.PP?.toast(message, { type, title: title || (type === 'error' ? 'Error' : 'Required') });
        }

        function validateStep(step) {
            if (step === 1 && !selectedChannel) { ppToast('Please select a channel.'); return false; }
            if (step === 2 && !selectedMediaType) { ppToast('Please select a media type.'); return false; }
            if (step === 3 && !skipSizeStep && !selectedPreset) { ppToast('Please select a size format.'); return false; }
            if (step === 4 && !selectedMedia) { ppToast('Please select a media asset.'); return false; }
            if (step === 5) {
                if (!document.getElementById('postAccount').value) { ppToast('Please select an account.'); return false; }
                if (selectedChannel === 'pinterest' && !document.getElementById('postTitle').value.trim()) {
                    ppToast('Please enter a pin title.');
                    return false;
                }
                if (!document.getElementById('postCaption').value.trim()) { ppToast('Please enter a caption.'); return false; }
            }
            return true;
        }

        async function selectChannel(key) {
            selectedChannel = key;
            selectedMediaType = null;
            selectedPreset = null;
            selectedMedia = null;
            document.getElementById('postChannel').value = key;
            document.getElementById('postMediaType').value = '';
            document.getElementById('postPresetKey').value = '';
            document.getElementById('selectedMediaId').value = '';
            document.getElementById('postChannelLabel').textContent = channelName(key);

            updatePlatformLabels();

            document.querySelectorAll('.channel-select').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.channel === key);
            });

            await loadPostMediaTypes();
            goToStep(2);
        }

        async function loadPostMediaTypes() {
            const data = await fetchJson(optionsUrl + '?channel=' + selectedChannel);
            const container = document.getElementById('postTypeOptions');
            container.innerHTML = '';

            (data.media_types || []).forEach(type => {
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6';
                col.innerHTML = `
                    <button type="button" class="select-card type-select" data-type="${type.key}">
                        <span class="material-symbols-outlined text-3xl text-[#004ac6] mb-2 block">${type.icon}</span>
                        <span class="font-semibold text-[#131b2e] block">${type.label}</span>
                        <span class="text-xs text-[#737686]">${type.has_sizes ? 'Size options available' : 'Any size'}</span>
                    </button>`;
                container.appendChild(col);
            });

            container.querySelectorAll('.type-select').forEach(btn => {
                btn.addEventListener('click', () => selectMediaType(btn.dataset.type));
            });
        }

        async function selectMediaType(type) {
            selectedMediaType = type;
            selectedPreset = null;
            selectedMedia = null;
            document.getElementById('postMediaType').value = type;
            document.getElementById('postPresetKey').value = '';
            document.getElementById('selectedMediaId').value = '';

            document.querySelectorAll('.type-select').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.type === type);
            });

            await loadPostPresets();
            if (!skipSizeStep) goToStep(3);
        }

        async function loadPostPresets() {
            const data = await fetchJson(optionsUrl + '?channel=' + selectedChannel + '&media_type=' + selectedMediaType);
            availablePresets = data.presets || [];
            skipSizeStep = availablePresets.length === 0;

            const container = document.getElementById('postPresetOptions');
            const notice = document.getElementById('postNoPreset');
            container.innerHTML = '';

            if (skipSizeStep) {
                notice.classList.remove('hidden');
                selectedPreset = '';
                document.getElementById('postPresetKey').value = '';
                goToStep(4);
                loadFilteredMedia();
                return;
            }

            notice.classList.add('hidden');
            availablePresets.forEach(preset => {
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6';
                col.innerHTML = `
                    <button type="button" class="select-card preset-select" data-preset="${preset.key}">
                        <span class="font-semibold text-[#131b2e] block">${preset.name}</span>
                        <span class="text-xs text-[#737686]">${preset.aspect_ratio} • ${preset.width}×${preset.height}px</span>
                    </button>`;
                container.appendChild(col);
            });

            container.querySelectorAll('.preset-select').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedPreset = btn.dataset.preset;
                    document.getElementById('postPresetKey').value = selectedPreset;
                    document.querySelectorAll('.preset-select').forEach(b => b.classList.toggle('active', b.dataset.preset === selectedPreset));
                    goToStep(4);
                    loadFilteredMedia();
                });
            });
        }

        async function loadFilteredMedia() {
            const loading = document.getElementById('mediaLoading');
            const empty = document.getElementById('mediaEmpty');
            const grid = document.getElementById('mediaGrid');

            loading.classList.remove('hidden');
            empty.classList.add('hidden');
            grid.innerHTML = '';

            let url = filterUrl + '?channel=' + selectedChannel + '&media_type=' + selectedMediaType;
            if (selectedPreset) url += '&preset_key=' + selectedPreset;

            const data = await fetchJson(url);
            loading.classList.add('hidden');

            if (!data.assets || data.assets.length === 0) {
                empty.classList.remove('hidden');
                return;
            }

            data.assets.forEach(asset => {
                const item = document.createElement('div');
                item.className = 'media-item';
                item.dataset.id = asset.id;
                item.dataset.url = asset.url;
                item.dataset.name = asset.original_name;
                item.innerHTML = `
                    <div class="aspect-square overflow-hidden relative">
                        ${asset.is_video
                            ? `<video src="${asset.url}" class="w-full h-full object-cover" muted></video>`
                            : `<img src="${asset.url}" class="w-full h-full object-cover cursor-zoom-in" alt=""
                                   data-pp-lightbox="${asset.url}" data-pp-lightbox-caption="${asset.original_name}" title="View full image">`}
                        <button type="button" class="absolute top-1.5 right-1.5 p-1 rounded-full bg-black/55 text-white hover:bg-[#004ac6] transition-colors"
                                data-pp-lightbox="${asset.url}" data-pp-lightbox-caption="${asset.original_name}" title="View full image">
                            <span class="material-symbols-outlined text-[16px]">fullscreen</span>
                        </button>
                    </div>
                    <div class="p-2 text-xs text-center truncate text-[#434655]">${asset.original_name}</div>`;
                grid.appendChild(item);
            });

            grid.querySelectorAll('.media-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.closest('[data-pp-lightbox]')) return;
                    selectMedia(item);
                });
            });
        }

        function selectMedia(el) {
            selectedMedia = {
                id: el.dataset.id,
                url: el.dataset.url,
                name: el.dataset.name,
            };
            document.getElementById('selectedMediaId').value = selectedMedia.id;
            document.getElementById('mediaGrid').querySelectorAll('.media-item').forEach(i => i.classList.toggle('active', i === el));

            document.getElementById('selectedMediaPreview').innerHTML = selectedMedia.url.match(/\.mp4|video/)
                ? `<video src="${selectedMedia.url}" class="w-full h-full object-cover" muted autoplay loop></video>`
                : `<img src="${selectedMedia.url}" class="w-full h-full object-cover cursor-zoom-in" alt=""
                       data-pp-lightbox="${selectedMedia.url}" data-pp-lightbox-caption="${selectedMedia.name}" title="View full image">`;

            populateAccounts();
            updatePlatformLabels();
        }

        function updatePlatformLabels() {
            const titleWrap = document.getElementById('postTitleWrap');
            const titleInput = document.getElementById('postTitle');
            const titleLabel = document.getElementById('postTitleLabel');
            const titleHint = document.getElementById('postTitleHint');
            const heading = document.getElementById('platformFieldsHeading');
            const usesTitle = selectedChannel === 'pinterest';

            titleWrap.classList.toggle('hidden', !usesTitle);
            if (usesTitle) {
                titleInput.setAttribute('required', 'required');
                titleLabel.textContent = 'Pin title';
                titleHint.textContent = 'Shown on Pinterest (max 100 characters).';
            } else {
                titleInput.removeAttribute('required');
            }

            heading.textContent = (channelName(selectedChannel) || 'Channel') + ' options';
        }

        function fieldShouldShow(field) {
            if (!field.show_when) return true;

            if (field.show_when.media_type && field.show_when.media_type !== selectedMediaType) {
                return false;
            }

            if (field.show_when.has_link) {
                const linkInput = document.querySelector('[name="platformFields[link_url]"]');
                return linkInput && linkInput.value.trim() !== '';
            }

            return true;
        }

        function capturePlatformFieldValues() {
            const values = {};
            document.querySelectorAll('#platformFieldsContainer .platform-field-input').forEach(el => {
                const key = el.id?.replace('pf_', '');
                if (!key) return;
                if (el.type === 'checkbox') {
                    values[key] = el.checked;
                } else {
                    values[key] = el.value;
                }
            });
            return values;
        }

        function renderPlatformFields() {
            const section = document.getElementById('platformFieldsSection');
            const container = document.getElementById('platformFieldsContainer');
            const accountId = document.getElementById('postAccount').value;
            const fields = platformFields[selectedChannel] || [];
            const captured = capturePlatformFieldValues();
            const saved = { ...(editPayload?.platform_fields || {}), ...captured };

            if (!selectedChannel || !accountId || fields.length === 0) {
                section.classList.add('hidden');
                container.innerHTML = '';
                return;
            }

            section.classList.remove('hidden');
            container.innerHTML = '';

            fields.forEach(field => {
                const wrap = document.createElement('div');
                wrap.className = 'platform-field-wrap';
                wrap.dataset.fieldKey = field.key;
                if (!fieldShouldShow(field)) {
                    wrap.classList.add('hidden');
                }

                const label = document.createElement('label');
                label.className = 'text-xs font-semibold text-[#737686] uppercase block mb-1';
                label.textContent = field.label + (field.required ? ' *' : '');
                label.setAttribute('for', 'pf_' + field.key);

                let inputHtml = '';
                const name = `platformFields[${field.key}]`;
                const value = saved[field.key] ?? '';

                if (field.type === 'textarea') {
                    inputHtml = `<textarea name="${name}" id="pf_${field.key}" rows="${field.rows || 3}" class="pp-input platform-field-input" placeholder="${field.placeholder || ''}">${value}</textarea>`;
                } else if (field.type === 'checkbox') {
                    const checked = value === true || value === 1 || value === '1' || (value === '' && field.default);
                    inputHtml = `
                        <label class="flex items-start gap-3 cursor-pointer mt-1">
                            <input type="hidden" name="${name}" value="0">
                            <input type="checkbox" name="${name}" id="pf_${field.key}" value="1" class="mt-1 rounded border-[#c3c6d7] text-[#004ac6] platform-field-input" ${checked ? 'checked' : ''}>
                            <span class="text-sm text-[#434655]">${field.hint || field.label}</span>
                        </label>`;
                } else if (field.type === 'color') {
                    const colorVal = value || field.placeholder || '#493fd8';
                    inputHtml = `<input type="color" name="${name}" id="pf_${field.key}" class="pp-input h-10 w-20 p-1 platform-field-input" value="${colorVal}">`;
                } else if (field.type === 'datetime-local') {
                    inputHtml = `<input type="datetime-local" name="${name}" id="pf_${field.key}" class="pp-input platform-field-input" value="${value}">`;
                } else {
                    const inputType = field.type === 'url' ? 'url' : 'text';
                    inputHtml = `<input type="${inputType}" name="${name}" id="pf_${field.key}" class="pp-input platform-field-input" placeholder="${field.placeholder || ''}" value="${value}">`;
                }

                wrap.innerHTML = label.outerHTML + inputHtml;

                if (field.hint && field.type !== 'checkbox') {
                    const hint = document.createElement('p');
                    hint.className = 'text-[11px] text-[#737686] mt-1';
                    hint.textContent = field.hint;
                    wrap.appendChild(hint);
                }

                container.appendChild(wrap);
            });

            updatePlatformFieldVisibility();
            bindPlatformFieldVisibility();
        }

        function updatePlatformFieldVisibility() {
            if (selectedChannel !== 'facebook' && selectedChannel !== 'instagram') {
                return;
            }

            const container = document.getElementById('platformFieldsContainer');
            const fields = platformFields[selectedChannel] || [];

            container.querySelectorAll('.platform-field-wrap').forEach(wrap => {
                const field = fields.find(f => f.key === wrap.dataset.fieldKey);
                if (!field) return;
                wrap.classList.toggle('hidden', !fieldShouldShow(field));
            });
        }

        function bindPlatformFieldVisibility() {
            const linkInput = document.querySelector('[name="platformFields[link_url]"]');
            if (linkInput && !linkInput.dataset.visibilityBound) {
                linkInput.dataset.visibilityBound = '1';
                linkInput.addEventListener('input', updatePlatformFieldVisibility);
                linkInput.addEventListener('change', updatePlatformFieldVisibility);
            }
        }

        function populateAccounts() {
            const select = document.getElementById('postAccount');
            select.innerHTML = '<option value="">— Select account —</option>';

            const readyAccounts = accounts.filter(a => a.provider === selectedChannel && a.ready);
            const pendingAccounts = accounts.filter(a => a.provider === selectedChannel && !a.ready);

            readyAccounts.forEach(acc => {
                const opt = document.createElement('option');
                opt.value = acc.id;
                const dest = acc.destinations?.[0];
                opt.textContent = selectedChannel === 'pinterest'
                    ? acc.name
                    : (dest?.name || acc.name);
                select.appendChild(opt);
            });

            pendingAccounts.forEach(acc => {
                const opt = document.createElement('option');
                opt.value = '';
                opt.disabled = true;
                opt.textContent = `${acc.name} (boards required)`;
                select.appendChild(opt);
            });

            if (!readyAccounts.length && pendingAccounts.length) {
                const hint = document.createElement('option');
                hint.value = '';
                hint.disabled = true;
                hint.textContent = 'Open Accounts → Manage boards for Pinterest';
                select.insertBefore(hint, select.options[1] || null);
            }

            populateBoards();
            renderPlatformFields();
        }

        function populateBoards() {
            const boardWrap = document.getElementById('boardSelectWrap');
            const boardSelect = document.getElementById('postDestination');
            const accountId = document.getElementById('postAccount').value;
            const account = accounts.find(a => String(a.id) === String(accountId));

            boardSelect.innerHTML = '<option value="">— Select board —</option>';

            if (!account || selectedChannel !== 'pinterest' || !account.destinations?.length) {
                boardWrap.classList.add('hidden');
                boardSelect.removeAttribute('required');
                return;
            }

            boardWrap.classList.remove('hidden');
            boardSelect.setAttribute('required', 'required');

            account.destinations.forEach(dest => {
                const opt = document.createElement('option');
                opt.value = dest.id;
                opt.textContent = dest.label || dest.name;
                boardSelect.appendChild(opt);
            });

            if (account.destinations.length === 1) {
                boardSelect.value = account.destinations[0].id;
            }

            renderPlatformFields();
        }

        const channelAccent = {
            facebook: 'text-[#1877F2] bg-[#1877F2]/10 border-[#1877F2]/25',
            instagram: 'text-[#E4405F] bg-gradient-to-r from-[#833AB4]/10 via-[#E4405F]/10 to-[#FCAF45]/10 border-[#E4405F]/25',
            pinterest: 'text-[#BD081C] bg-[#BD081C]/10 border-[#BD081C]/25',
        };

        const platformIcons = {
            facebook: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            instagram: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
            pinterest: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0a12 12 0 00-1.9 23.8c-.1-.9-.2-2.2 0-3.2.2-.9 1.3-5.9 1.3-5.9s-.3-.6-.3-1.5c0-1.4.8-2.5 1.9-2.5.9 0 1.3.7 1.3 1.5 0 .9-.6 2.3-.9 3.6-.3 1.1.5 2 1.6 2 1.9 0 3.4-2 3.4-4.9 0-2.6-1.9-4.4-4.6-4.4-3.1 0-4.9 2.3-4.9 4.7 0 .9.4 1.9.8 2.4.1.1.1.2.1.3-.1.4-.3 1.3-.4 1.5-.1.2-.3.3-.5.2-1.4-.6-2.2-2.5-2.2-4 0-3.3 2.4-6.3 6.9-6.3 3.6 0 6.4 2.6 6.4 6.1 0 3.6-2.3 6.5-5.5 6.5-1.1 0-2.1-.6-2.4-1.3l-.7 2.6c-.2 1-.8 2.3-1.2 3.1C9.6 23.8 10.8 24 12 24c6.6 0 12-5.4 12-12S18.6 0 12 0z"/></svg>',
        };

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function selectedAccount() {
            const accountId = document.getElementById('postAccount').value;
            return accounts.find(a => String(a.id) === String(accountId)) || null;
        }

        function resolveAccountLabels() {
            const account = selectedAccount();
            if (!account) {
                return { accountName: '—', publishLabel: '—', boardName: null, handle: '—' };
            }

            if (selectedChannel === 'pinterest') {
                const destId = document.getElementById('postDestination').value;
                const dest = account.destinations?.find(d => String(d.id) === String(destId));
                return {
                    accountName: account.name,
                    publishLabel: dest?.label || account.name,
                    boardName: dest?.name || null,
                    handle: account.username || account.name,
                };
            }

            const destination = account.destinations?.[0] || null;
            const displayName = destination?.name || account.name;

            return {
                accountName: displayName,
                publishLabel: displayName,
                boardName: null,
                handle: account.username || account.name,
            };
        }

        function collectPlatformFieldSummary() {
            const fields = platformFields[selectedChannel] || [];
            const values = capturePlatformFieldValues();
            const summary = [];

            fields.forEach(field => {
                if (!fieldShouldShow(field)) return;

                let value = values[field.key];
                if (field.type === 'checkbox') {
                    value = value ? 'Yes' : 'No';
                } else if (value === null || value === undefined || value === '') {
                    return;
                } else {
                    value = String(value);
                }

                summary.push({ label: field.label, value });
            });

            return summary;
        }

        function reviewMediaStyle() {
            const preset = availablePresets.find(p => p.key === selectedPreset);
            if (preset?.width && preset?.height) {
                return `aspect-ratio: ${preset.width} / ${preset.height}; max-height: 420px; width: 100%;`;
            }

            const defaults = {
                facebook: 'aspect-ratio: 1200 / 630; max-height: 280px; width: 100%;',
                instagram: 'aspect-ratio: 1 / 1; max-height: 400px; width: 100%;',
                pinterest: 'aspect-ratio: 2 / 3; max-height: 420px; width: 100%;',
            };

            return defaults[selectedChannel] || 'aspect-ratio: 4 / 3; max-height: 320px; width: 100%;';
        }

        function reviewMediaHtml() {
            if (!selectedMedia) {
                return `<div class="pp-media-frame bg-[#131b2e] flex items-center justify-center" style="${reviewMediaStyle()}"><span class="material-symbols-outlined text-3xl text-[#737686]">image_not_supported</span></div>`;
            }

            const isVideo = selectedMedia.url.match(/\.mp4|video/i);
            const asset = isVideo
                ? `<video src="${selectedMedia.url}" class="pp-media-frame__asset" muted autoplay loop playsinline></video>`
                : `<img src="${selectedMedia.url}" class="pp-media-frame__asset cursor-zoom-in" alt=""
                       data-pp-lightbox="${selectedMedia.url}" title="View full image">`;

            return `<div class="pp-media-frame bg-[#131b2e]" style="${reviewMediaStyle()}">${asset}</div>`;
        }

        function buildReviewPreview() {
            const title = document.getElementById('postTitle').value.trim();
            const caption = document.getElementById('postCaption').value.trim();
            const hashtags = document.getElementById('postHashtags').value.trim();
            const labels = resolveAccountLabels();
            const accountLabel = escapeHtml(labels.publishLabel);
            const handle = escapeHtml(labels.handle);
            const captionHtml = escapeHtml(caption) + (hashtags ? ` <span class="text-[#004ac6]">${escapeHtml(hashtags)}</span>` : '');
            const media = reviewMediaHtml();
            const initials = escapeHtml((labels.accountName || 'A').substring(0, 1).toUpperCase());
            const icon = platformIcons[selectedChannel] || '';

            if (selectedChannel === 'instagram') {
                return `
                    <div class="feed-preview feed-preview-instagram shadow-sm">
                        <div class="px-3 py-2.5 flex items-center gap-2 border-b border-[#efefef]">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-[#833AB4] via-[#E4405F] to-[#FCAF45] p-[2px] shrink-0">
                                <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-[11px] font-bold text-[#E4405F]">${initials}</div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-[#262626] truncate">${accountLabel}</p>
                                <p class="text-[10px] text-[#8e8e8e] truncate">${handle}</p>
                            </div>
                            <span class="text-[#E4405F] shrink-0">${icon}</span>
                        </div>
                        <div class="bg-black">${media}</div>
                        <div class="px-3 py-2 flex gap-3 text-[#262626]">
                            <span class="material-symbols-outlined text-[22px]">favorite_border</span>
                            <span class="material-symbols-outlined text-[22px]">chat_bubble_outline</span>
                            <span class="material-symbols-outlined text-[22px]">send</span>
                            <span class="material-symbols-outlined text-[22px] ml-auto">bookmark_border</span>
                        </div>
                        <div class="px-3 pb-3 text-xs text-[#262626] leading-relaxed">
                            <span class="font-semibold">${handle}</span> ${captionHtml}
                        </div>
                    </div>`;
            }

            if (selectedChannel === 'pinterest') {
                return `
                    <div class="feed-preview feed-preview-pinterest shadow-sm">
                        <div class="bg-[#efefef]">${media}</div>
                        <div class="p-3">
                            <p class="text-sm font-semibold text-[#111] mb-1 line-clamp-2">${escapeHtml(title)}</p>
                            <p class="text-xs text-[#111] line-clamp-4 mb-2 leading-relaxed">${captionHtml}</p>
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-[#BD081C]/10 text-[#BD081C] flex items-center justify-center shrink-0">${icon}</div>
                                <span class="text-xs font-semibold text-[#111] truncate">${accountLabel}</span>
                            </div>
                        </div>
                    </div>`;
            }

            return `
                <div class="feed-preview feed-preview-facebook shadow-sm">
                    <div class="px-3 py-2.5 flex items-center gap-2">
                        <div class="w-9 h-9 rounded-full bg-[#1877F2] text-white flex items-center justify-center shrink-0">${icon}</div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-[#050505] truncate">${accountLabel}</p>
                            <p class="text-[10px] text-[#65676b] truncate">${handle} · <span class="material-symbols-outlined text-[11px] align-middle">public</span></p>
                        </div>
                        <span class="material-symbols-outlined text-[#65676b]">more_horiz</span>
                    </div>
                    <p class="px-3 pb-2 text-sm text-[#050505] whitespace-pre-wrap leading-relaxed">${captionHtml}</p>
                    <div class="bg-[#f0f2f5]">${media}</div>
                    <div class="px-3 py-2 flex justify-around border-t border-[#dadde1] text-[#65676b] text-xs">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">thumb_up</span> Like</span>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">chat_bubble_outline</span> Comment</span>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">share</span> Share</span>
                    </div>
                </div>`;
        }

        function updateReview() {
            const preset = availablePresets.find(p => p.key === selectedPreset);
            const labels = resolveAccountLabels();
            const title = document.getElementById('postTitle').value.trim();
            const caption = document.getElementById('postCaption').value.trim();
            const hashtags = document.getElementById('postHashtags').value.trim();
            const channelLabel = channelName(selectedChannel);

            document.getElementById('reviewChannel').textContent = channelLabel;
            document.getElementById('reviewType').textContent = selectedMediaType
                ? selectedMediaType.charAt(0).toUpperCase() + selectedMediaType.slice(1)
                : '—';
            document.getElementById('reviewSize').textContent = preset
                ? `${preset.name} (${preset.width}×${preset.height})`
                : 'Any size';

            document.getElementById('reviewAccount').textContent = labels.accountName;
            const boardRow = document.getElementById('reviewBoardRow');
            if (selectedChannel === 'pinterest' && labels.boardName) {
                boardRow.classList.remove('hidden');
                document.getElementById('reviewBoard').textContent = labels.boardName;
            } else {
                boardRow.classList.add('hidden');
            }

            const titleRow = document.getElementById('reviewTitleRow');
            if (selectedChannel === 'pinterest') {
                titleRow.classList.remove('hidden');
                document.getElementById('reviewTitle').textContent = title || '—';
            } else {
                titleRow.classList.add('hidden');
            }
            document.getElementById('reviewMediaName').textContent = selectedMedia?.name || '—';

            document.getElementById('reviewCaptionText').textContent = caption || '—';
            const hashtagsEl = document.getElementById('reviewHashtagsText');
            if (hashtags) {
                hashtagsEl.textContent = hashtags;
                hashtagsEl.classList.remove('hidden');
            } else {
                hashtagsEl.textContent = '';
                hashtagsEl.classList.add('hidden');
            }

            const badge = document.getElementById('reviewChannelBadge');
            badge.textContent = channelLabel;
            badge.className = `pp-status-chip text-[10px] border ${channelAccent[selectedChannel] || 'bg-[#f2f3ff] text-[#434655] border-[#c3c6d7]'}`;

            const platformSummary = collectPlatformFieldSummary();
            const platformSection = document.getElementById('reviewPlatformFieldsSection');
            const platformList = document.getElementById('reviewPlatformFieldsList');
            document.getElementById('reviewPlatformFieldsHeading').textContent = channelLabel + ' options';

            if (platformSummary.length) {
                platformSection.classList.remove('hidden');
                platformList.innerHTML = platformSummary.map(item => `
                    <div class="grid grid-cols-[110px_1fr] gap-2 items-start py-1 border-b border-[#c3c6d7]/40 last:border-0">
                        <dt class="text-[#737686] text-xs">${escapeHtml(item.label)}</dt>
                        <dd class="text-[#131b2e] text-xs break-words whitespace-pre-wrap">${escapeHtml(item.value)}</dd>
                    </div>
                `).join('');
            } else {
                platformSection.classList.add('hidden');
                platformList.innerHTML = '';
            }

            document.getElementById('reviewPreviewWrap').innerHTML = buildReviewPreview();
        }

        document.querySelectorAll('.channel-select:not(:disabled)').forEach(btn => {
            btn.addEventListener('click', () => selectChannel(btn.dataset.channel));
        });

        async function generateWithAi() {
            const brief = document.getElementById('aiBriefInput')?.value.trim() || '';
            if (brief.length < 5) {
                ppToast('Please describe your post in a few words (at least 5 characters).');
                return;
            }
            if (!selectedChannel) {
                ppToast('Select a channel first.');
                return;
            }

            const btn = document.getElementById('aiGenerateBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px] animate-spin">progress_activity</span> Generating...';

            try {
                const res = await fetch(@json(route('posts.suggest')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                },
                body: JSON.stringify({
                        brief,
                        platform: selectedChannel,
                    }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Request failed');
                }

                if (selectedChannel === 'pinterest' && data.title) {
                    document.getElementById('postTitle').value = data.title;
                }
                if (data.caption) document.getElementById('postCaption').value = data.caption;
                if (data.hashtags) {
                    document.getElementById('postHashtags').value = data.hashtags.join(' ');
                }

                toggleAiBriefPanel(false);
            } catch (e) {
                ppToast(e.message || 'Could not generate content. Check your API key and try again.', 'error', 'AI generation failed');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        function toggleAiBriefPanel(show) {
            const panel = document.getElementById('aiBriefPanel');
            if (!panel) return;

            const shouldShow = typeof show === 'boolean' ? show : panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !shouldShow);

            if (shouldShow) {
                document.getElementById('aiBriefInput')?.focus();
            }
        }

        async function initEditMode() {
            if (!editPayload || !editPayload.channel) return;

            selectedChannel = editPayload.channel;
            document.getElementById('postChannel').value = editPayload.channel;
            document.getElementById('postChannelLabel').textContent = channelName(editPayload.channel);
            document.querySelectorAll('.channel-select').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.channel === editPayload.channel);
            });

            await loadPostMediaTypes();
            selectedMediaType = editPayload.media_type;
            document.getElementById('postMediaType').value = editPayload.media_type || '';
            document.querySelectorAll('.type-select').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.type === editPayload.media_type);
            });

            await loadPostPresets();
            if (editPayload.preset_key) {
                selectedPreset = editPayload.preset_key;
                document.getElementById('postPresetKey').value = editPayload.preset_key;
                document.querySelectorAll('.preset-select').forEach(b => {
                    b.classList.toggle('active', b.dataset.preset === editPayload.preset_key);
                });
            }

            await loadFilteredMedia();

            if (editPayload.media_id) {
                const item = document.querySelector(`.media-item[data-id="${editPayload.media_id}"]`);
                if (item) {
                    selectMedia(item);
                }
            } else if (editPayload.has_archived_media && editPayload.media_url) {
                selectedMedia = {
                    id: null,
                    url: editPayload.media_url,
                    name: editPayload.media_name || 'Post media',
                };
                document.getElementById('selectedMediaId').value = '';
                document.getElementById('selectedMediaPreview').innerHTML = editPayload.media_url.match(/\.mp4|video/i)
                    ? `<video src="${editPayload.media_url}" class="w-full h-full object-cover" muted autoplay loop></video>`
                    : `<img src="${editPayload.media_url}" class="w-full h-full object-cover cursor-zoom-in" alt=""
                           data-pp-lightbox="${editPayload.media_url}" title="View full image">`;
            }

            document.getElementById('postTitle').value = editPayload.title || '';
            document.getElementById('postCaption').value = editPayload.caption || '';
            document.getElementById('postHashtags').value = editPayload.hashtags || '';

            populateAccounts();
            updatePlatformLabels();
            if (editPayload.account_id) {
                document.getElementById('postAccount').value = editPayload.account_id;
                populateBoards();
            }
            if (editPayload.destination_id) {
                document.getElementById('postDestination').value = editPayload.destination_id;
            }

            goToStep(5);
        }

        document.getElementById('postAccount')?.addEventListener('change', () => {
            populateBoards();
            renderPlatformFields();
        });
        document.getElementById('postDestination')?.addEventListener('change', renderPlatformFields);

        document.getElementById('isDraftSwitch')?.addEventListener('change', function () {
            const label = document.getElementById('submitBtnLabel');
            if (!label) return;
            label.textContent = this.checked
                ? 'Save draft'
                : (isEditingPost ? 'Resubmit for Approval' : 'Submit for Approval');
        });

        if (editPayload) {
            initEditMode();
        }
    </script>
    @endpush
</x-app-layout>
