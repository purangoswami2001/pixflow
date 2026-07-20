<x-app-layout>
    <x-pp-page-header title="Media Library" description="Manage and organize your visual assets across all social channels.">
        <x-slot name="actions">
            <a href="{{ route('media.create') }}" class="pp-btn-primary">
                <span class="material-symbols-outlined text-[18px]">upload_file</span>
                <span class="pp-action-label">Add Media</span>
            </a>
        </x-slot>
    </x-pp-page-header>

    <div class="pp-filter-bar pp-card p-3 sm:p-4 mb-5 sm:mb-6 flex items-center gap-2 sm:gap-3 overflow-x-auto whitespace-nowrap sm:flex-wrap sm:whitespace-normal">
        <div class="flex items-center gap-2 border-r border-[var(--pp-border-soft)] pr-3 shrink-0">
            <span class="material-symbols-outlined text-[var(--pp-text-muted)] text-[20px]">filter_list</span>
            <span class="text-xs font-semibold uppercase tracking-wider text-[var(--pp-text-muted)] hidden sm:inline">Filters</span>
        </div>
        <a href="{{ route('media.index') }}" class="pp-status-chip shrink-0 {{ !request('channel') && !request('type') ? 'bg-[var(--pp-text)] text-[var(--pp-surface)]' : 'bg-[var(--pp-surface-soft)] text-[var(--pp-text-secondary)]' }}">All</a>
        @foreach($channels as $channel)
            <a href="{{ route('media.index', ['channel' => $channel['key']]) }}"
               class="pp-status-chip shrink-0 {{ request('channel') === $channel['key'] ? 'bg-[var(--pp-primary-muted)] text-[var(--pp-primary)]' : 'bg-[var(--pp-surface-soft)] text-[var(--pp-text-secondary)]' }}">
                {{ $channel['name'] }}
            </a>
        @endforeach
        <span class="text-[var(--pp-border)] shrink-0">|</span>
        <a href="{{ route('media.index', ['type' => 'image']) }}" class="pp-status-chip shrink-0 {{ request('type') === 'image' ? 'bg-[var(--pp-text)] text-[var(--pp-surface)]' : 'bg-[var(--pp-surface-soft)] text-[var(--pp-text-secondary)]' }}">Images</a>
        <a href="{{ route('media.index', ['type' => 'video']) }}" class="pp-status-chip shrink-0 {{ request('type') === 'video' ? 'bg-[var(--pp-text)] text-[var(--pp-surface)]' : 'bg-[var(--pp-surface-soft)] text-[var(--pp-text-secondary)]' }}">Videos</a>
    </div>

    @if($assets->total() === 0)
        <div class="pp-card p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-[#c3c6d7] mb-4 block">perm_media</span>
            <h3 class="font-display text-lg font-semibold text-[#131b2e] mb-2">No media yet</h3>
            <p class="text-sm text-[#737686] mb-6">Upload your first asset with channel configuration.</p>
            <a href="{{ route('media.create') }}" class="pp-btn-primary">Add Media</a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4" id="pp-media-list" data-pp-paginated-list>
            @foreach($assets as $asset)
                @php
                    $isVideo = ($asset->media_type === 'video')
                        || str_starts_with($asset->mime_type ?? '', 'video/');
                    $uploader = $asset->uploaderLabel();
                @endphp
                <div class="pp-card overflow-hidden media-card-hover group transition-all duration-300 hover:border-[#004ac6] flex flex-col">
                    <div class="aspect-square relative overflow-hidden bg-[#0a0a0a]">
                        @if($isVideo)
                            <video src="{{ $asset->url }}" class="w-full h-full object-contain" muted playsinline preload="metadata"></video>
                            <button type="button"
                                    class="pp-media-play-btn"
                                    title="Play video"
                                    data-pp-lightbox="{{ $asset->url }}"
                                    data-pp-lightbox-type="video"
                                    data-pp-lightbox-mime="{{ $asset->mime_type }}"
                                    data-pp-lightbox-caption="{{ $asset->original_name }}">
                                <span class="pp-media-play-btn__icon">
                                    <span class="material-symbols-outlined filled">play_arrow</span>
                                </span>
                            </button>
                            <span class="absolute top-2 left-2 z-[3] pp-status-chip bg-black/70 text-white text-[8px] flex items-center gap-0.5 pointer-events-none">
                                <span class="material-symbols-outlined text-[10px]">videocam</span> VIDEO
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
                            <span class="absolute bottom-2 right-2 z-[3] pp-status-chip bg-black/70 text-white text-[8px] pointer-events-none">{{ $asset->width }}Ã—{{ $asset->height }}</span>
                        @endif
                        <div class="overlay-actions absolute inset-0 z-[4] bg-[#131b2e]/50 flex items-center justify-center gap-2">
                            <button type="button"
                                    class="p-2 bg-white rounded-full text-[#131b2e] hover:bg-[#004ac6] hover:text-white transition-colors shadow"
                                    title="{{ $isVideo ? 'Play video' : 'View full image' }}"
                                    data-pp-lightbox="{{ $asset->url }}"
                                    @if($isVideo)
                                        data-pp-lightbox-type="video"
                                        data-pp-lightbox-mime="{{ $asset->mime_type }}"
                                    @endif
                                    data-pp-lightbox-alt="{{ $asset->alt_text ?: $asset->original_name }}"
                                    data-pp-lightbox-caption="{{ $asset->original_name }}">
                                <span class="material-symbols-outlined text-[18px]">{{ $isVideo ? 'play_circle' : 'fullscreen' }}</span>
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
                        <p class="text-[9px] text-[#737686] mt-auto">{{ $asset->formatted_size }} Â· {{ $asset->created_at->format('M j, Y') }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <x-pp-pagination :paginator="$assets" :per-page="$perPage" list-target="#pp-media-list" />
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
                        @if(($selectedAsset->media_type === 'video') || str_starts_with($selectedAsset->mime_type ?? '', 'video/'))
                            <video src="{{ $selectedAsset->url }}" class="w-full h-full object-contain" controls playsinline preload="metadata">
                                Your browser does not support this video.
                            </video>
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
</x-app-layout>
