<x-app-layout>
    <x-pp-page-header title="Upload Media" description="Configure channel, type, and size, then upload up to 10 files at once.">
        <x-slot name="actions">
            <a href="{{ route('media.index') }}" class="pp-btn-secondary">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span class="pp-action-label">Back</span>
            </a>
        </x-slot>
    </x-pp-page-header>

    <div>
        <div class="pp-card overflow-hidden">
            <div class="pp-card-header flex items-center justify-between flex-wrap gap-3">
                <h3 class="font-display font-semibold text-[#131b2e] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#004ac6]">cloud_upload</span>
                    New Media
                </h3>
                <div class="flex gap-2 flex-wrap" id="uploadStepPills">
                    <span class="wizard-pill upload-pill active" data-step="1">1. Channel</span>
                    <span class="wizard-pill upload-pill" data-step="2">2. Type</span>
                    <span class="wizard-pill upload-pill" data-step="3">3. Size</span>
                    <span class="wizard-pill upload-pill" data-step="4">4. Upload</span>
                </div>
            </div>

            <form action="{{ route('media.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <input type="hidden" name="channel" id="uploadChannel" value="{{ old('channel') }}">
                <input type="hidden" name="media_type" id="uploadMediaType" value="{{ old('media_type') }}">
                <input type="hidden" name="preset_key" id="uploadPresetKey" value="{{ old('preset_key') }}">

                <div class="p-6">
                    @if($errors->any())
                        <div class="pp-alert-error mb-4 text-sm">
                            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                    @endif

                    <div class="upload-step" id="upload-step-1">
                        <h4 class="font-display font-semibold text-[#131b2e] mb-1">Choose target channel</h4>
                        <p class="text-sm text-[#737686] mb-6">Select where this media will be published.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($channels as $channel)
                                <button type="button" class="select-card channel-card" data-channel="{{ $channel['key'] }}">
                                    <span class="material-symbols-outlined text-3xl text-[#004ac6] mb-2 block">{{ $channel['icon'] }}</span>
                                    <span class="font-semibold text-[#131b2e] block">{{ $channel['name'] }}</span>
                                    <span class="text-xs text-[#737686]">Configure for {{ $channel['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="upload-step hidden" id="upload-step-2">
                        <h4 class="font-display font-semibold text-[#131b2e] mb-1">Choose media type</h4>
                        <p class="text-sm text-[#737686] mb-6">Pick image or video for <strong id="selectedChannelLabel">—</strong>.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="mediaTypeOptions"></div>
                    </div>

                    <div class="upload-step hidden" id="upload-step-3">
                        <h4 class="font-display font-semibold text-[#131b2e] mb-1">Choose size format</h4>
                        <p class="text-sm text-[#737686] mb-6">Select the recommended dimensions.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="presetOptions"></div>
                        <div id="noPresetNotice" class="hidden mt-4 p-4 bg-[#f2f3ff] rounded-xl border border-[#c3c6d7] text-sm text-[#434655]">
                            <span class="material-symbols-outlined text-[#004ac6] align-middle mr-1">info</span>
                            No fixed size required. Continue to upload your file.
                        </div>
                    </div>

                    <div class="upload-step hidden" id="upload-step-4">
                        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                            <div class="lg:col-span-3">
                                <h4 class="font-display font-semibold text-[#131b2e] mb-1">Upload your files</h4>
                                <p class="text-sm text-[#737686] mb-4">Select up to 10 files. They will all use the same channel configuration.</p>

                                <input type="file" name="files[]" id="uploadFiles" class="hidden" accept="image/*,video/*" multiple required>

                                <div class="upload-dropzone relative" id="uploadDropzone">
                                    <div id="uploadDropzoneEmpty">
                                        <span class="material-symbols-outlined text-5xl text-[#004ac6] mb-3 block">cloud_upload</span>
                                        <p class="font-semibold text-[#131b2e] mb-1">Drop files here or click to browse</p>
                                        <p class="text-xs text-[#737686]" id="uploadAcceptHint">JPEG, PNG, WebP, MP4 — max {{ \App\Support\MediaUpload::maxMbLabel() }} each · up to {{ \App\Support\MediaUpload::maxFiles() }} files</p>
                                    </div>

                                    <div id="uploadFilePreview" class="hidden text-left">
                                        <div class="flex items-center justify-between gap-2 mb-3">
                                            <p id="uploadFileCount" class="text-sm font-semibold text-[#131b2e]"></p>
                                            <button type="button" id="uploadClearFiles" class="text-xs font-semibold text-[#93000a] hover:underline">Clear all</button>
                                        </div>
                                        <div id="uploadFilesGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-64 overflow-y-auto mb-3"></div>
                                        <button type="button" id="uploadChangeFile" class="text-xs font-semibold text-[#004ac6] hover:underline flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px]">add</span> Add more files
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-2">
                                <div class="pp-card p-5 h-full">
                                    <h4 class="font-semibold text-[#131b2e] mb-4">Configuration Summary</h4>
                                    <ul class="text-sm space-y-2 mb-4">
                                        <li class="flex justify-between py-2 border-b border-[#c3c6d7]"><span class="text-[#737686]">Channel</span><strong id="summaryChannel">—</strong></li>
                                        <li class="flex justify-between py-2 border-b border-[#c3c6d7]"><span class="text-[#737686]">Type</span><strong id="summaryType">—</strong></li>
                                        <li class="flex justify-between py-2"><span class="text-[#737686]">Size</span><strong id="summarySize">—</strong></li>
                                    </ul>
                                    <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Alt text (optional)</label>
                                    <textarea name="alt_text" id="altText" rows="2" class="pp-input text-sm" placeholder="Describe for accessibility">{{ old('alt_text') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center border-t border-[#c3c6d7] p-4 px-6">
                    <button type="button" class="pp-btn-secondary invisible" id="uploadPrevBtn">Back</button>
                    <div class="flex gap-2">
                        <a href="{{ route('media.index') }}" class="pp-btn-secondary">Cancel</a>
                        <button type="button" class="pp-btn-primary" id="uploadNextBtn">Continue</button>
                        <button type="submit" class="pp-btn-primary hidden bg-emerald-600 hover:bg-emerald-700" id="uploadSubmitBtn">
                            <span class="material-symbols-outlined text-[18px]">check</span>
                            <span id="uploadSubmitLabel">Save Media</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const MAX_UPLOAD_FILES = @json(\App\Support\MediaUpload::maxFiles());
        const MAX_UPLOAD_BYTES = @json(\App\Support\MediaUpload::maxKb() * 1024);
        const MAX_UPLOAD_LABEL = @json(\App\Support\MediaUpload::maxMbLabel());
        let uploadPreviewObjectUrls = [];
        let addingMoreFiles = false;

        function formatFileSize(bytes) {
            if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            return (bytes / 1024).toFixed(1) + ' KB';
        }

        function validateUploadFile(file, mediaType) {
            const type = (file.type || '').toLowerCase();
            const name = file.name || '';
            if (mediaType === 'image') {
                const ok = type.startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(name);
                if (!ok) return `"${name}" is not a JPEG, PNG, or WebP image.`;
            } else if (mediaType === 'video') {
                const ok = type === 'video/mp4' || type.startsWith('video/') || /\.mp4$/i.test(name);
                if (!ok) return `"${name}" is not an MP4 video.`;
            }
            if (file.size > MAX_UPLOAD_BYTES) {
                return `"${name}" is ${formatFileSize(file.size)}. Maximum size is ${MAX_UPLOAD_LABEL}.`;
            }
            return null;
        }

        function clearFilePreview() {
            const fileInput = document.getElementById('uploadFiles');
            const empty = document.getElementById('uploadDropzoneEmpty');
            const preview = document.getElementById('uploadFilePreview');
            const grid = document.getElementById('uploadFilesGrid');

            uploadPreviewObjectUrls.forEach(url => URL.revokeObjectURL(url));
            uploadPreviewObjectUrls = [];

            fileInput.value = '';
            grid.innerHTML = '';
            preview.classList.add('hidden');
            empty.classList.remove('hidden');
            updateUploadSubmitLabel(0);
        }

        function updateUploadSubmitLabel(count) {
            const label = document.getElementById('uploadSubmitLabel');
            if (!label) return;
            label.textContent = count <= 1 ? 'Save Media' : `Save ${count} Files`;
        }

        function filesFromInput() {
            return Array.from(document.getElementById('uploadFiles').files || []);
        }

        function setFilesOnInput(files) {
            const fileInput = document.getElementById('uploadFiles');
            const dt = new DataTransfer();
            files.slice(0, MAX_UPLOAD_FILES).forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            renderFilesPreview(dt.files);
        }

        function mergeFiles(existing, incoming) {
            const merged = [...existing];
            const names = new Set(existing.map(f => f.name + f.size + f.lastModified));

            for (const file of incoming) {
                const key = file.name + file.size + file.lastModified;
                if (names.has(key)) continue;
                if (merged.length >= MAX_UPLOAD_FILES) break;
                merged.push(file);
                names.add(key);
            }

            return merged;
        }

        function renderFilesPreview(fileList) {
            const files = Array.from(fileList || []);
            const empty = document.getElementById('uploadDropzoneEmpty');
            const preview = document.getElementById('uploadFilePreview');
            const grid = document.getElementById('uploadFilesGrid');
            const countEl = document.getElementById('uploadFileCount');
            const addBtn = document.getElementById('uploadChangeFile');

            uploadPreviewObjectUrls.forEach(url => URL.revokeObjectURL(url));
            uploadPreviewObjectUrls = [];
            grid.innerHTML = '';

            if (!files.length) {
                preview.classList.add('hidden');
                empty.classList.remove('hidden');
                updateUploadSubmitLabel(0);
                return;
            }

            countEl.textContent = `${files.length} file${files.length === 1 ? '' : 's'} selected${files.length >= MAX_UPLOAD_FILES ? ' (max)' : ''}`;
            addBtn.classList.toggle('hidden', files.length >= MAX_UPLOAD_FILES);

            files.forEach(file => {
                const url = URL.createObjectURL(file);
                uploadPreviewObjectUrls.push(url);
                const sizeKb = formatFileSize(file.size);
                const isVideo = file.type.startsWith('video/');
                const item = document.createElement('div');
                item.className = 'rounded-lg border border-[#c3c6d7] overflow-hidden bg-[#f2f3ff]';
                const thumb = document.createElement('div');
                thumb.className = 'aspect-square bg-[#131b2e] flex items-center justify-center overflow-hidden';
                if (isVideo) {
                    const video = document.createElement('video');
                    video.src = url;
                    video.className = 'max-w-full max-h-full object-contain';
                    video.muted = true;
                    video.playsInline = true;
                    thumb.appendChild(video);
                } else {
                    const img = document.createElement('img');
                    img.src = url;
                    img.className = 'max-w-full max-h-full object-contain';
                    img.alt = file.name;
                    thumb.appendChild(img);
                }
                const meta = document.createElement('div');
                meta.className = 'p-2';
                const nameEl = document.createElement('p');
                nameEl.className = 'text-[10px] font-semibold text-[#131b2e] truncate';
                nameEl.textContent = file.name;
                nameEl.title = file.name;
                const sizeEl = document.createElement('p');
                sizeEl.className = 'text-[9px] text-[#737686]';
                sizeEl.textContent = sizeKb;
                meta.appendChild(nameEl);
                meta.appendChild(sizeEl);
                item.appendChild(thumb);
                item.appendChild(meta);
                grid.appendChild(item);
            });

            empty.classList.add('hidden');
            preview.classList.remove('hidden');
            updateUploadSubmitLabel(files.length);
        }

        function mediaToast(message, type = 'warning') {
            window.PP?.toast(message, { type, title: type === 'error' ? 'Error' : 'Notice' });
        }

        function handleFileSelection(fileList, append = false) {
            const incoming = Array.from(fileList || []);
            if (!incoming.length) {
                if (!append) clearFilePreview();
                return;
            }

            const mediaType = document.getElementById('uploadMediaType')?.value || '';
            const accepted = [];
            const rejected = [];

            for (const file of incoming) {
                const error = validateUploadFile(file, mediaType);
                if (error) rejected.push(error);
                else accepted.push(file);
            }

            if (rejected.length) {
                mediaToast(rejected[0], 'error');
            }

            if (!accepted.length) return;

            const merged = append ? mergeFiles(filesFromInput(), accepted) : accepted.slice(0, MAX_UPLOAD_FILES);

            if (append && filesFromInput().length + accepted.length > MAX_UPLOAD_FILES) {
                mediaToast(`You can upload up to ${MAX_UPLOAD_FILES} files at once.`);
            }

            setFilesOnInput(merged);
        }

        (function () {
            const optionsUrl = @json(route('media.options'));
            const channels = @json(array_values($channels));

            let uploadStep = 1;
            let selectedChannel = @json(old('channel'));
            let selectedMediaType = @json(old('media_type'));
            let selectedPreset = @json(old('preset_key'));
            let availablePresets = [];
            let skipSizeStep = false;

            const channelInput = document.getElementById('uploadChannel');
            const typeInput = document.getElementById('uploadMediaType');
            const presetInput = document.getElementById('uploadPresetKey');
            const fileInput = document.getElementById('uploadFiles');

            function channelName(key) {
                return channels.find(c => c.key === key)?.name || key;
            }

            function setUploadStep(step) {
                uploadStep = step;
                document.querySelectorAll('.upload-step').forEach(el => el.classList.add('hidden'));
                document.getElementById('upload-step-' + step)?.classList.remove('hidden');

                document.querySelectorAll('.upload-pill').forEach(pill => {
                    const s = parseInt(pill.dataset.step, 10);
                    pill.classList.toggle('active', s === step);
                });

                document.getElementById('uploadPrevBtn').classList.toggle('invisible', step === 1);
                document.getElementById('uploadNextBtn').classList.toggle('hidden', step === 4);
                document.getElementById('uploadSubmitBtn').classList.toggle('hidden', step !== 4);
            }

            function updateSummary() {
                document.getElementById('summaryChannel').textContent = selectedChannel ? channelName(selectedChannel) : '—';
                document.getElementById('summaryType').textContent = selectedMediaType ? selectedMediaType.charAt(0).toUpperCase() + selectedMediaType.slice(1) : '—';
                const preset = availablePresets.find(p => p.key === selectedPreset);
                document.getElementById('summarySize').textContent = preset ? preset.name : (skipSizeStep ? 'Any size' : '—');
                document.getElementById('selectedChannelLabel').textContent = selectedChannel ? channelName(selectedChannel) : '—';
                fileInput.accept = selectedMediaType === 'video' ? 'video/mp4,video/*' : 'image/jpeg,image/png,image/webp,image/jpg';
                document.getElementById('uploadAcceptHint').textContent = selectedMediaType === 'video'
                    ? `MP4 video — max ${MAX_UPLOAD_LABEL} each · up to ${MAX_UPLOAD_FILES} files`
                    : `JPEG, PNG, WebP — max ${MAX_UPLOAD_LABEL} each · up to ${MAX_UPLOAD_FILES} files`;
            }

            async function fetchJson(url) {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                return res.json();
            }

            async function loadMediaTypes() {
                const data = await fetchJson(optionsUrl + '?channel=' + selectedChannel);
                const container = document.getElementById('mediaTypeOptions');
                container.innerHTML = '';

                (data.media_types || []).forEach(type => {
                    const col = document.createElement('div');
                    col.innerHTML = `
                        <button type="button" class="select-card type-card" data-type="${type.key}">
                            <span class="material-symbols-outlined text-3xl text-[#004ac6] mb-2 block">${type.icon}</span>
                            <span class="font-semibold text-[#131b2e] block">${type.label}</span>
                            <span class="text-xs text-[#737686]">${type.has_sizes ? 'Size options available' : 'No fixed size required'}</span>
                        </button>`;
                    container.appendChild(col);
                });

                container.querySelectorAll('.type-card').forEach(btn => {
                    btn.addEventListener('click', () => selectMediaType(btn.dataset.type));
                });
            }

            async function loadPresets() {
                const data = await fetchJson(optionsUrl + '?channel=' + selectedChannel + '&media_type=' + selectedMediaType);
                availablePresets = data.presets || [];
                skipSizeStep = availablePresets.length === 0;

                const container = document.getElementById('presetOptions');
                const notice = document.getElementById('noPresetNotice');
                container.innerHTML = '';

                if (skipSizeStep) {
                    notice.classList.remove('hidden');
                    selectedPreset = '';
                    presetInput.value = '';
                    updateSummary();
                    setUploadStep(4);
                    return;
                }

                notice.classList.add('hidden');
                availablePresets.forEach(preset => {
                    const col = document.createElement('div');
                    col.innerHTML = `
                        <button type="button" class="select-card preset-card" data-preset="${preset.key}">
                            <span class="font-semibold text-[#131b2e] block">${preset.name}</span>
                            <span class="text-xs text-[#737686]">${preset.aspect_ratio} • ${preset.width}×${preset.height}px</span>
                        </button>`;
                    container.appendChild(col);
                });

                setUploadStep(3);

                container.querySelectorAll('.preset-card').forEach(btn => {
                    btn.addEventListener('click', () => {
                        selectPreset(btn.dataset.preset);
                        updateSummary();
                        setUploadStep(4);
                    });
                });
            }

            function selectChannel(key) {
                selectedChannel = key;
                channelInput.value = key;
                selectedMediaType = '';
                selectedPreset = '';
                typeInput.value = '';
                presetInput.value = '';
                document.querySelectorAll('.channel-card').forEach(c => c.classList.toggle('active', c.dataset.channel === key));
                loadMediaTypes().then(() => setUploadStep(2));
            }

            function selectMediaType(type) {
                selectedMediaType = type;
                typeInput.value = type;
                selectedPreset = '';
                presetInput.value = '';
                document.querySelectorAll('.type-card').forEach(c => c.classList.toggle('active', c.dataset.type === type));
                loadPresets();
            }

            function selectPreset(key) {
                selectedPreset = key;
                presetInput.value = key;
                document.querySelectorAll('.preset-card').forEach(c => c.classList.toggle('active', c.dataset.preset === key));
                updateSummary();
            }

            document.querySelectorAll('.channel-card').forEach(btn => {
                btn.addEventListener('click', () => selectChannel(btn.dataset.channel));
            });

            document.getElementById('uploadPrevBtn').addEventListener('click', () => {
                if (uploadStep === 4) setUploadStep(skipSizeStep ? 2 : 3);
                else if (uploadStep === 3) setUploadStep(2);
                else if (uploadStep === 2) setUploadStep(1);
            });

            document.getElementById('uploadNextBtn').addEventListener('click', () => {
                if (uploadStep === 3) {
                    if (!skipSizeStep && !selectedPreset) {
                        mediaToast('Please select a size format.');
                        return;
                    }
                    updateSummary();
                    setUploadStep(4);
                }
            });

            if (selectedChannel) {
                document.querySelectorAll('.channel-card').forEach(c => c.classList.toggle('active', c.dataset.channel === selectedChannel));
                loadMediaTypes();
            }

            document.getElementById('uploadDropzone').addEventListener('click', (e) => {
                if (e.target.closest('#uploadChangeFile') || e.target.closest('#uploadClearFiles')) return;
                if (filesFromInput().length >= MAX_UPLOAD_FILES) return;
                addingMoreFiles = filesFromInput().length > 0;
                fileInput.click();
            });

            document.getElementById('uploadChangeFile').addEventListener('click', (e) => {
                e.stopPropagation();
                if (filesFromInput().length >= MAX_UPLOAD_FILES) {
                    mediaToast(`You can upload up to ${MAX_UPLOAD_FILES} files at once.`);
                    return;
                }
                addingMoreFiles = true;
                fileInput.click();
            });

            document.getElementById('uploadClearFiles').addEventListener('click', (e) => {
                e.stopPropagation();
                clearFilePreview();
            });

            fileInput.addEventListener('change', () => {
                handleFileSelection(fileInput.files, addingMoreFiles);
                addingMoreFiles = false;
            });

            document.getElementById('uploadForm').addEventListener('submit', (e) => {
                const files = filesFromInput();
                const count = files.length;
                if (count === 0) {
                    e.preventDefault();
                    mediaToast('Please select at least one file.', 'error');
                    return;
                }
                if (count > MAX_UPLOAD_FILES) {
                    e.preventDefault();
                    mediaToast(`You can upload up to ${MAX_UPLOAD_FILES} files at once.`, 'error');
                    return;
                }
                for (const file of files) {
                    const error = validateUploadFile(file, selectedMediaType);
                    if (error) {
                        e.preventDefault();
                        mediaToast(error, 'error');
                        return;
                    }
                }
                const btn = document.getElementById('uploadSubmitBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.dataset.originalHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span> Uploading…';
                }
            });

            function resetUploadSubmitButton() {
                const btn = document.getElementById('uploadSubmitBtn');
                if (!btn) return;
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml
                    || '<span class="material-symbols-outlined text-[18px]">check</span><span id="uploadSubmitLabel">Save Media</span>';
                updateUploadSubmitLabel(filesFromInput().length);
            }

            document.getElementById('uploadDropzone').addEventListener('dragover', (e) => {
                e.preventDefault();
                e.currentTarget.classList.add('is-dragover');
            });

            document.getElementById('uploadDropzone').addEventListener('dragleave', (e) => {
                e.currentTarget.classList.remove('is-dragover');
            });

            document.getElementById('uploadDropzone').addEventListener('drop', (e) => {
                e.preventDefault();
                e.currentTarget.classList.remove('is-dragover');
                const dropped = Array.from(e.dataTransfer.files || []);
                if (!dropped.length) return;
                handleFileSelection(dropped, filesFromInput().length > 0);
            });

            @if($errors->any())
                @if(old('channel') && old('media_type'))
                    setUploadStep(4);
                    updateSummary();
                @endif
                resetUploadSubmitButton();
                mediaToast(@json($errors->first()), 'error');
            @endif
        })();
    </script>
    @endpush
</x-app-layout>
