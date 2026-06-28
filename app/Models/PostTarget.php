<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\ProviderType;
use App\Enums\ScheduleMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PostTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'social_account_id',
        'social_destination_id',
        'provider',
        'connection_snapshot',
        'post_type',
        'media_asset_id',
        'platform_media_path',
        'media_snapshot',
        'platform_payload',
        'scheduled_at',
        'timezone',
        'schedule_mode',
        'queue_slot_id',
        'status',
        'provider_post_id',
        'provider_post_url',
        'error_message',
        'attempts_count',
        'published_at',
    ];

    protected $casts = [
        'provider' => ProviderType::class,
        'post_type' => PostType::class,
        'status' => PostStatus::class,
        'schedule_mode' => ScheduleMode::class,
        'platform_payload' => 'array',
        'media_snapshot' => 'array',
        'connection_snapshot' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'attempts_count' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function socialDestination(): BelongsTo
    {
        return $this->belongsTo(SocialDestination::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function queueSlot(): BelongsTo
    {
        return $this->belongsTo(QueueSlot::class);
    }

    public function scopeAutoQueued($query)
    {
        return $query->where(function ($q) {
            $q->where('schedule_mode', ScheduleMode::AUTO)
                ->orWhereNull('schedule_mode');
        });
    }

    public function scopeManualScheduled($query)
    {
        return $query->where('schedule_mode', ScheduleMode::MANUAL);
    }

    public function publishLogs(): HasMany
    {
        return $this->hasMany(PublishLog::class);
    }

    public function getPlatformMediaUrlAttribute(): ?string
    {
        if (!$this->platform_media_path) {
            return null;
        }

        return asset('storage/' . ltrim($this->platform_media_path, '/'));
    }

    public function resolvedMediaUrl(): ?string
    {
        if (!$this->platform_media_path) {
            return null;
        }

        return $this->platform_media_url;
    }

    public function resolvedMimeType(): ?string
    {
        if (!empty($this->media_snapshot['mime_type'])) {
            return $this->media_snapshot['mime_type'];
        }

        if (!$this->platform_media_path) {
            return null;
        }

        $extension = strtolower(pathinfo($this->platform_media_path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            default => 'image/jpeg',
        };
    }

    public function archivedOriginalName(): ?string
    {
        return $this->media_snapshot['original_name'] ?? null;
    }

    public function archivedMediaType(): ?string
    {
        return $this->media_snapshot['media_type'] ?? null;
    }

    public function archivedPresetLabel(): ?string
    {
        $presetKey = $this->media_snapshot['preset_key'] ?? null;

        if (!$presetKey) {
            return null;
        }

        return config("social-platforms.presets.{$presetKey}.name");
    }

    public function displayDimensions(): array
    {
        $snapshot = $this->media_snapshot ?? [];

        if (!empty($snapshot['width']) && !empty($snapshot['height'])) {
            return [
                'width' => (int) $snapshot['width'],
                'height' => (int) $snapshot['height'],
            ];
        }

        $presetKey = $snapshot['preset_key'] ?? null;
        if ($presetKey) {
            $preset = config("social-platforms.presets.{$presetKey}");
            if ($preset) {
                return ['width' => $preset['width'], 'height' => $preset['height']];
            }
        }

        return match ($this->provider->value) {
            'facebook' => ['width' => 1200, 'height' => 630],
            'instagram' => ['width' => 1080, 'height' => 1080],
            'pinterest' => ['width' => 1000, 'height' => 1500],
            default => ['width' => 4, 'height' => 3],
        };
    }

    public function hasDisplayableMedia(): bool
    {
        return $this->platform_media_path
            && Storage::disk('public')->exists($this->platform_media_path);
    }

    public function isVideoMedia(): bool
    {
        $mime = $this->resolvedMimeType();

        return $mime && str_starts_with($mime, 'video/');
    }

    protected static function booted(): void
    {
        static::creating(function (PostTarget $target) {
            if ($target->connection_snapshot) {
                return;
            }

            $target->connection_snapshot = $target->buildConnectionSnapshot();
        });
    }

    public static function syncAccountForDestination(SocialDestination $destination): void
    {
        self::query()
            ->where('social_destination_id', $destination->id)
            ->where('provider', $destination->provider)
            ->update(['social_account_id' => $destination->social_account_id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConnectionSnapshot(?SocialAccount $account = null, ?SocialDestination $destination = null): array
    {
        $account ??= $this->social_account_id
            ? SocialAccount::find($this->social_account_id)
            : $this->socialAccount;
        $destination ??= $this->social_destination_id
            ? SocialDestination::find($this->social_destination_id)
            : $this->socialDestination;

        $publishLabel = null;
        if ($account) {
            $publishLabel = $account->publishLabel($destination);
        } elseif ($destination) {
            $publishLabel = $destination->name;
        }

        return [
            'account_name' => $account?->name,
            'account_username' => $account?->username,
            'account_handle' => $account?->displayHandle(),
            'destination_name' => $destination?->name,
            'publish_label' => $publishLabel,
            'provider' => $this->provider->value,
        ];
    }

    public function resolvedAccountHandle(): string
    {
        if ($this->socialAccount) {
            return $this->socialAccount->displayHandle();
        }

        $snap = $this->connection_snapshot ?? [];

        if (!empty($snap['account_handle'])) {
            return $snap['account_handle'];
        }

        if (!empty($snap['account_username'])) {
            return '@' . ltrim($snap['account_username'], '@');
        }

        if (!empty($snap['account_name'])) {
            return $snap['account_name'];
        }

        return '@account';
    }

    public function resolvedPublishLabel(): string
    {
        $account = $this->socialAccount;
        $destination = $this->socialDestination;

        if ($account) {
            if ($destination && $this->provider === ProviderType::PINTEREST) {
                return $account->name . ' → ' . $destination->name;
            }

            return $account->publishLabel($destination);
        }

        $snap = $this->connection_snapshot ?? [];

        if (!empty($snap['publish_label'])) {
            return $snap['publish_label'];
        }

        if (!empty($snap['account_name']) && !empty($snap['destination_name'])) {
            return $snap['account_name'] . ' → ' . $snap['destination_name'];
        }

        if (!empty($snap['account_handle'])) {
            return $snap['account_handle'];
        }

        if ($destination?->name) {
            return $destination->name;
        }

        if (!empty($snap['destination_name'])) {
            return $snap['destination_name'];
        }

        if (!empty($snap['account_name'])) {
            return $snap['account_name'];
        }

        return ucfirst($this->provider->value) . ' account';
    }

    public function resolvedProvider(): string
    {
        return $this->provider->value;
    }

    public function publishDestinationLabel(): string
    {
        return $this->resolvedPublishLabel();
    }
}
