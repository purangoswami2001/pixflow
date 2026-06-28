<?php

namespace App\Services\Automation;

use App\Models\ContentCategory;
use App\Models\ContentTopic;
use App\Services\Ai\AiTextService;
use App\Support\AutomationAiProvider;
use App\Support\AutomationImageStyles;

class AutomationImagePromptService
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
        array $idea,
        array $copy,
        int $width,
        int $height,
        string $imageStyle = 'lifestyle'
    ): array {
        if (! AutomationImageStyles::isValid($imageStyle)) {
            $imageStyle = AutomationImageStyles::defaultForPlatform($platform);
        }

        $system = AutomationImageStyles::systemPromptForStyle($imageStyle);

        $keyPoints = is_array($idea['key_points'] ?? null)
            ? implode("\n", array_slice($idea['key_points'], 0, 5))
            : '';

        $title = $copy['title'] ?? $idea['pin_title'] ?? $topic->title;

        $rendered = trim($this->renderer->render($platform, 'image', [
            'niche' => $category->niche_prompt ?? $category->name,
            'audience' => $category->audience ?? 'women interested in wellness',
            'safety_rules' => config('automation.safety_enabled', false) ? ($category->safety_rules ?? '') : '',
            'topic' => $topic->title,
            'platform' => $platform,
            'pin_title' => $title,
            'caption' => $copy['caption'] ?? '',
            'key_points' => $keyPoints,
            'width' => $width,
            'height' => $height,
            'image_style' => $imageStyle,
            'color_palette' => 'feminine, soft sage, blush pink, lavender, cream',
        ]));

        if ($rendered !== '' && ! self::templateConflictsWithStyle($rendered, $imageStyle)) {
            $user = $rendered."\n\nImage style for this post (follow exactly): ".AutomationImageStyles::promptHint($imageStyle)
                ."\n".AutomationImageStyles::styleLockClause($imageStyle)
                ."\n".AutomationImageStyles::textSafetyClause($width, $height);
        } else {
            $user = SocialImageSafeZone::imageStepUserPrompt(
                $platform,
                $title,
                $topic->title,
                $keyPoints,
                $width,
                $height,
                $imageStyle,
                $category->niche_prompt ?? $category->name,
                $category->audience ?? 'women interested in wellness'
            );
        }

        $data = $this->text->generateJson($system, $user, AutomationAiProvider::textProviders());
        $designPrompt = trim((string) ($data['design_prompt'] ?? ''));

        if ($designPrompt === '') {
            $designPrompt = "Create a {$width}x{$height}px {$platform} "
                . AutomationImageStyles::promptHint($imageStyle)
                . ' Titled "'.($copy['title'] ?? $topic->title).'".';
        }

        $designPrompt = SocialImageSafeZone::appendToDesignPrompt($designPrompt, $platform, $width, $height, $imageStyle);

        $result = [
            'design_prompt' => $designPrompt,
            'image_style' => $imageStyle,
            'text_provider' => $data['_provider'] ?? null,
        ];

        AutomationGenerationLogger::prompt('image_prompt', $system, $user, $result, $result['text_provider'] ?? AutomationAiProvider::name());

        return $result;
    }

    protected static function templateConflictsWithStyle(string $template, string $imageStyle): bool
    {
        $lower = strtolower($template);

        if ($imageStyle === 'infographic') {
            return str_contains($lower, 'lifestyle photo')
                || str_contains($lower, 'minimal on-image text')
                || str_contains($lower, 'at most one short headline');
        }

        if (in_array($imageStyle, ['lifestyle', 'minimal', 'quote', 'tip_card'], true)) {
            return str_contains($lower, 'informational infographic')
                || str_contains($lower, 'educational infographic')
                || str_contains($lower, '5–6 content sections')
                || str_contains($lower, '3–5 icon sections')
                || str_contains($lower, 'icon+text sections')
                || str_contains($lower, 'scannable icon sections')
                || str_contains($lower, 'icon sections stacked')
                || str_contains($lower, 'split layout: left lifestyle');
        }

        return false;
    }
}
