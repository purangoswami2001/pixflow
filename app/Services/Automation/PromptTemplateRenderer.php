<?php

namespace App\Services\Automation;

use App\Models\PromptTemplate;

class PromptTemplateRenderer
{
    public function render(
        string $platform,
        string $step,
        array $variables,
        ?int $organizationId = null,
    ): string
    {
        $organizationId ??= app(\App\Support\OrganizationContext::class)->id();

        $template = PromptTemplate::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->where('platform', $platform)
            ->where('step', $step)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('version')
            ->first();

        if (! $template) {
            $template = PromptTemplate::query()
                ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
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
