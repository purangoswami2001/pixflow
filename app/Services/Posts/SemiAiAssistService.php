<?php

namespace App\Services\Posts;

use App\Models\ContentCategory;
use App\Models\MediaAsset;
use App\Services\Automation\PromptTemplateRenderer;
use App\Services\Media\ImageMetadataService;
use App\Services\Media\MediaConfigService;
use App\Support\AutomationImageStyles;
use App\Support\AutomationPromptSchema;
use App\Support\PlatformPromptGuardrails;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SemiAiAssistService
{
    public function __construct(
        protected PromptTemplateRenderer $renderer,
        protected MediaConfigService $mediaConfig,
        protected ImageMetadataService $imageMetadata,
    ) {}

    /**
     * @return array{prompt: string, system: string, user: string, schema: array<string, mixed>, schema_hint: string}
     */
    public function buildIdeaPrompt(
        string $platform,
        string $topic,
        string $topicType,
        ?ContentCategory $category = null,
        ?string $customNiche = null,
        ?string $customAudience = null,
    ): array {
        $vars = $this->baseVariables($platform, $topic, $topicType, $category, $customNiche, $customAudience);

        $system = PlatformPromptGuardrails::ideaSystem($platform, $topicType)
            .' '
            .(config('automation.safety_enabled', false) ? 'Content must be evidence-based and safe. ' : '')
            .$this->schemaInstruction('idea');

        $user = $this->renderer->render($platform, 'idea', $vars);
        $user .= "\n\n".PlatformPromptGuardrails::qualityFooter($platform, $topicType);

        return $this->bundlePrompt($system, $user, 'idea');
    }

    /**
     * @param  array<string, mixed>  $idea
     * @return array{prompt: string, system: string, user: string, schema: array<string, mixed>, schema_hint: string, idea: array<string, mixed>}
     */
    public function buildCopyPrompt(
        string $platform,
        string $topic,
        string $topicType,
        array $idea,
        ?ContentCategory $category = null,
        ?string $customNiche = null,
        ?string $customAudience = null,
        ?string $linkUrl = null,
    ): array {
        $idea = $this->normalizeIdea($idea);
        $vars = $this->baseVariables($platform, $topic, $topicType, $category, $customNiche, $customAudience);

        $keyPoints = is_array($idea['key_points'] ?? null)
            ? implode("\n- ", $idea['key_points'])
            : '';

        $vars = array_merge($vars, [
            'pin_title' => $idea['pin_title'] ?? $topic,
            'angle' => $idea['angle'] ?? '',
            'key_points' => $keyPoints,
            'seo_keywords' => is_array($idea['seo_keywords'] ?? null)
                ? implode(', ', $idea['seo_keywords'])
                : '',
            'link_url' => $linkUrl ?? '',
        ]);

        $system = PlatformPromptGuardrails::copySystem($platform, $topicType)
            .' '
            .(config('automation.safety_enabled', false) ? 'Never make unsafe medical claims. ' : '')
            .$this->schemaInstruction('copy');

        $user = $this->renderer->render($platform, 'copy', $vars);
        $user .= "\n\n".PlatformPromptGuardrails::qualityFooter($platform, $topicType);

        return array_merge($this->bundlePrompt($system, $user, 'copy'), ['idea' => $idea]);
    }

    /**
     * @param  array<string, mixed>  $idea
     * @param  array<string, mixed>  $copy
     * @return array{prompt: string, system: string, user: string, schema: array<string, mixed>, schema_hint: string, idea: array<string, mixed>, copy: array<string, mixed>, width: int, height: int, image_style: string}
     */
    public function buildImagePrompt(
        string $platform,
        string $topic,
        string $topicType,
        array $idea,
        array $copy,
        ?string $presetKey = null,
        ?string $imageStyle = null,
        ?ContentCategory $category = null,
        ?string $customNiche = null,
        ?string $customAudience = null,
    ): array {
        $idea = $this->normalizeIdea($idea);
        $copy = $this->normalizeCopy($copy);

        $style = $imageStyle && AutomationImageStyles::isValid($imageStyle)
            ? $imageStyle
            : AutomationImageStyles::defaultForPlatform($platform);

        [$width, $height] = $this->resolveDimensions($platform, $presetKey);

        $vars = $this->baseVariables($platform, $topic, $topicType, $category, $customNiche, $customAudience);

        $keyPoints = is_array($idea['key_points'] ?? null)
            ? implode("\n", array_slice($idea['key_points'], 0, 5))
            : '';

        $title = $copy['title'] ?? $idea['pin_title'] ?? $topic;
        $palette = PlatformPromptGuardrails::colorPalette($vars['niche'] ?? null, $vars['audience'] ?? null);

        $vars = array_merge($vars, [
            'pin_title' => $title,
            'caption' => $copy['caption'] ?? '',
            'key_points' => $keyPoints,
            'width' => $width,
            'height' => $height,
            'image_style' => $style,
            'color_palette' => $palette,
        ]);

        $system = PlatformPromptGuardrails::imageSystem($platform, $style, $topicType)
            .' '.$this->schemaInstruction('image');

        $rendered = trim($this->renderer->render($platform, 'image', $vars));
        $platformLabel = PlatformPromptGuardrails::platformLabel($platform);
        $user = $rendered !== ''
            ? $rendered."\n\nSelected image style (follow exactly): ".AutomationImageStyles::promptHint($style)
            : "Create a {$width}x{$height}px {$platformLabel} design_prompt for topic \"{$topic}\". Style: ".AutomationImageStyles::promptHint($style);

        $user .= "\n\n".PlatformPromptGuardrails::qualityFooter($platform, $topicType, $style);
        $user .= "\n".AutomationImageStyles::styleLockClause($style);
        $user .= "\n".AutomationImageStyles::textSafetyClause($width, $height);

        return array_merge($this->bundlePrompt($system, $user, 'image'), [
            'idea' => $idea,
            'copy' => $copy,
            'width' => $width,
            'height' => $height,
            'image_style' => $style,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateIdeaJson(string $json): array
    {
        $data = $this->decodeJson($json, 'idea_json');

        return $this->normalizeIdea($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateCopyJson(string $json): array
    {
        $data = $this->decodeJson($json, 'copy_json');

        return $this->normalizeCopy($data);
    }

    /**
     * @return array{design_prompt: string}
     */
    public function validateImageJson(string $json): array
    {
        $data = $this->decodeJson($json, 'image_json');
        $designPrompt = trim((string) ($data['design_prompt'] ?? ''));

        if ($designPrompt === '') {
            throw ValidationException::withMessages([
                'image_json' => 'Missing required key: design_prompt (non-empty string).',
            ]);
        }

        return ['design_prompt' => $designPrompt];
    }

    public function resolveCategory(?int $categoryId): ?ContentCategory
    {
        if (! $categoryId) {
            return null;
        }

        return ContentCategory::query()
            ->where('is_active', true)
            ->find($categoryId);
    }

    /**
     * Upload an AI-generated image and attribute filename/metadata to the current user.
     *
     * @param  array<int, string>  $extraTags
     */
    public function uploadAttributedImage(
        UploadedFile $file,
        string $channel,
        string $mediaType,
        ?string $presetKey,
        string $userName,
        int $userId,
        array $extraTags = [],
    ): MediaAsset {
        $availablePresets = collect(
            $this->mediaConfig->presetsForChannelAndType($channel, $mediaType)
        )->pluck('key')->all();

        if ($availablePresets && $presetKey && ! in_array($presetKey, $availablePresets, true)) {
            throw ValidationException::withMessages([
                'preset_key' => 'Please select a valid size for this channel and media type.',
            ]);
        }

        if (! $availablePresets) {
            $presetKey = null;
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $isImage = str_starts_with($mime, 'image/');
        $isVideo = str_starts_with($mime, 'video/');

        if ($mediaType === 'image' && ! $isImage) {
            throw ValidationException::withMessages([
                'file' => 'Selected media type is Image, but the uploaded file is not an image.',
            ]);
        }

        if ($mediaType === 'video' && ! $isVideo) {
            throw ValidationException::withMessages([
                'file' => 'Selected media type is Video, but the uploaded file is not a video.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: ($isImage ? 'png' : 'mp4'));
        $path = $file->store('media', 'public');

        $preset = $presetKey ? $this->mediaConfig->preset($presetKey) : null;
        $width = $preset['width'] ?? null;
        $height = $preset['height'] ?? null;

        if ($isImage && function_exists('getimagesize') && (! $width || ! $height)) {
            $sizes = @getimagesize(Storage::disk('public')->path($path));
            if ($sizes) {
                $width = $sizes[0];
                $height = $sizes[1];
            }
        }

        $originalName = $file->getClientOriginalName() ?: "upload.{$extension}";
        $fileSize = $file->getSize();
        $tags = array_values(array_unique(array_filter(array_merge(
            [$channel],
            $extraTags,
        ))));
        $assetMetadata = [
            // Keep file/library attribution neutral. AI provenance belongs to the
            // Post metadata, not the uploaded image's embedded/public metadata.
            'source' => 'user-upload',
            'attributed_to' => $userName,
        ];

        if ($isImage) {
            try {
                // Same Canva-style embedded metadata rewrite as automation exports.
                $finalized = $this->imageMetadata->finalizeStoredImage($path);
                $path = $finalized['file_path'];
                $originalName = $finalized['original_name'];
                $fileSize = $finalized['file_size'];
                $tags = array_values(array_unique(array_merge($tags, ['canva'])));
                $assetMetadata['creator_tool'] = 'Canva';
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Semi-AI metadata rewrite skipped after upload', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return MediaAsset::create([
            'user_id' => $userId,
            'file_path' => $path,
            'disk' => 'public',
            'original_name' => $originalName,
            'mime_type' => $mime,
            'media_type' => $mediaType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'preset_key' => $presetKey,
            'alt_text' => "Created by {$userName}",
            'tags' => $tags,
            'channels' => [$channel],
            'metadata' => $assetMetadata,
        ]);
    }

    /**
     * @return array{prompt: string, system: string, user: string, schema: array<string, mixed>, schema_hint: string}
     */
    protected function bundlePrompt(string $system, string $user, string $step): array
    {
        $schema = AutomationPromptSchema::steps()[$step] ?? [];
        $schemaHint = $this->schemaHintText($step);

        $prompt = trim(
            "=== SYSTEM ===\n{$system}\n\n"
            ."=== USER ===\n{$user}\n\n"
            ."=== REQUIRED JSON OUTPUT ===\n{$schemaHint}"
        );

        return [
            'prompt' => $prompt,
            'system' => $system,
            'user' => $user,
            'schema' => $schema,
            'schema_hint' => $schemaHint,
        ];
    }

    protected function schemaInstruction(string $step): string
    {
        return 'Use exactly these JSON keys: '.$this->schemaHintText($step);
    }

    protected function schemaHintText(string $step): string
    {
        $schema = AutomationPromptSchema::steps()[$step] ?? null;
        if (! $schema || empty($schema['keys'])) {
            return '{}';
        }

        $parts = [];
        foreach ($schema['keys'] as $key => $meta) {
            $type = $meta['type'] ?? 'string';
            $req = ! empty($meta['required']) ? 'required' : 'optional';
            $parts[] = "{$key} ({$type}, {$req})";
        }

        return '{ '.implode('; ', $parts).' }';
    }

    /**
     * @return array<string, string>
     */
    protected function baseVariables(
        string $platform,
        string $topic,
        string $topicType,
        ?ContentCategory $category,
        ?string $customNiche,
        ?string $customAudience,
    ): array {
        $niche = $category?->niche_prompt
            ?: $category?->name
            ?: (trim((string) $customNiche) ?: 'general audience content');

        $audience = $category?->audience
            ?: (trim((string) $customAudience) ?: 'adults interested in this topic');

        $safety = config('automation.safety_enabled', false)
            ? ($category?->safety_rules ?? 'Be accurate. Avoid absolute health claims unless evidence is provided.')
            : ($category?->safety_rules ?? '');

        return [
            'niche' => $niche,
            'audience' => $audience,
            'safety_rules' => $safety,
            'topic' => $topic,
            'topic_type' => $topicType,
            'platform' => $platform,
            'color_palette' => PlatformPromptGuardrails::colorPalette($niche, $audience),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeIdea(array $data): array
    {
        $pinTitle = trim((string) ($data['pin_title'] ?? $data['title'] ?? ''));
        $angle = trim((string) ($data['angle'] ?? ''));
        $keyPoints = $data['key_points'] ?? [];

        if (! is_array($keyPoints)) {
            $keyPoints = [];
        }

        $keyPoints = array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            $keyPoints
        ), fn ($p) => $p !== ''));

        $errors = [];
        if ($pinTitle === '') {
            $errors[] = 'pin_title (required string)';
        }
        if ($angle === '') {
            $errors[] = 'angle (required string)';
        }
        if (count($keyPoints) < 1) {
            $errors[] = 'key_points (required array of strings, at least 1)';
        }

        if ($errors) {
            throw ValidationException::withMessages([
                'idea_json' => 'Missing or invalid: '.implode(', ', $errors).'.',
            ]);
        }

        $seo = $data['seo_keywords'] ?? [];
        if (! is_array($seo)) {
            $seo = [];
        }

        return [
            'pin_title' => mb_substr($pinTitle, 0, 100),
            'angle' => $angle,
            'key_points' => array_slice($keyPoints, 0, 8),
            'seo_keywords' => array_values(array_filter(array_map(
                fn ($k) => trim((string) $k),
                $seo
            ))),
            'safety_notes' => trim((string) ($data['safety_notes'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeCopy(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $caption = trim((string) ($data['caption'] ?? ''));

        $errors = [];
        if ($title === '') {
            $errors[] = 'title (required string)';
        }
        if ($caption === '') {
            $errors[] = 'caption (required string)';
        }

        if ($errors) {
            throw ValidationException::withMessages([
                'copy_json' => 'Missing or invalid: '.implode(', ', $errors).'.',
            ]);
        }

        if (mb_strlen($title) > 100) {
            $title = mb_substr($title, 0, 97).'...';
        }

        $hashtags = $data['hashtags'] ?? [];
        if (! is_array($hashtags)) {
            $hashtags = [];
        }

        $hashtags = array_values(array_filter(array_map(function ($tag) {
            $tag = trim((string) $tag);

            return $tag === '' ? null : (str_starts_with($tag, '#') ? $tag : "#{$tag}");
        }, $hashtags)));

        $linkUrl = trim((string) ($data['link_url'] ?? ''));

        return [
            'title' => $title,
            'caption' => $caption,
            'hashtags' => $hashtags,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(string $json, string $field): array
    {
        $json = trim($json);
        if ($json === '') {
            throw ValidationException::withMessages([
                $field => 'Paste the JSON response from your AI model.',
            ]);
        }

        // Allow accidental markdown fences from chat UIs.
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $json, $m)) {
            $json = trim($m[1]);
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Invalid JSON. Paste a single JSON object.',
            ]);
        }

        return $decoded;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function resolveDimensions(string $platform, ?string $presetKey): array
    {
        if ($presetKey) {
            $preset = $this->mediaConfig->preset($presetKey);
            if ($preset && ! empty($preset['width']) && ! empty($preset['height'])) {
                return [(int) $preset['width'], (int) $preset['height']];
            }
        }

        return match ($platform) {
            'facebook' => [1200, 630],
            'instagram' => [1080, 1080],
            'pinterest' => [1000, 1500],
            default => [1080, 1080],
        };
    }
}
