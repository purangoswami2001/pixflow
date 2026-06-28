<?php

namespace App\Services\Automation;

use App\Models\PromptTemplate;

class PromptTemplateRenderer
{
    public function render(string $platform, string $step, array $variables): string
    {
        $template = PromptTemplate::query()
            ->where('platform', $platform)
            ->where('step', $step)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('version')
            ->first();

        if (! $template) {
            $template = PromptTemplate::query()
                ->where('platform', 'all')
                ->where('step', $step)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first();
        }

        if (! $template) {
            throw new \RuntimeException("No prompt template for {$platform}/{$step}.");
        }

        $text = $template->template;
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }

        return $text;
    }
}
