<?php

namespace App\Services\Automation;

use App\Models\AutomationRunItem;
use App\Services\Ai\CursorApiClient;
use App\Services\Ai\ImageGenerationService;
use App\Support\AutomationAiProvider;

class AutomationPipelineService
{
    public function __construct(
        protected AutomationIdeaService $ideas,
        protected AutomationCopyService $copy,
        protected AutomationImagePromptService $imagePrompts,
        protected ImageGenerationService $images,
        protected AutomationMediaService $media,
        protected AutomationPostFactory $posts,
        protected AutomationActorResolver $actors,
    ) {
    }

    public function processItem(AutomationRunItem $item, bool $resume = false): void
    {
        AutomationAiProvider::assertConfigured();

        if ($resume) {
            AutomationPipelineState::hydrateFromGenerationSteps($item);
        }

        $startStep = $resume ? AutomationPipelineState::resumeFrom($item) : 'idea';

        if ($startStep === 'done') {
            return;
        }

        $cursor = app(CursorApiClient::class);
        $useCursorSession = AutomationAiProvider::name() === 'cursor' && $cursor->usesCloudApi();

        AutomationGenerationLogger::bind($item);
        AutomationGenerationLogger::step('pipeline_started', [
            'topic' => $item->topic?->title,
            'topic_type' => $item->topic_type,
            'text_provider' => AutomationAiProvider::name(),
            'image_provider' => AutomationAiProvider::imageProviderNameForPlatform($item->platform ?? ''),
            'cursor_model' => AutomationAiProvider::name() === 'cursor'
                ? app(\App\Services\Ai\Cursor\CursorModelResolver::class)->resolveId((string) config('ai.cursor.chat_model', 'auto'))
                : null,
            'resuming' => $resume,
            'resume_from' => $startStep,
        ]);

        if ($useCursorSession) {
            $cursor->beginSession('PixFlow item #'.$item->id);
        }

        try {
            $this->runPipeline($item, $startStep);
            AutomationGenerationLogger::step('pipeline_completed');
        } finally {
            if ($useCursorSession) {
                $cursor->endSession();
            }
            AutomationGenerationLogger::clear();
        }
    }

    protected function runPipeline(AutomationRunItem $item, string $startStep): void
    {
        $item->load(['run.campaign.category', 'topic']);
        $campaign = $item->run->campaign;
        $category = $campaign->category;
        $topic = $item->topic;

        if (! $topic) {
            throw new \RuntimeException('No topic assigned to automation item.');
        }

        $platform = $item->platform;
        $presetKey = \App\Support\AutomationCampaignPresets::resolvePresetKey($campaign, $platform);
        $imageStyle = \App\Support\AutomationImageStyles::resolveStyleKey($campaign, $platform);
        $dims = \App\Support\AutomationCampaignPresets::presetDimensions($presetKey);
        $width = $dims['width'];
        $height = $dims['height'];

        AutomationGenerationLogger::step('image_preset_resolved', [
            'preset' => $presetKey,
            'width' => $width,
            'height' => $height,
        ]);
        AutomationGenerationLogger::step('image_style_resolved', [
            'style' => $imageStyle,
        ]);

        $idea = $this->resolveIdea($item, $category, $topic, $platform, $startStep);
        $copy = $this->resolveCopy($item, $category, $topic, $platform, $idea, $startStep);
        $designPrompt = $this->resolveDesignPrompt($item, $category, $topic, $platform, $idea, $copy, $width, $height, $imageStyle, $startStep);
        $imageResult = $this->resolveImage($item, $designPrompt, $width, $height, $startStep);
        $asset = $this->resolveMediaAsset($item, $imageResult, $platform, $presetKey, $copy, $topic, $startStep);
        $this->resolvePost($item, $copy, $asset, $startStep);
    }

    protected function resolveIdea(
        AutomationRunItem $item,
        $category,
        $topic,
        string $platform,
        string $startStep
    ): array {
        if (AutomationPipelineState::shouldReuse('idea', $startStep)) {
            $idea = AutomationPipelineState::get($item, 'idea');
            if (! is_array($idea)) {
                throw new \RuntimeException('Resume failed: saved idea not found. Run without resume or regenerate from scratch.');
            }

            AutomationGenerationLogger::step('idea_reused', ['provider' => $idea['text_provider'] ?? null]);

            return $idea;
        }

        $item->update(['status' => 'generating_idea']);
        $started = microtime(true);
        $idea = $this->ideas->generate($category, $topic, $platform, $item->topic_type);
        AutomationPipelineState::save($item, 'idea', $idea);
        AutomationGenerationLogger::step('idea_completed', [
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'provider' => $idea['text_provider'] ?? AutomationAiProvider::name(),
            'result' => $idea,
        ]);

        return $idea;
    }

    protected function resolveCopy(
        AutomationRunItem $item,
        $category,
        $topic,
        string $platform,
        array $idea,
        string $startStep
    ): array {
        if (AutomationPipelineState::shouldReuse('copy', $startStep)) {
            $copy = AutomationPipelineState::get($item, 'copy');
            if (! is_array($copy)) {
                throw new \RuntimeException('Resume failed: saved copy not found.');
            }

            AutomationGenerationLogger::step('copy_reused', ['title' => $copy['title'] ?? null]);

            return $copy;
        }

        $item->update(['status' => 'generating_copy', 'text_provider' => $idea['text_provider'] ?? null]);
        $started = microtime(true);
        $copy = $this->copy->generate($category, $topic, $platform, $item->topic_type, $idea);
        AutomationPipelineState::save($item, 'copy', $copy);
        AutomationGenerationLogger::step('copy_completed', [
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'provider' => $copy['text_provider'] ?? AutomationAiProvider::name(),
            'result' => $copy,
        ]);

        if ($copy['caption'] === '') {
            throw new \RuntimeException('AI returned empty caption.');
        }

        if (config('automation.safety_enabled', false)) {
            $unsafePhrase = $this->detectUnsafeClaim($copy['caption']);
            if ($unsafePhrase !== null) {
                AutomationGenerationLogger::step('safety_filter_blocked', [
                    'matched_phrase' => $unsafePhrase,
                    'caption_preview' => mb_substr($copy['caption'], 0, 500),
                ]);
                throw new \RuntimeException("Caption flagged for unsafe health claims (matched: {$unsafePhrase}).");
            }
        }

        return $copy;
    }

    protected function resolveDesignPrompt(
        AutomationRunItem $item,
        $category,
        $topic,
        string $platform,
        array $idea,
        array $copy,
        int $width,
        int $height,
        string $imageStyle,
        string $startStep
    ): string {
        if (AutomationPipelineState::shouldReuse('image_prompt', $startStep)) {
            $designPrompt = $item->design_prompt ?: AutomationPipelineState::get($item, 'design_prompt');
            if (! is_string($designPrompt) || trim($designPrompt) === '') {
                throw new \RuntimeException('Resume failed: saved design prompt not found.');
            }

            AutomationGenerationLogger::step('image_prompt_reused', [
                'design_prompt' => $designPrompt,
            ]);

            return $designPrompt;
        }

        $item->update(['status' => 'generating_image_prompt']);
        $started = microtime(true);
        $promptData = $this->imagePrompts->generate($category, $topic, $platform, $idea, $copy, $width, $height, $imageStyle);
        $designPrompt = $promptData['design_prompt'];
        $item->update(['design_prompt' => $designPrompt]);
        AutomationPipelineState::save($item, 'design_prompt', $designPrompt);
        AutomationPipelineState::save($item, 'image_prompt', $promptData);
        AutomationGenerationLogger::step('image_prompt_completed', [
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'provider' => $promptData['text_provider'] ?? AutomationAiProvider::name(),
            'image_style' => $imageStyle,
            'design_prompt' => $designPrompt,
        ]);

        return $designPrompt;
    }

    protected function resolveImage(
        AutomationRunItem $item,
        string $designPrompt,
        int $width,
        int $height,
        string $startStep
    ): array {
        if (AutomationPipelineState::shouldReuse('image', $startStep)) {
            $imageResult = AutomationPipelineState::get($item, 'image');
            if (! is_array($imageResult) || empty($imageResult['path'])) {
                throw new \RuntimeException('Resume failed: saved image file not found.');
            }

            AutomationGenerationLogger::step('image_reused', [
                'image_provider' => $imageResult['provider'] ?? null,
                'local_path' => $imageResult['path'],
            ]);

            return $imageResult;
        }

        $item->update(['status' => 'generating_image']);
        $started = microtime(true);

        if (\App\Support\AutomationAiProvider::usesCursorAgentImage($item->platform)) {
            app(CursorApiClient::class)->endSession();
        }

        $imageResult = $this->images->generate(
            $designPrompt,
            $width,
            $height,
            AutomationAiProvider::imageProvidersForPlatform($item->platform),
            $item->platform,
            \App\Support\AutomationImageStyles::resolveStyleKey($item->run->campaign, $item->platform)
        );
        $item->update(['image_provider' => $imageResult['provider']]);
        AutomationPipelineState::save($item, 'image', $imageResult);
        AutomationGenerationLogger::step('image_completed', [
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'image_provider' => $imageResult['provider'],
            'local_path' => $imageResult['path'],
            'width' => $imageResult['width'] ?? $width,
            'height' => $imageResult['height'] ?? $height,
            'preset_width' => $width,
            'preset_height' => $height,
        ]);

        return $imageResult;
    }

    protected function resolveMediaAsset(
        AutomationRunItem $item,
        array $imageResult,
        string $platform,
        string $presetKey,
        array $copy,
        $topic,
        string $startStep
    ): \App\Models\MediaAsset {
        if (AutomationPipelineState::shouldReuse('media', $startStep)) {
            $assetId = AutomationPipelineState::get($item, 'media_asset_id');
            $asset = $assetId ? \App\Models\MediaAsset::find($assetId) : null;
            if (! $asset) {
                throw new \RuntimeException('Resume failed: saved media asset not found.');
            }

            AutomationGenerationLogger::step('media_asset_reused', ['asset_id' => $asset->id]);

            return $asset;
        }

        $actor = $this->actors->forCampaign($item->run->campaign);
        $started = microtime(true);
        $asset = $this->media->createAssetFromGenerated(
            $actor,
            $imageResult['path'],
            $platform,
            $presetKey,
            $copy['title'] ?? $topic->title,
            $item->organization_id ?? $item->run?->organization_id ?? $item->run?->campaign?->organization_id,
        );
        AutomationPipelineState::save($item, 'media_asset_id', $asset->id);
        AutomationGenerationLogger::step('media_asset_created', [
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'asset_id' => $asset->id,
            'preset' => $presetKey,
        ]);

        return $asset;
    }

    protected function resolvePost(
        AutomationRunItem $item,
        array $copy,
        \App\Models\MediaAsset $asset,
        string $startStep
    ): void {
        if ($startStep === 'done' || $item->post_id) {
            return;
        }

        $item->update(['status' => 'creating_post']);
        $accountId = $this->posts->resolveAccountId($item);
        $post = $this->posts->create($item, $copy, $asset->id, $accountId);

        $item->update([
            'status' => 'post_created',
            'post_id' => $post->id,
            'error_message' => null,
        ]);

        AutomationGenerationLogger::step('post_created', ['post_id' => $post->id]);

        try {
            $notifications = app(\App\Services\Notifications\InAppNotificationService::class);
            $title = $post->title ?: ('Post #'.$post->id);
            $notifications->notifyAdmins(
                'post_created_ai',
                'AI agent created a post',
                "\"{$title}\" is ready for review ({$item->platform}).",
                'info',
                route('admin.automation.auto-review'),
                ['post_id' => $post->id, 'run_item_id' => $item->id, 'platform' => $item->platform],
                $post->organization_id,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        if (config('automation.auto_review.enabled') && config('automation.auto_review.dispatch_after_create', true)) {
            \App\Jobs\AutoReviewPostJob::dispatch($post->id);
            AutoReviewLogger::info('Dispatched auto-review after post create', ['post_id' => $post->id]);
        }
    }

    protected function detectUnsafeClaim(string $text): ?string
    {
        $blocked = ['cure', 'guaranteed', 'miracle', 'instantly cure', 'replace your doctor', '100% effective'];

        foreach ($blocked as $phrase) {
            if (preg_match('/\b'.preg_quote($phrase, '/').'\b/i', $text)) {
                return $phrase;
            }
        }

        return null;
    }
}
