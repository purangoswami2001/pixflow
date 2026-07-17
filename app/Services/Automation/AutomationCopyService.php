<?php

namespace App\Services\Automation;

use App\Models\ContentCategory;
use App\Models\ContentTopic;
use App\Services\Ai\AiTextService;
use App\Support\AutomationAiProvider;
use App\Support\PlatformPromptGuardrails;

class AutomationCopyService
{
    public function __construct(
        protected PromptTemplateRenderer $renderer,
        protected AiTextService $text
    ) {
    }

    public function generate(
        ContentCategory $category,
        ContentTopic $topic,
        string $platform,
        string $topicType,
        array $idea
    ): array {
        $system = PlatformPromptGuardrails::copySystem($platform, $topicType)
            .(config('automation.safety_enabled', false) ? ' Never make unsafe medical claims.' : '')
            .' Respond with valid JSON only.';

        $keyPoints = is_array($idea['key_points'] ?? null)
            ? implode("\n- ", $idea['key_points'])
            : '';

        $niche = $category->niche_prompt ?: $category->name;
        $audience = $category->audience ?: 'adults interested in this topic';

        $user = $this->renderer->render($platform, 'copy', [
            'niche' => $niche,
            'audience' => $audience,
            'safety_rules' => config('automation.safety_enabled', false) ? ($category->safety_rules ?? '') : ($category->safety_rules ?? ''),
            'topic' => $topic->title,
            'topic_type' => $topicType,
            'platform' => $platform,
            'pin_title' => $idea['pin_title'] ?? $topic->title,
            'angle' => $idea['angle'] ?? '',
            'key_points' => $keyPoints,
            'seo_keywords' => is_array($idea['seo_keywords'] ?? null)
                ? implode(', ', $idea['seo_keywords'])
                : '',
            'link_url' => $topic->metadata['link_url'] ?? '',
            'color_palette' => PlatformPromptGuardrails::colorPalette($niche, $audience),
        ]);
        $user .= "\n\n".PlatformPromptGuardrails::qualityFooter($platform, $topicType);

        $data = $this->text->generateJson($system, $user, AutomationAiProvider::textProviders());

        $hashtags = $data['hashtags'] ?? [];
        if (! is_array($hashtags)) {
            $hashtags = [];
        }

        $hashtags = array_values(array_filter(array_map(function ($tag) {
            $tag = trim((string) $tag);

            return $tag === '' ? null : (str_starts_with($tag, '#') ? $tag : "#{$tag}");
        }, $hashtags)));

        $title = trim((string) ($data['title'] ?? $idea['pin_title'] ?? $topic->title));
        if (mb_strlen($title) > 100) {
            $title = mb_substr($title, 0, 97) . '...';
        }

        $result = [
            'title' => $title,
            'caption' => trim((string) ($data['caption'] ?? '')),
            'hashtags' => $hashtags,
            'link_url' => $data['link_url'] ?? $topic->metadata['link_url'] ?? null,
            'text_provider' => $data['_provider'] ?? null,
        ];

        AutomationGenerationLogger::prompt('copy', $system, $user, $result, $result['text_provider'] ?? AutomationAiProvider::name());

        return $result;
    }
}
