@props([
    'media' => null,
    'channel' => null,
    'maxHeight' => '240px',
    'fallbackUrl' => null,
    'fallbackMime' => null,
    'fallbackDimensions' => null,
    'lightbox' => true,
])

@php
    $url = $media?->url ?? $fallbackUrl;
    $mime = $media?->mime_type ?? $fallbackMime;
    $dimensions = $media
        ? $media->displayDimensions($channel)
        : ($fallbackDimensions ?? ['width' => 4, 'height' => 3]);
    $isVideo = $mime && str_starts_with($mime, 'video/');
    $altText = $media?->alt_text ?: $media?->original_name ?: 'Post media';
@endphp

<div {{ $attributes->merge(['class' => 'pp-media-frame' . ($lightbox && $url && ! $isVideo ? ' pp-media-frame--lightbox' : '')]) }}
     style="aspect-ratio: {{ $dimensions['width'] }} / {{ $dimensions['height'] }}; max-height: {{ $maxHeight }};">
    @if($url)
        @if($isVideo)
            <video src="{{ $url }}" class="pp-media-frame__asset" muted playsinline preload="metadata"></video>
        @else
            <img src="{{ $url }}"
                 alt="{{ $altText }}"
                 class="pp-media-frame__asset"
                 loading="lazy"
                 @if($lightbox)
                     data-pp-lightbox="{{ $url }}"
                     data-pp-lightbox-alt="{{ $altText }}"
                     title="View full image"
                 @endif>
        @endif
    @else
        <span class="material-symbols-outlined text-2xl text-[#737686]">image_not_supported</span>
    @endif
</div>
