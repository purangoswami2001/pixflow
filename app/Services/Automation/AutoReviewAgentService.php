<?php

namespace App\Services\Automation;

use App\Enums\PostStatus;
use App\Exceptions\AiGenerationException;
use App\Models\Post;
use App\Models\PostTarget;
use App\Services\Ai\AiTextService;
use App\Services\Posts\PostReviewService;
use App\Support\AutomationAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AutoReviewAgentService
{
    public function __construct(
        protected PostReviewService $reviews,
        protected AiTextService $text,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('automation.auto_review.enabled', false);
    }

    /**
     * True only for full-automation pipeline posts (not manual or semi-AI).
     */
    public function isEligibleForAutoReview(Post $post): bool
    {
        $meta = $post->metadata ?? [];
        $source = (string) ($meta['source'] ?? '');

        if ($source !== 'automation') {
            return false;
        }

        // Reject explicit non-automation origins if ever nested/spoofed.
        $blocked = ['manual', 'semi-ai', 'semi_ai', 'semi', 'manual_admin', 'manual-upload'];
        if (in_array(strtolower($source), $blocked, true)) {
            return false;
        }

        // Require pipeline fingerprints so only campaign-generated posts qualify.
        if (empty($meta['run_id']) || empty($meta['run_item_id'])) {
            return false;
        }

        return true;
    }

    /**
     * Human-readable skip reason when a post is not eligible.
     */
    public function ineligibilityReason(Post $post): string
    {
        $source = (string) (($post->metadata['source'] ?? '') ?: 'unset');

        if (in_array(strtolower($source), ['manual', 'semi-ai', 'semi_ai', 'semi', 'manual_admin', 'manual-upload'], true)) {
            return "Skipped: {$source} posts are reviewed only by admins (not the auto-reviewer).";
        }

        if ($source !== 'automation') {
            return 'Skipped: only automation-generated posts can be auto-reviewed.';
        }

        if (empty($post->metadata['run_id']) || empty($post->metadata['run_item_id'])) {
            return 'Skipped: post is missing automation run fingerprints (not a pipeline post).';
        }

        return 'Skipped: post is not eligible for auto-review.';
    }

    /**
     * Review a single pending automation post and apply the decision.
     *
     * @return array{skipped?: bool, decision?: string, post?: Post, reason?: string, report?: array<string, mixed>}
     */
    public function reviewPost(Post $post): array
    {
        if (! $this->enabled()) {
            return ['skipped' => true, 'reason' => 'Auto-review is disabled.'];
        }

        $post->loadMissing(['targets', 'user']);

        if ($post->status !== PostStatus::PENDING_APPROVAL) {
            return ['skipped' => true, 'reason' => 'Post is not pending approval.'];
        }

        if (! $this->isEligibleForAutoReview($post)) {
            $reason = $this->ineligibilityReason($post);
            AutoReviewLogger::info('Skipped non-automation post', [
                'post_id' => $post->id,
                'source' => $post->metadata['source'] ?? null,
                'reason' => $reason,
            ]);

            return ['skipped' => true, 'reason' => $reason];
        }

        if (! empty($post->metadata['auto_review']['decision']) && ! config('automation.auto_review.allow_rereview', false)) {
            return ['skipped' => true, 'reason' => 'Post already has an auto-review decision.'];
        }

        AutoReviewLogger::info('Starting review', ['post_id' => $post->id]);

        $report = $this->analyze($post);
        $decision = $report['final_decision'];
        $note = $report['admin_note'];

        AutoReviewLogger::decision($post->id, $decision, [
            'overall_score' => $report['overall_score'],
            'confidence' => $report['confidence'],
            'provider' => $report['provider'] ?? null,
            'gates' => $report['deterministic_gates'] ?? [],
        ]);

        $result = $this->reviews->apply(
            $post,
            $decision,
            $note,
            'auto-review-agent',
            [
                'agent' => 'AutoReviewAgent',
                'version' => 1,
                'overall_score' => $report['overall_score'],
                'confidence' => $report['confidence'],
                'scores' => $report['scores'],
                'reasons' => $report['reasons'],
                'revision_checklist' => $report['revision_checklist'],
                'deterministic_gates' => $report['deterministic_gates'],
                'image_findings' => $report['image_findings'],
                'metadata_findings' => $report['metadata_findings'],
                'provider' => $report['provider'],
                'raw_ai' => $report['raw_ai'],
            ]
        );

        return [
            'decision' => $decision,
            'post' => $result['post'],
            'report' => $report,
            'message' => $result['message'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(Post $post): array
    {
        $target = $post->targets->first();
        $gates = $this->runDeterministicGates($post, $target);
        $imageFindings = $this->inspectImage($target);
        $metadataFindings = $this->inspectMetadata($post, $target);

        $hardFail = collect($gates)->firstWhere('severity', 'reject');
        if ($hardFail) {
            return $this->buildReport(
                decision: 'reject',
                overall: 15,
                confidence: 0.95,
                scores: [
                    'content_quality' => 20,
                    'image_quality' => $imageFindings['score'] ?? 20,
                    'metadata_integrity' => $metadataFindings['score'] ?? 20,
                    'platform_fit' => 20,
                    'growth_potential' => 10,
                    'safety' => 10,
                ],
                reasons: [$hardFail['message']],
                revisionChecklist: [],
                adminNote: 'Auto-rejected: '.$hardFail['message'],
                gates: $gates,
                imageFindings: $imageFindings,
                metadataFindings: $metadataFindings,
                provider: 'deterministic',
                rawAi: null,
            );
        }

        $hardRevision = collect($gates)->where('severity', 'revision')->values()->all();
        $ai = $this->askAiReviewer($post, $target, $gates, $imageFindings, $metadataFindings);

        $decision = $ai['decision'] ?? 'revision';
        $confidence = (float) ($ai['confidence'] ?? 0.5);
        $scores = array_merge([
            'content_quality' => 50,
            'image_quality' => $imageFindings['score'] ?? 50,
            'metadata_integrity' => $metadataFindings['score'] ?? 50,
            'platform_fit' => 50,
            'growth_potential' => 50,
            'safety' => 50,
        ], is_array($ai['scores'] ?? null) ? $ai['scores'] : []);

        // Blend deterministic image/metadata scores into AI scores when AI omitted them.
        $scores['image_quality'] = (int) round((((int) $scores['image_quality']) + (int) ($imageFindings['score'] ?? 50)) / 2);
        $scores['metadata_integrity'] = (int) round((((int) $scores['metadata_integrity']) + (int) ($metadataFindings['score'] ?? 50)) / 2);

        $overall = (int) round(array_sum($scores) / max(1, count($scores)));
        if (isset($ai['overall_score'])) {
            $overall = (int) round(($overall + (int) $ai['overall_score']) / 2);
        }

        $minApprove = (int) config('automation.auto_review.min_approve_score', 75);
        $minConfidence = (float) config('automation.auto_review.min_confidence', 0.65);

        if ($hardRevision !== []) {
            $decision = 'revision';
        }

        if ($decision === 'approve' && ($overall < $minApprove || $confidence < $minConfidence)) {
            $decision = 'revision';
            $ai['reasons'] = array_values(array_unique(array_merge(
                $ai['reasons'] ?? [],
                ["Score/confidence below auto-approve threshold (score {$overall}/{$minApprove}, confidence {$confidence}/{$minConfidence})."]
            )));
        }

        if (! in_array($decision, ['approve', 'reject', 'revision'], true)) {
            $decision = 'revision';
        }

        $reasons = array_values(array_unique(array_filter(array_merge(
            array_column($hardRevision, 'message'),
            $ai['reasons'] ?? [],
            $imageFindings['issues'] ?? [],
            $metadataFindings['issues'] ?? [],
        ))));

        $checklist = array_values(array_unique(array_filter(array_merge(
            array_column($hardRevision, 'fix'),
            $ai['revision_checklist'] ?? [],
            $imageFindings['fixes'] ?? [],
            $metadataFindings['fixes'] ?? [],
        ))));

        $adminNote = trim((string) ($ai['admin_note'] ?? ''));
        if ($adminNote === '') {
            $adminNote = $this->defaultNote($decision, $reasons, $checklist);
        }

        return $this->buildReport(
            decision: $decision,
            overall: $overall,
            confidence: $confidence,
            scores: $scores,
            reasons: $reasons,
            revisionChecklist: $checklist,
            adminNote: $adminNote,
            gates: $gates,
            imageFindings: $imageFindings,
            metadataFindings: $metadataFindings,
            provider: $ai['_provider'] ?? 'ai',
            rawAi: $ai,
        );
    }

    /**
     * @return list<array{code: string, severity: string, message: string, fix?: string}>
     */
    protected function runDeterministicGates(Post $post, ?PostTarget $target): array
    {
        $gates = [];
        $caption = trim((string) $post->caption);
        $title = trim((string) $post->title);
        $platform = $target?->provider?->value ?? ($post->metadata['platform'] ?? 'unknown');

        if ($caption === '' || mb_strlen($caption) < 40) {
            $gates[] = [
                'code' => 'caption_too_short',
                'severity' => 'revision',
                'message' => 'Caption is missing or too short to be useful for growth.',
                'fix' => 'Expand the caption with a clear hook, value, and CTA appropriate for '.$platform.'.',
            ];
        }

        if ($platform === 'pinterest' && ($title === '' || mb_strlen($title) < 8)) {
            $gates[] = [
                'code' => 'pin_title_weak',
                'severity' => 'revision',
                'message' => 'Pinterest title is missing or too weak for discovery.',
                'fix' => 'Write a specific, search-friendly pin title under 100 characters.',
            ];
        }

        if (! $target || ! $target->hasDisplayableMedia()) {
            $gates[] = [
                'code' => 'missing_media',
                'severity' => 'reject',
                'message' => 'Post has no publishable media asset.',
            ];
        }

        if (config('automation.safety_enabled', false)) {
            $blocked = ['cure', 'guaranteed', 'miracle', 'instantly cure', 'replace your doctor', '100% effective'];
            foreach ($blocked as $phrase) {
                if (preg_match('/\b'.preg_quote($phrase, '/').'\b/i', $caption.' '.$title)) {
                    $gates[] = [
                        'code' => 'unsafe_claim',
                        'severity' => 'reject',
                        'message' => "Caption/title contains blocked safety phrase: {$phrase}.",
                    ];
                    break;
                }
            }
        }

        $hashtags = $post->hashtags ?? [];
        if (is_array($hashtags) && count($hashtags) > 20) {
            $gates[] = [
                'code' => 'hashtag_spam',
                'severity' => 'revision',
                'message' => 'Too many hashtags (looks spammy for growth).',
                'fix' => 'Keep 4–12 niche-relevant hashtags.',
            ];
        }

        return $gates;
    }

    /**
     * @return array<string, mixed>
     */
    protected function inspectImage(?PostTarget $target): array
    {
        $findings = [
            'score' => 50,
            'issues' => [],
            'fixes' => [],
            'width' => null,
            'height' => null,
            'bytes' => null,
            'mime' => null,
            'vision_summary' => null,
        ];

        if (! $target || ! $target->platform_media_path) {
            $findings['score'] = 10;
            $findings['issues'][] = 'No archived media path on target.';
            $findings['fixes'][] = 'Regenerate and attach a platform-sized image.';

            return $findings;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($target->platform_media_path)) {
            $findings['score'] = 10;
            $findings['issues'][] = 'Media file missing from storage.';
            $findings['fixes'][] = 'Re-archive media before publishing.';

            return $findings;
        }

        $absolute = $disk->path($target->platform_media_path);
        $findings['bytes'] = $disk->size($target->platform_media_path);
        $findings['mime'] = $target->resolvedMimeType();

        if (function_exists('getimagesize')) {
            $sizes = @getimagesize($absolute);
            if ($sizes) {
                $findings['width'] = $sizes[0];
                $findings['height'] = $sizes[1];
            }
        }

        $score = 80;
        if (($findings['bytes'] ?? 0) < 15_000) {
            $score -= 25;
            $findings['issues'][] = 'Image file is unusually small (likely low quality).';
            $findings['fixes'][] = 'Regenerate a sharper, higher-resolution image.';
        }
        if (($findings['bytes'] ?? 0) > 8_000_000) {
            $score -= 10;
            $findings['issues'][] = 'Image is very large; may slow publishing.';
        }
        if (! $findings['width'] || ! $findings['height']) {
            $score -= 20;
            $findings['issues'][] = 'Could not read image dimensions.';
        } elseif ($findings['width'] < 600 || $findings['height'] < 600) {
            $score -= 20;
            $findings['issues'][] = 'Image dimensions are below recommended social sizes.';
            $findings['fixes'][] = 'Export at the selected platform preset size.';
        }

        if (config('automation.auto_review.vision_enabled', true)) {
            $vision = $this->visionDescribe($absolute, $findings['mime'] ?? 'image/png');
            if ($vision) {
                $findings['vision_summary'] = $vision['summary'] ?? null;
                $findings['vision_score'] = $vision['score'] ?? null;
                $findings['vision_issues'] = $vision['issues'] ?? [];
                if (isset($vision['score'])) {
                    $score = (int) round(($score + (int) $vision['score']) / 2);
                }
                foreach ($vision['issues'] ?? [] as $issue) {
                    $findings['issues'][] = $issue;
                }
                foreach ($vision['fixes'] ?? [] as $fix) {
                    $findings['fixes'][] = $fix;
                }
            }
        }

        $findings['score'] = max(0, min(100, $score));
        $findings['issues'] = array_values(array_unique($findings['issues']));
        $findings['fixes'] = array_values(array_unique($findings['fixes']));

        return $findings;
    }

    /**
     * @return array<string, mixed>
     */
    protected function inspectMetadata(Post $post, ?PostTarget $target): array
    {
        $findings = [
            'score' => 70,
            'issues' => [],
            'fixes' => [],
            'post_metadata' => $post->metadata ?? [],
            'media_snapshot' => $target?->media_snapshot ?? [],
        ];

        $meta = $post->metadata ?? [];
        if (($meta['source'] ?? null) !== 'automation') {
            $findings['score'] -= 20;
            $findings['issues'][] = 'Post source is not marked as automation.';
        }

        foreach (['run_id', 'run_item_id', 'platform', 'topic_type'] as $key) {
            if (empty($meta[$key])) {
                $findings['score'] -= 5;
                $findings['issues'][] = "Missing automation metadata key: {$key}.";
            }
        }

        $asset = $target?->mediaAsset;
        if ($asset) {
            $assetMeta = $asset->metadata ?? [];
            $tags = $asset->tags ?? [];
            if (($assetMeta['creator_tool'] ?? null) !== 'Canva' && ! in_array('canva', $tags, true)) {
                $findings['score'] -= 10;
                $findings['issues'][] = 'Image metadata does not look Canva-normalized.';
                $findings['fixes'][] = 'Re-run media:rewrite-canva-metadata or regenerate media.';
            }
            if (empty($asset->original_name) || ! str_contains(strtolower((string) $asset->original_name), 'untitled design')) {
                $findings['score'] -= 5;
                $findings['issues'][] = 'Original filename is not Canva-style.';
            }
        } else {
            $findings['score'] -= 15;
            $findings['issues'][] = 'Linked media asset missing for metadata inspection.';
        }

        $findings['score'] = max(0, min(100, (int) $findings['score']));
        $findings['issues'] = array_values(array_unique($findings['issues']));
        $findings['fixes'] = array_values(array_unique($findings['fixes']));

        return $findings;
    }

    /**
     * @param  list<array<string, mixed>>  $gates
     * @param  array<string, mixed>  $imageFindings
     * @param  array<string, mixed>  $metadataFindings
     * @return array<string, mixed>
     */
    protected function askAiReviewer(
        Post $post,
        ?PostTarget $target,
        array $gates,
        array $imageFindings,
        array $metadataFindings
    ): array {
        $platform = $target?->provider?->value ?? ($post->metadata['platform'] ?? 'unknown');
        $system = <<<'SYS'
You are PixFlow AutoReview Agent — an enterprise social publishing quality reviewer.
Decide if an automation-generated post should be approved into the publish queue, sent back for revision, or rejected.
Optimize for account growth: clarity, usefulness, niche fit, platform-native quality, visual polish, and safety.
Never invent facts about the image that contradict the provided image findings.
Respond with JSON only.
SYS;

        $payload = [
            'platform' => $platform,
            'title' => $post->title,
            'caption' => $post->caption,
            'hashtags' => $post->hashtags ?? [],
            'tags' => $post->tags ?? [],
            'topic_type' => $post->metadata['topic_type'] ?? null,
            'design_prompt' => $post->metadata['design_prompt'] ?? null,
            'deterministic_gates' => $gates,
            'image_findings' => [
                'score' => $imageFindings['score'] ?? null,
                'width' => $imageFindings['width'] ?? null,
                'height' => $imageFindings['height'] ?? null,
                'bytes' => $imageFindings['bytes'] ?? null,
                'issues' => $imageFindings['issues'] ?? [],
                'vision_summary' => $imageFindings['vision_summary'] ?? null,
                'vision_issues' => $imageFindings['vision_issues'] ?? [],
            ],
            'metadata_findings' => [
                'score' => $metadataFindings['score'] ?? null,
                'issues' => $metadataFindings['issues'] ?? [],
            ],
            'required_json' => [
                'decision' => 'approve|revision|reject',
                'confidence' => '0..1',
                'overall_score' => '0..100',
                'scores' => [
                    'content_quality', 'image_quality', 'metadata_integrity',
                    'platform_fit', 'growth_potential', 'safety',
                ],
                'admin_note' => 'short note for operators/creators',
                'reasons' => ['array of strings'],
                'revision_checklist' => ['array of concrete fixes if revision'],
            ],
            'policy' => [
                'approve_only_if_useful_for_growth' => true,
                'reject_unsafe_or_unusable' => true,
                'revision_for_fixable_quality_gaps' => true,
            ],
        ];

        try {
            $providers = array_values(array_unique(array_filter(array_merge(
                AutomationAiProvider::textProviders(),
                config('ai.text_providers', ['openai', 'gemini'])
            ))));

            $ai = $this->text->generateJson(
                $system,
                'Review this automation post package and return the required JSON:\n'.json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                $providers
            );

            $ai['decision'] = strtolower((string) ($ai['decision'] ?? 'revision'));
            $ai['confidence'] = (float) ($ai['confidence'] ?? 0.5);
            $ai['reasons'] = array_values(array_filter((array) ($ai['reasons'] ?? [])));
            $ai['revision_checklist'] = array_values(array_filter((array) ($ai['revision_checklist'] ?? [])));
            $ai['scores'] = is_array($ai['scores'] ?? null) ? $ai['scores'] : [];

            return $ai;
        } catch (AiGenerationException|\Throwable $e) {
            AutoReviewLogger::warning('AI reviewer failed; falling back to heuristic decision', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            $avg = (int) round((
                ($imageFindings['score'] ?? 50)
                + ($metadataFindings['score'] ?? 50)
                + (mb_strlen((string) $post->caption) > 120 ? 70 : 40)
            ) / 3);

            return [
                'decision' => $avg >= (int) config('automation.auto_review.min_approve_score', 75) ? 'approve' : 'revision',
                'confidence' => 0.45,
                'overall_score' => $avg,
                'scores' => [
                    'content_quality' => mb_strlen((string) $post->caption) > 120 ? 70 : 40,
                    'image_quality' => $imageFindings['score'] ?? 50,
                    'metadata_integrity' => $metadataFindings['score'] ?? 50,
                    'platform_fit' => 55,
                    'growth_potential' => $avg,
                    'safety' => 70,
                ],
                'admin_note' => $avg >= 75
                    ? 'Approved by heuristic fallback after AI reviewer failure.'
                    : 'Needs revision (AI reviewer unavailable). Improve caption usefulness and image clarity.',
                'reasons' => ['AI reviewer unavailable: '.$e->getMessage()],
                'revision_checklist' => $imageFindings['fixes'] ?? [],
                '_provider' => 'heuristic_fallback',
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function visionDescribe(string $absolutePath, string $mime): ?array
    {
        $binary = @file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return null;
        }

        $b64 = base64_encode($binary);
        $providers = AutomationAiProvider::textProviders();

        foreach ($providers as $provider) {
            try {
                if ($provider === 'openai') {
                    return $this->openAiVision($b64, $mime);
                }
                if ($provider === 'gemini') {
                    return $this->geminiVision($b64, $mime);
                }
            } catch (\Throwable $e) {
                AutoReviewLogger::warning("Vision via {$provider} failed", ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function openAiVision(string $base64, string $mime): array
    {
        $apiKey = config('ai.openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiGenerationException('OpenAI API key missing for vision.');
        }

        $response = Http::withToken($apiKey)->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('ai.openai.model', 'gpt-4o-mini'),
            'response_format' => ['type' => 'json_object'],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Assess this social media image for clarity, text readability, layout quality, and growth usefulness. Return JSON: summary (string), score (0-100), issues (string[]), fixes (string[]).',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$mime};base64,{$base64}",
                        ],
                    ],
                ],
            ]],
            'max_tokens' => 800,
        ]);

        if (! $response->successful()) {
            throw new AiGenerationException($response->json('error.message') ?? 'OpenAI vision failed.');
        }

        $data = json_decode((string) $response->json('choices.0.message.content'), true);
        if (! is_array($data)) {
            throw new AiGenerationException('OpenAI vision returned invalid JSON.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function geminiVision(string $base64, string $mime): array
    {
        $apiKey = config('ai.gemini.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiGenerationException('Gemini API key missing for vision.');
        }

        $model = config('ai.gemini.model', 'gemini-2.5-flash');
        $apiVersion = config('ai.gemini.api_version', 'v1beta');
        $url = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent";

        $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout(90)
            ->post($url, [
                'contents' => [[
                    'parts' => [
                        ['text' => 'Assess this social media image for clarity, text readability, layout quality, and growth usefulness. Return JSON: summary (string), score (0-100), issues (string[]), fixes (string[]).'],
                        ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                    ],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.2,
                    'maxOutputTokens' => 800,
                ],
            ]);

        if (! $response->successful()) {
            throw new AiGenerationException($response->json('error.message') ?? 'Gemini vision failed.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($data)) {
            throw new AiGenerationException('Gemini vision returned invalid JSON.');
        }

        return $data;
    }

    /**
     * @param  list<string>  $reasons
     * @param  list<string>  $checklist
     */
    protected function defaultNote(string $decision, array $reasons, array $checklist): string
    {
        $parts = [strtoupper($decision).' by AutoReview Agent.'];
        if ($reasons !== []) {
            $parts[] = 'Reasons: '.implode(' ', array_slice($reasons, 0, 4));
        }
        if ($decision === 'revision' && $checklist !== []) {
            $parts[] = 'Fix: '.implode(' ', array_slice($checklist, 0, 4));
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, int|float>  $scores
     * @param  list<string>  $reasons
     * @param  list<string>  $revisionChecklist
     * @param  list<array<string, mixed>>  $gates
     * @param  array<string, mixed>  $imageFindings
     * @param  array<string, mixed>  $metadataFindings
     * @param  array<string, mixed>|null  $rawAi
     * @return array<string, mixed>
     */
    protected function buildReport(
        string $decision,
        int $overall,
        float $confidence,
        array $scores,
        array $reasons,
        array $revisionChecklist,
        string $adminNote,
        array $gates,
        array $imageFindings,
        array $metadataFindings,
        string $provider,
        ?array $rawAi,
    ): array {
        return [
            'final_decision' => $decision,
            'overall_score' => $overall,
            'confidence' => $confidence,
            'scores' => $scores,
            'reasons' => $reasons,
            'revision_checklist' => $revisionChecklist,
            'admin_note' => $adminNote,
            'deterministic_gates' => $gates,
            'image_findings' => $imageFindings,
            'metadata_findings' => $metadataFindings,
            'provider' => $provider,
            'raw_ai' => $rawAi,
        ];
    }
}
