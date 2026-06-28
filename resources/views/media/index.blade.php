<x-app-layout>
    <x-pp-page-header title="Media Library" description="Manage and organize your visual assets across all social channels.">
        <x-slot name="actions">
            <button type="button" onclick="openUploadModal()" class="pp-btn-primary">
                <span class="material-symbols-outlined text-[18px]">upload_file</span>
                Add Media
            </button>
        </x-slot>
    </x-pp-page-header>

    {{-- Upload modal (wizard only — no gallery inside) --}}
    <div id="uploadModalBackdrop" class="fixed inset-0 bg-black/40 backdrop-blur-[2px] z-[60] hidden" onclick="closeUploadModal()"></div>
    <div id="uploadModal" class="fixed inset-0 z-[70] hidden flex items-center justify-center p-4 pointer-events-none">
        <div class="wizard-card w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl pointer-events-auto" onclick="event.stopPropagation()">
            <div class="pp-card-header flex items-center justify-between flex-wrap gap-3 shrink-0">
                <div>
                    <h3 class="font-display font-semibold text-[#131b2e] flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#004ac6]">cloud_upload</span>
                        Upload New Media
                    </h3>
                    <p class="text-xs text-[#737686] mt-1">Configure channel, type, size, then upload up to 10 files at once.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex gap-2 flex-wrap" id="uploadStepPills">
                        <span class="wizard-pill upload-pill active" data-step="1">1. Channel</span>
                        <span class="wizard-pill upload-pill" data-step="2">2. Type</span>
                        <span class="wizard-pill upload-pill" data-step="3">3. Size</span>
                        <span class="wizard-pill upload-pill" data-step="4">4. Upload</span>
                    </div>
                    <button type="button" onclick="closeUploadModal()" class="p-2 hover:bg-[#f2f3ff] rounded-full text-[#737686]">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>

            <form action="{{ route('media.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm" class="flex flex-col min-h-0 flex-1">
                @csrf
                <input type="hidden" name="channel" id="uploadChannel" value="{{ old('channel') }}">
                <input type="hidden" name="media_type" id="uploadMediaType" value="{{ old('media_type') }}">
                <input type="hidden" name="preset_key" id="uploadPresetKey" value="{{ old('preset_key') }}">

                <div class="p-6 overflow-y-auto flex-1">
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
                                        <p class="text-xs text-[#737686]" id="uploadAcceptHint">JPEG, PNG, WebP, MP4 — max 10MB each · up to 10 files</p>
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
                <div class="flex justify-between items-center border-t border-[#c3c6d7] p-4 px-6 shrink-0">
                    <button type="button" class="pp-btn-secondary invisible" id="uploadPrevBtn">Back</button>
                    <div class="flex gap-2">
                        <button type="button" class="pp-btn-secondary" onclick="closeUploadModal()">Cancel</button>
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

    <div class="pp-card p-4 mb-6 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2 border-r border-[#c3c6d7] pr-4">
            <span class="material-symbols-outlined text-[#737686]">filter_list</span>
            <span class="text-xs font-semibold uppercase tracking-wider text-[#737686]">Filters</span>
        </div>
        <a href="{{ route('media.index') }}" class="pp-status-chip {{ !request('channel') && !request('type') ? 'bg-[#131b2e] text-white' : 'bg-[#f2f3ff] text-[#434655]' }}">All</a>
        @foreach($channels as $channel)
            <a href="{{ route('media.index', ['channel' => $channel['key']]) }}"
               class="pp-status-chip {{ request('channel') === $channel['key'] ? 'bg-[#004ac6]/10 text-[#004ac6]' : 'bg-[#f2f3ff] text-[#434655]' }}">
                {{ $channel['name'] }}
            </a>
        @endforeach
        <span class="text-[#c3c6d7]">|</span>
        <a href="{{ route('media.index', ['type' => 'image']) }}" class="pp-status-chip {{ request('type') === 'image' ? 'bg-[#131b2e] text-white' : 'bg-[#f2f3ff] text-[#434655]' }}">Images</a>
        <a href="{{ route('media.index', ['type' => 'video']) }}" class="pp-status-chip {{ request('type') === 'video' ? 'bg-[#131b2e] text-white' : 'bg-[#f2f3ff] text-[#434655]' }}">Videos</a>
    </div>

    @if($assets->isEmpty())
        <div class="pp-card p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-[#c3c6d7] mb-4 block">perm_media</span>
            <h3 class="font-display text-lg font-semibold text-[#131b2e] mb-2">No media yet</h3>
            <p class="text-sm text-[#737686] mb-6">Upload your first asset with channel configuration.</p>
            <button type="button" onclick="openUploadModal()" class="pp-btn-primary">Add Media</button>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            @foreach($assets as $asset)
                @php
                    $isVideo = str_starts_with($asset->mime_type ?? '', 'video/');
                    $uploader = $asset->user?->name ?? 'Unknown';
                @endphp
                <div class="pp-card overflow-hidden media-card-hover group transition-all duration-300 hover:border-[#004ac6] flex flex-col">
                    <div class="aspect-square relative overflow-hidden bg-[#0a0a0a]">
                        @if($isVideo)
                            <video src="{{ $asset->url }}" class="w-full h-full object-cover" muted playsinline preload="metadata"></video>
                            <span class="absolute top-2 left-2 pp-status-chip bg-black/70 text-white text-[8px] flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[10px]">play_circle</span> VIDEO
                            </span>
                        @else
                            <img src="{{ $asset->url }}"
                                 alt="{{ $asset->alt_text ?: $asset->original_name }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 cursor-zoom-in"
                                 loading="lazy"
                                 data-pp-lightbox="{{ $asset->url }}"
                                 data-pp-lightbox-alt="{{ $asset->alt_text ?: $asset->original_name }}"
                                 data-pp-lightbox-caption="{{ $asset->original_name }}"
                                 title="View full image">
                        @endif
                        @if($asset->width)
                            <span class="absolute bottom-2 right-2 pp-status-chip bg-black/70 text-white text-[8px]">{{ $asset->width }}×{{ $asset->height }}</span>
                        @endif
                        <div class="overlay-actions absolute inset-0 bg-[#131b2e]/50 flex items-center justify-center gap-2">
                            <button type="button"
                                    class="p-2 bg-white rounded-full text-[#131b2e] hover:bg-[#004ac6] hover:text-white transition-colors shadow"
                                    title="View full image"
                                    data-pp-lightbox="{{ $asset->url }}"
                                    data-pp-lightbox-alt="{{ $asset->alt_text ?: $asset->original_name }}"
                                    data-pp-lightbox-caption="{{ $asset->original_name }}">
                                <span class="material-symbols-outlined text-[18px]">fullscreen</span>
                            </button>
                            <a href="{{ route('media.index', array_merge(request()->query(), ['selected_asset' => $asset->id])) }}" class="p-2 bg-white rounded-full text-[#131b2e] hover:bg-[#004ac6] hover:text-white transition-colors shadow" title="Edit">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </a>
                            <form action="{{ route('media.delete', $asset->id) }}" method="POST"
                                data-pp-confirm="This media file will be removed from your library."
                                data-pp-confirm-title="Delete media?"
                                data-pp-confirm-variant="danger"
                                data-pp-confirm-button="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 bg-white rounded-full text-[#131b2e] hover:bg-[#ba1a1a] hover:text-white transition-colors shadow" title="Delete">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="p-3 flex-1 flex flex-col min-h-0">
                        <h3 class="text-[11px] font-semibold text-[#131b2e] truncate mb-1" title="{{ $asset->original_name }}">{{ $asset->original_name }}</h3>
                        <p class="text-[10px] text-[#737686] truncate mb-2 flex items-center gap-1" title="Uploaded by {{ $uploader }}">
                            <span class="material-symbols-outlined text-[12px]">person</span>
                            {{ $uploader }}
                        </p>
                        <div class="flex flex-wrap gap-1 mb-1.5">
                            @if($asset->channel_label)
                                <span class="pp-status-chip bg-[#004ac6]/10 text-[#004ac6] text-[8px]">{{ $asset->channel_label }}</span>
                            @endif
                            @if($asset->media_type)
                                <span class="pp-status-chip bg-[#f2f3ff] text-[#434655] text-[8px]">{{ strtoupper($asset->media_type) }}</span>
                            @endif
                        </div>
                        <p class="text-[9px] text-[#737686] mt-auto">{{ $asset->formatted_size }} · {{ $asset->created_at->format('M j, Y') }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <x-pp-pagination :paginator="$assets" :per-page="$perPage" :per-page-options="[12, 24, 48, 96]" />
    @endif

    @if($selectedAsset ?? null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-[2px]">
            <div class="pp-card w-full max-w-md shadow-2xl overflow-hidden">
                <div class="pp-card-header flex justify-between items-center">
                    <h3 class="font-display font-semibold text-sm">Edit Media</h3>
                    <a href="{{ route('media.index', request()->except('selected_asset')) }}" class="text-[#737686] hover:text-[#131b2e]"><span class="material-symbols-outlined">close</span></a>
                </div>
                <div class="p-4 border-b border-[#c3c6d7] bg-[#faf8ff]">
                    <div class="aspect-video rounded-lg overflow-hidden bg-[#0a0a0a] mb-3">
                        @if(str_starts_with($selectedAsset->mime_type, 'video/'))
                            <video src="{{ $selectedAsset->url }}" class="w-full h-full object-contain" controls playsinline></video>
                        @else
                            <img src="{{ $selectedAsset->url }}" alt="" class="w-full h-full object-contain cursor-zoom-in"
                                 data-pp-lightbox="{{ $selectedAsset->url }}"
                                 data-pp-lightbox-caption="{{ $selectedAsset->original_name }}"
                                 title="View full image">
                        @endif
                    </div>
                    <p class="text-xs font-semibold text-[#131b2e] truncate">{{ $selectedAsset->original_name }}</p>
                    <p class="text-[10px] text-[#737686] mt-0.5">Uploaded by {{ $selectedAsset->user?->name ?? 'Unknown' }}</p>
                </div>
                <form action="{{ route('media.metadata', $selectedAsset->id) }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <div class="flex gap-2 flex-wrap">
                        @if($selectedAsset->channel_label)<span class="pp-status-chip bg-[#004ac6]/10 text-[#004ac6] text-[10px]">{{ $selectedAsset->channel_label }}</span>@endif
                        @if($selectedAsset->media_type)<span class="pp-status-chip bg-[#f2f3ff] text-[#434655] text-[10px]">{{ strtoupper($selectedAsset->media_type) }}</span>@endif
                        <span class="pp-status-chip bg-[#f2f3ff] text-[#434655] text-[10px]">{{ $selectedAsset->formatted_size }}</span>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Alt Text</label>
                        <textarea name="alt_text" rows="2" class="pp-input text-sm">{{ $selectedAsset->alt_text }}</textarea>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-[#737686] uppercase block mb-1">Tags</label>
                        <input type="text" name="tags" class="pp-input text-sm" value="{{ $selectedAsset->tags ? implode(', ', $selectedAsset->tags) : '' }}" placeholder="summer, sale">
                    </div>
                    <div class="flex gap-2 justify-end pt-2">
                        <a href="{{ route('media.index', request()->except('selected_asset')) }}" class="pp-btn-secondary">Cancel</a>
                        <button type="submit" class="pp-btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
        const MAX_UPLOAD_FILES = 10;
        let uploadPreviewObjectUrls = [];
        let addingMoreFiles = false;

        function openUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
            document.getElementById('uploadModalBackdrop').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('uploadModalBackdrop').classList.add('hidden');
            document.body.style.overflow = '';
            clearFilePreview();
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
                const sizeKb = (file.size / 1024).toFixed(1);
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
                sizeEl.textContent = sizeKb + ' KB';
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

            const merged = append ? mergeFiles(filesFromInput(), incoming) : incoming.slice(0, MAX_UPLOAD_FILES);

            if (append && filesFromInput().length + incoming.length > MAX_UPLOAD_FILES) {
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
                fileInput.accept = selectedMediaType === 'video' ? 'video/*' : 'image/*';
                document.getElementById('uploadAcceptHint').textContent = selectedMediaType === 'video'
                    ? 'MP4 video — max 10MB each · up to 10 files'
                    : 'JPEG, PNG, WebP — max 10MB each · up to 10 files';
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
                    col.className = 'col-12 col-md-6';
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
                    col.className = 'col-12 col-md-6';
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
                const count = filesFromInput().length;
                if (count === 0) {
                    e.preventDefault();
                    mediaToast('Please select at least one file.');
                    return;
                }
                if (count > MAX_UPLOAD_FILES) {
                    e.preventDefault();
                    mediaToast(`You can upload up to ${MAX_UPLOAD_FILES} files at once.`);
                }
            });

            document.getElementById('uploadDropzone').addEventListener('dragover', (e) => {
                e.preventDefault();
                e.currentTarget.classList.add('border-[#004ac6]');
            });

            document.getElementById('uploadDropzone').addEventListener('dragleave', (e) => {
                e.currentTarget.classList.remove('border-[#004ac6]');
            });

            document.getElementById('uploadDropzone').addEventListener('drop', (e) => {
                e.preventDefault();
                e.currentTarget.classList.remove('border-[#004ac6]');
                const dropped = Array.from(e.dataTransfer.files || []);
                if (!dropped.length) return;
                handleFileSelection(dropped, filesFromInput().length > 0);
            });

            @if($errors->any())
                openUploadModal();
            @endif
        })();
    </script>
    @endpush
</x-app-layout>
