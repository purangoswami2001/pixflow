<?php

namespace App\Services\Automation;

use App\Models\ContentCategory;
use App\Models\ContentTopic;
use App\Services\Ai\AiTextService;
use App\Support\AutomationAiProvider;

class AutomationIdeaService
{
    public function __construct(
        protected PromptTemplateRenderer $renderer,
        protected AiTextService $text
    ) {
    }

    public function generate(ContentCategory $category, ContentTopic $topic, string $platform, string $topicType): array
    {
        $system = 'You are an expert social media strategist for health and wellness brands. '
            . 'Respond with valid JSON only.'
            . (config('automation.safety_enabled', false) ? ' Content must be evidence-based and safe.' : '');

        $user = $this->renderer->render($platform, 'idea', [
            'niche' => $category->niche_prompt ?? $category->name,
            'audience' => $category->audience ?? 'women interested in wellness',
            'safety_rules' => config('automation.safety_enabled', false)
                ? ($category->safety_rules ?? 'No cure claims. Use may help language.')
                : '',
            'topic' => $topic->title,
            'topic_type' => $topicType,
            'platform' => $platform,
        ]);

        $data = $this->text->generateJson($system, $user, AutomationAiProvider::textProviders());

        AutomationGenerationLogger::prompt('idea', $system, $user, $data, $data['_provider'] ?? AutomationAiProvider::name());

        return [
            'pin_title' => $data['pin_title'] ?? $data['title'] ?? $topic->title,
            'angle' => $data['angle'] ?? '',
            'key_points' => $data['key_points'] ?? [],
            'seo_keywords' => $data['seo_keywords'] ?? [],
            'safety_notes' => $data['safety_notes'] ?? '',
            'text_provider' => $data['_provider'] ?? null,
        ];
    }
}
