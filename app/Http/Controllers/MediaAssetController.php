<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Services\Media\ImageMetadataService;
use App\Services\Media\MediaConfigService;
use App\Support\MediaUpload;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaAssetController extends Controller
{
    public function __construct(
        protected MediaConfigService $mediaConfig,
        protected ImageMetadataService $imageMetadata,
    ) {}

    public function index(Request $request)
    {
        $query = MediaAsset::orderBy('created_at', 'desc');

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('channel')) {
            $query->where('channels', 'like', '%"' . $request->channel . '"%');
        }

        if ($request->filled('type')) {
            $query->where('media_type', $request->type);
        }

        if ($request->filled('preset')) {
            $query->where('preset_key', $request->preset);
        }

        $perPage = PerPage::resolve($request, 10);

        $assets = $query->with('user')->paginate($perPage)->withQueryString();

        $selectedAsset = null;
        if ($request->filled('selected_asset')) {
            $selectedQuery = MediaAsset::with('user');
            if (! auth()->user()->isAdmin()) {
                $selectedQuery->where('user_id', auth()->id());
            }
            $selectedAsset = $selectedQuery->find($request->selected_asset);
        }

        return view('media.index', [
            'assets' => $assets,
            'perPage' => $perPage,
            'selectedAsset' => $selectedAsset,
            'channels' => $this->mediaConfig->channels(),
            'presets' => config('social-platforms.presets'),
        ]);
    }

    public function create()
    {
        return view('media.create', [
            'channels' => $this->mediaConfig->channels(),
        ]);
    }

    public function options(Request $request)
    {
        $request->validate([
            'channel' => 'nullable|string|in:facebook,instagram,pinterest',
            'media_type' => 'nullable|string|in:image,video',
        ]);

        if ($request->filled('channel') && $request->filled('media_type')) {
            return response()->json([
                'presets' => $this->mediaConfig->presetsForChannelAndType(
                    $request->channel,
                    $request->media_type
                ),
            ]);
        }

        if ($request->filled('channel')) {
            return response()->json([
                'media_types' => $this->mediaConfig->mediaTypesForChannel($request->channel),
            ]);
        }

        return response()->json([
            'channels' => array_values($this->mediaConfig->channels()),
        ]);
    }

    public function upload(Request $request)
    {
        if (MediaUpload::postTooLarge()) {
            return back()->withErrors(['files' => MediaUpload::postTooLargeMessage()]);
        }

        try {
            $request->validate(
                array_merge(MediaUpload::libraryFilesRules(), [
                    'channel' => 'required|string|in:facebook,instagram,pinterest',
                    'media_type' => 'required|string|in:image,video',
                    'preset_key' => 'nullable|string',
                    'alt_text' => 'nullable|string|max:250',
                ]),
                MediaUpload::validationMessages('files')
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                MediaUpload::friendlyValidationErrors($e->errors(), $request->file('files'))
            );
        }

        MediaUpload::assertValid($request->file('files'), 'files');

        $channel = $request->channel;
        $mediaType = $request->media_type;
        $presetKey = $request->preset_key ?: null;

        $availablePresets = collect(
            $this->mediaConfig->presetsForChannelAndType($channel, $mediaType)
        )->pluck('key')->all();

        if ($availablePresets && ! in_array($presetKey, $availablePresets, true)) {
            return back()->withErrors(['preset_key' => 'Please select a valid size for this channel and media type.']);
        }

        if (! $availablePresets) {
            $presetKey = null;
        }

        $preset = $presetKey ? $this->mediaConfig->preset($presetKey) : null;
        $uploaded = 0;

        foreach ($request->file('files') as $index => $file) {
            $mime = $file->getMimeType() ?: 'application/octet-stream';
            $isImage = str_starts_with($mime, 'image/');
            $isVideo = str_starts_with($mime, 'video/');

            if ($mediaType === 'image' && ! $isImage) {
                return back()->withErrors(['files' => 'Selected media type is Image, but file '.($index + 1).' is not an image.']);
            }

            if ($mediaType === 'video' && ! $isVideo) {
                return back()->withErrors(['files' => 'Selected media type is Video, but file '.($index + 1).' is not a video.']);
            }

            $path = $file->store('media', 'public');

            $width = $preset['width'] ?? null;
            $height = $preset['height'] ?? null;
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $tags = [$channel];
            $assetMetadata = [
                'source' => 'manual-upload',
            ];

            if ($isImage) {
                $fullPath = Storage::disk('public')->path($path);
                if ((! $width || ! $height) && function_exists('getimagesize')) {
                    $sizes = @getimagesize($fullPath);
                    if ($sizes) {
                        $width = $sizes[0];
                        $height = $sizes[1];
                    }
                }

                try {
                    // Same Canva-style embedded metadata rewrite as automation exports.
                    $finalized = $this->imageMetadata->finalizeStoredImage($path);
                    $path = $finalized['file_path'];
                    $originalName = $finalized['original_name'];
                    $fileSize = $finalized['file_size'];
                    $tags = array_values(array_unique(array_merge($tags, ['canva'])));
                    $assetMetadata['creator_tool'] = 'Canva';
                } catch (\Throwable $e) {
                    Log::warning('Media metadata rewrite skipped after upload', [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            MediaAsset::create([
                'user_id' => auth()->id(),
                'file_path' => $path,
                'disk' => 'public',
                'original_name' => $originalName,
                'mime_type' => $mime,
                'media_type' => $mediaType,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'preset_key' => $presetKey,
                'alt_text' => $request->alt_text,
                'tags' => $tags,
                'channels' => [$channel],
                'metadata' => $assetMetadata,
            ]);

            $uploaded++;
        }

        $message = $uploaded === 1
            ? 'Media uploaded successfully with your channel configuration!'
            : "{$uploaded} media files uploaded successfully with your channel configuration!";

        return redirect()->route('media.index')
            ->with('status', $message);
    }

    public function saveMetadata(Request $request, $id)
    {
        $query = MediaAsset::query();
        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }
        $asset = $query->findOrFail($id);

        $request->validate([
            'alt_text' => 'nullable|string|max:250',
            'tags' => 'nullable|string|max:250',
        ]);

        $tags = array_filter(array_map('trim', explode(',', $request->tags ?? '')));

        $asset->update([
            'alt_text' => $request->alt_text,
            'tags' => array_values($tags),
        ]);

        return redirect()->route('media.index')
            ->with('status', 'Media metadata updated successfully.');
    }

    public function delete($id)
    {
        $query = MediaAsset::query();
        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }
        $asset = $query->findOrFail($id);

        if (Storage::disk($asset->disk)->exists($asset->file_path)) {
            Storage::disk($asset->disk)->delete($asset->file_path);
        }

        $asset->delete();

        return redirect()->route('media.index')
            ->with('status', 'Media removed from library. Posts keep their own saved copies.');
    }
}
