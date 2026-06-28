<?php

namespace App\Services\Automation;

use App\Services\Ai\AiTextService;
use App\Support\AutomationAiProvider;
use App\Support\AutomationContentBundle;

class ContentLibraryGeneratorService
{
    public function __construct(
        protected AiTextService $text
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(string $brief): array
    {
        $brief = trim($brief);
        if ($brief === '') {
            throw new \InvalidArgumentException('Describe your category niche before generating.');
        }

        $system = 'You are a social media content strategist for PixFlow automation. '
            . 'Return valid JSON only matching the requested content library bundle schema.';

        $user = AutomationContentBundle::metaPrompt($brief);

        $data = $this->text->generateJson($system, $user, AutomationAiProvider::textProviders());

        unset($data['_provider']);

        if (! isset($data['category'], $data['topics']) || ! is_array($data['category']) || ! is_array($data['topics'])) {
            throw new \RuntimeException('AI response missing category or topics. Paste JSON manually or try again.');
        }

        $data['version'] = 1;

        return $data;
    }
}
