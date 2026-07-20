<?php

namespace App\Services\Api;

use App\Enums\PostStatus;
use App\Enums\ProviderType;
use App\Enums\SocialAccountStatus;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialDestination;
use App\Models\User;
use App\Services\Media\ImageMetadataService;
use App\Services\Media\MediaConfigService;
use App\Services\Posts\PostSchedulerService;
use App\Services\Posts\QueueScheduleCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApiPostCreator
{
    public const TIMEZONE = 'Asia/Kolkata';

    public function __construct(
        protected PostSchedulerService $scheduler,
        protected QueueScheduleCalculatorService $queueSchedule,
        protected MediaConfigService $mediaConfig,
        protected ImageMetadataService $imageMetadata,
    ) {}

    /**
     * Create a Pinterest pin from an uploaded image and schedule it in IST.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, array $input, UploadedFile $upload): Post
    {
        $channel = 'pinterest';
        $mediaType = 'image';
        $timezone = self::TIMEZONE;

        $destination = $this->resolveBoard($input);
        $account = $destination->socialAccount;

        if (! $account || $account->provider !== ProviderType::PINTEREST || $account->status !== SocialAccountStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'board_name' => 'The selected board is not linked to an active Pinterest account.',
            ]);
        }

        $this->assertAdminOwnedAccount($account);

        $mediaAsset = $this->storeUploadedMedia($user, $upload, $channel, $mediaType);
        $postType = $this->mediaConfig->defaultPostType($channel, $mediaType, $mediaAsset->preset_key);

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = 'Pin';
        }

        $platformPayload = [
            'title' => $title,
        ];
        $link = $input['link'] ?? null;
        if (is_string($link) && trim($link) !== '') {
            $platformPayload['link_url'] = trim($link);
        }

        $defaultStatus = $user->isAdmin() ? PostStatus::APPROVED : PostStatus::PENDING_APPROVAL;

        $post = $this->scheduler->createPost($user, [
            'title' => $title,
            'caption' => $input['caption'],
            'is_draft' => false,
            'status' => $defaultStatus->value,
            'hashtags' => array_values($input['hashtags'] ?? []),
            'metadata' => [
                'source' => 'api',
            ],
            'schedule_type' => 'once',
            'targets' => [[
                'social_account_id' => $account->id,
                'social_destination_id' => $destination->id,
                'provider' => $channel,
                'post_type' => $postType,
                'platform_payload' => $platformPayload,
                'media_asset_id' => $mediaAsset->id,
                'scheduled_at' => null,
                'timezone' => $timezone,
            ]],
        ]);

        if ($defaultStatus === PostStatus::APPROVED) {
            $this->applyManualSchedule($post, $input, $timezone);
        } else {
            $post->update([
                'metadata' => array_merge($post->metadata ?? [], [
                    'intended_schedule' => $this->scheduleIntentPayload($input, $timezone),
                ]),
            ]);
        }

        return $post->fresh(['targets.socialAccount', 'targets.socialDestination', 'user']);
    }

    /**
     * Resolve Pinterest board by id or name (account comes from the board).
     *
     * @param  array<string, mixed>  $input
     */
    protected function resolveBoard(array $input): SocialDestination
    {
        $adminIds = User::role('admin')->pluck('id');

        $query = SocialDestination::query()
            ->with('socialAccount')
            ->where('status', 'active')
            ->where('provider', ProviderType::PINTEREST)
            ->whereHas('socialAccount', function ($q) use ($adminIds) {
                $q->whereIn('user_id', $adminIds)->where('status', 'active');
            });

        $boardId = $input['board_id'] ?? $input['destination_id'] ?? null;
        if (! empty($boardId)) {
            $destination = (clone $query)->where('id', (int) $boardId)->first();
            if (! $destination) {
                throw ValidationException::withMessages([
                    'board_id' => 'No active Pinterest board found for the given board_id.',
                ]);
            }

            return $destination;
        }

        $name = trim((string) ($input['board_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'board_name' => 'Provide board_name or board_id.',
            ]);
        }

        $matches = (clone $query)->where('name', $name)->get();
        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'board_name' => "No active Pinterest board named \"{$name}\".",
            ]);
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'board_name' => "Multiple boards named \"{$name}\" found. Use board_id instead.",
            ]);
        }

        return $matches->first();
    }

    protected function assertAdminOwnedAccount(SocialAccount $account): void
    {
        $adminIds = User::role('admin')->pluck('id');
        if (! $adminIds->contains($account->user_id)) {
            throw ValidationException::withMessages([
                'board_name' => 'Board account is not available for publishing.',
            ]);
        }
    }

    protected function storeUploadedMedia(
        User $user,
        UploadedFile $file,
        string $channel,
        string $mediaType,
    ): MediaAsset {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        if (! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                'image' => 'The uploaded file must be an image (JPEG, PNG, or WebP).',
            ]);
        }

        $presetKey = 'pinterest_pin';
        $availablePresets = collect(
            $this->mediaConfig->presetsForChannelAndType($channel, $mediaType)
        )->pluck('key')->all();

        if ($availablePresets && ! in_array($presetKey, $availablePresets, true)) {
            $presetKey = $availablePresets[0] ?? null;
        }

        if (! $availablePresets) {
            $presetKey = null;
        }

        $preset = $presetKey ? $this->mediaConfig->preset($presetKey) : null;
        $path = $file->store('media', 'public');
        $width = $preset['width'] ?? null;
        $height = $preset['height'] ?? null;
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $tags = [$channel];
        $assetMetadata = ['source' => 'api-upload'];

        $fullPath = Storage::disk('public')->path($path);
        if ((! $width || ! $height) && function_exists('getimagesize')) {
            $sizes = @getimagesize($fullPath);
            if ($sizes) {
                $width = $sizes[0];
                $height = $sizes[1];
            }
        }

        try {
            $finalized = $this->imageMetadata->finalizeStoredImage($path);
            $path = $finalized['file_path'];
            $originalName = $finalized['original_name'];
            $fileSize = $finalized['file_size'];
            $tags = array_values(array_unique(array_merge($tags, ['canva'])));
            $assetMetadata['creator_tool'] = 'Canva';
        } catch (\Throwable $e) {
            Log::warning('API media metadata rewrite skipped', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return MediaAsset::create([
            'user_id' => $user->id,
            'file_path' => $path,
            'disk' => 'public',
            'original_name' => $originalName,
            'mime_type' => $mime,
            'media_type' => $mediaType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'preset_key' => $presetKey,
            'alt_text' => null,
            'tags' => $tags,
            'channels' => [$channel],
            'metadata' => $assetMetadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function applyManualSchedule(Post $post, array $input, string $timezone): void
    {
        [$date, $time] = $this->parseScheduleParts($input, $timezone);

        $target = $post->targets()->first();
        if (! $target) {
            throw ValidationException::withMessages([
                'schedule_time' => 'Post was created without a publish target.',
            ]);
        }

        try {
            $this->queueSchedule->scheduleManualPostAt(
                $target,
                $date,
                $time,
                $timezone
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'schedule_time' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: Carbon, 1: string}
     */
    protected function parseScheduleParts(array $input, string $timezone): array
    {
        $raw = $input['schedule_time'] ?? $input['schedule_at'] ?? null;
        if (empty($raw)) {
            throw ValidationException::withMessages([
                'schedule_time' => 'Provide schedule_time in IST, e.g. 2026-07-21 15:30.',
            ]);
        }

        $local = Carbon::parse((string) $raw, $timezone);

        return [$local->copy()->startOfDay(), $local->format('H:i')];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function scheduleIntentPayload(array $input, string $timezone): array
    {
        try {
            [$date, $time] = $this->parseScheduleParts($input, $timezone);

            return [
                'date' => $date->toDateString(),
                'time' => $time,
                'timezone' => $timezone,
            ];
        } catch (ValidationException) {
            return [
                'timezone' => $timezone,
                'raw' => $input['schedule_time'] ?? null,
            ];
        }
    }
}
