<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Services\Media\MediaConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaAssetController extends Controller
{
    public function __construct(
        protected MediaConfigService $mediaConfig,
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

        $perPage = (int) $request->query('per_page', 24);
        $perPage = in_array($perPage, [12, 24, 48, 96], true) ? $perPage : 24;

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
        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|mimes:jpeg,png,jpg,webp,mp4|max:10240',
            'channel' => 'required|string|in:facebook,instagram,pinterest',
            'media_type' => 'required|string|in:image,video',
            'preset_key' => 'nullable|string',
            'alt_text' => 'nullable|string|max:250',
        ]);

        $channel = $request->channel;
        $mediaType = $request->media_type;
        $presetKey = $request->preset_key ?: null;

        $availablePresets = collect(
            $this->mediaConfig->presetsForChannelAndType($channel, $mediaType)
        )->pluck('key')->all();

        if ($availablePresets && !in_array($presetKey, $availablePresets, true)) {
            return back()->withErrors(['preset_key' => 'Please select a valid size for this channel and media type.']);
        }

        if (!$availablePresets) {
            $presetKey = null;
        }

        $preset = $presetKey ? $this->mediaConfig->preset($presetKey) : null;
        $uploaded = 0;

        foreach ($request->file('files') as $file) {
            $mime = $file->getMimeType();
            $isImage = str_starts_with($mime, 'image/');
            $isVideo = str_starts_with($mime, 'video/');

            if ($mediaType === 'image' && !$isImage) {
                return back()->withErrors(['files' => 'Selected media type is Image, but an uploaded file is not an image.']);
            }

            if ($mediaType === 'video' && !$isVideo) {
                return back()->withErrors(['files' => 'Selected media type is Video, but an uploaded file is not a video.']);
            }

            $path = $file->store('media', 'public');

            $width = $preset['width'] ?? null;
            $height = $preset['height'] ?? null;

            if (!$width || !$height) {
                $fullPath = Storage::disk('public')->path($path);
                if ($isImage && function_exists('getimagesize')) {
                    $sizes = getimagesize($fullPath);
                    if ($sizes) {
                        $width = $sizes[0];
                        $height = $sizes[1];
                    }
                }
            }

            MediaAsset::create([
                'user_id' => auth()->id(),
                'file_path' => $path,
                'disk' => 'public',
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'media_type' => $mediaType,
                'file_size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'preset_key' => $presetKey,
                'alt_text' => $request->alt_text,
                'channels' => [$channel],
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
