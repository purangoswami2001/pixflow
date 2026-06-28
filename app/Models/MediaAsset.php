<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'file_path',
        'disk',
        'original_name',
        'mime_type',
        'media_type',
        'file_size',
        'width',
        'height',
        'preset_key',
        'duration',
        'alt_text',
        'tags',
        'metadata',
        'channels',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'channels' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
    ];

    public function getPresetLabelAttribute(): ?string
    {
        if (!$this->preset_key) {
            return null;
        }

        return config("social-platforms.presets.{$this->preset_key}.name");
    }

    public function getChannelLabelAttribute(): ?string
    {
        $channel = $this->channels[0] ?? null;
        if (!$channel) {
            return null;
        }

        return config("social-platforms.platforms.{$channel}.name", ucfirst($channel));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'public') {
            return asset('storage/' . ltrim($this->file_path, '/'));
        }

        return Storage::disk($this->disk)->url($this->file_path);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Width/height for UI framing — uses stored dimensions, preset, then channel fallback.
     */
    public function displayDimensions(?string $channel = null): array
    {
        if ($this->width && $this->height) {
            return ['width' => $this->width, 'height' => $this->height];
        }

        if ($this->preset_key) {
            $preset = config("social-platforms.presets.{$this->preset_key}");
            if ($preset) {
                return ['width' => $preset['width'], 'height' => $preset['height']];
            }
        }

        $channel = $channel ?? ($this->channels[0] ?? null);

        return match ($channel) {
            'facebook' => ['width' => 1200, 'height' => 630],
            'instagram' => ['width' => 1080, 'height' => 1080],
            'pinterest' => ['width' => 1000, 'height' => 1500],
            default => ['width' => 4, 'height' => 3],
        };
    }

    public function aspectRatioStyle(): string
    {
        $dimensions = $this->displayDimensions();

        return "aspect-ratio: {$dimensions['width']} / {$dimensions['height']}";
    }
}
