<?php

namespace App\Services\Posts;

use App\Enums\PostType;
use App\Models\Post;
use App\Models\PostTarget;
use Illuminate\Http\Request;

class PlatformPostFieldsService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fieldsFor(string $provider): array
    {
        return config("post-platform-fields.{$provider}", []);
    }

    /**
     * @return array<string, mixed>
     */
    public function collectFromRequest(Request $request, string $provider): array
    {
        $input = $request->input('platformFields', []);
        if (!is_array($input)) {
            return [];
        }

        $collected = [];

        foreach ($this->fieldsFor($provider) as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $collected[$key] = filter_var($input[$key] ?? ($field['default'] ?? false), FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            $value = $input[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if ($type === 'color' && is_string($value) && !str_starts_with($value, '#')) {
                $value = '#' . ltrim($value, '#');
            }

            $collected[$key] = is_string($value) ? trim($value) : $value;
        }

        return $collected;
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public function validationRules(string $provider, Request $request): array
    {
        $rules = ['platformFields' => 'nullable|array'];

        foreach ($this->fieldsFor($provider) as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';
            $ruleKey = "platformFields.{$key}";

            if ($type === 'checkbox') {
                $rules[$ruleKey] = 'nullable|boolean';
                continue;
            }

            $fieldRules = ['nullable'];

            if ($type === 'url') {
                $fieldRules[] = 'url';
                $fieldRules[] = 'max:2000';
            } elseif ($type === 'datetime-local') {
                $fieldRules[] = 'date';
            } elseif (isset($field['max'])) {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:' . (int) $field['max'];
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:2000';
            }

            if ($type === 'color') {
                $fieldRules[] = 'regex:/^#?[0-9A-Fa-f]{6}$/';
            }

            $rules[$ruleKey] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Human-readable platform field values for review / detail views.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array{label: string, value: string}>
     */
    public function displaySummary(?array $payload, string $provider): array
    {
        if (!$payload) {
            return [];
        }

        $summary = [];

        foreach ($this->fieldsFor($provider) as $field) {
            $key = $field['key'];
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
            } elseif ($value === null || $value === '') {
                continue;
            } elseif (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } else {
                $value = (string) $value;
            }

            $summary[] = [
                'label' => $field['label'],
                'value' => $value,
            ];
        }

        return $summary;
    }

    /**
     * Build stored platform_payload for PostTarget (form values + derived fields).
     *
     * @return array<string, mixed>
     */
    public function buildStoredPayload(Request $request, string $provider, string $campaignTitle): array
    {
        $payload = $this->collectFromRequest($request, $provider);

        if ($provider === 'pinterest') {
            $payload['title'] = $campaignTitle;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPinterestApiPayload(PostTarget $target, array $mediaSource): array
    {
        $post = $target->post;
        $destination = $target->relationLoaded('socialDestination')
            ? $target->socialDestination
            : ($target->exists ? $target->socialDestination : null);
        $platform = $target->platform_payload ?? [];

        $payload = [
            'title' => $platform['title'] ?? $post->title ?? 'Pin',
            'description' => $this->buildCaptionWithHashtags($post),
            'board_id' => $destination?->provider_destination_id,
            'media_source' => $mediaSource,
        ];

        $this->mergeApiFields($payload, $platform, [
            'link' => 'link_url',
        ]);

        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFacebookApiPayload(PostTarget $target, bool $isPhoto): array
    {
        $post = $target->post;
        $text = $this->buildCaptionWithHashtags($post);

        $payload = $isPhoto
            ? ['caption' => $text]
            : ['message' => $text];

        $payload['published'] = true;

        return $payload;
    }

    /**
     * Facebook Page video posts use /{page-id}/videos with description (not caption).
     *
     * @return array<string, mixed>
     */
    public function buildFacebookVideoApiPayload(PostTarget $target): array
    {
        $post = $target->post;
        $payload = [
            'description' => $this->buildCaptionWithHashtags($post),
            'published' => true,
        ];

        $title = trim((string) ($post->title ?? ''));
        if ($title !== '') {
            $payload['title'] = mb_substr($title, 0, 255);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildInstagramContainerPayload(PostTarget $target, string $mediaUrl): array
    {
        $post = $target->post;
        $postType = $target->post_type?->value ?? (string) $target->post_type;
        $caption = $this->buildCaptionWithHashtags($post);
        $isVideo = $target->isVideoMedia();

        if ($postType === PostType::INSTAGRAM_REEL->value || ($isVideo && $postType !== PostType::INSTAGRAM_STORY->value)) {
            return [
                'media_type' => 'REELS',
                'video_url' => $mediaUrl,
                'caption' => $caption,
                'share_to_feed' => true,
            ];
        }

        if ($postType === PostType::INSTAGRAM_STORY->value) {
            $payload = [
                'media_type' => 'STORIES',
                'caption' => $caption,
            ];

            if ($isVideo) {
                $payload['video_url'] = $mediaUrl;
            } else {
                $payload['image_url'] = $mediaUrl;
            }

            return $payload;
        }

        // Standard feed image post.
        return [
            'caption' => $caption,
            'image_url' => $mediaUrl,
        ];
    }

    public function buildCaptionWithHashtags(Post $post): string
    {
        $caption = trim($post->caption ?? '');
        $hashtags = collect($post->hashtags ?? [])
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->map(fn ($tag) => str_starts_with($tag, '#') ? $tag : '#' . $tag)
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->values();

        if ($hashtags->isEmpty()) {
            return $caption;
        }

        // Skip tags already present in the caption (automation previously embedded them).
        $captionLower = mb_strtolower($caption);
        $missing = $hashtags
            ->reject(fn ($tag) => str_contains($captionLower, mb_strtolower($tag)))
            ->values();

        if ($missing->isEmpty()) {
            return $caption;
        }

        return trim($caption . "\n\n" . $missing->implode(' '));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $platform
     * @param  array<string, string>  $apiToForm  API key => form key
     */
    protected function mergeApiFields(array &$payload, array $platform, array $apiToForm): void
    {
        foreach ($apiToForm as $apiKey => $formKey) {
            if (!array_key_exists($formKey, $platform)) {
                continue;
            }

            $value = $platform[$formKey];

            if ($apiKey === 'is_standard') {
                $payload[$apiKey] = (bool) $value;
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $payload[$apiKey] = $value;
        }
    }
}
