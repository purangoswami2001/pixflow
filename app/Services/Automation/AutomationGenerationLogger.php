<?php

namespace App\Services\Automation;

use App\Models\AutomationRunItem;
use Illuminate\Support\Facades\Log;

class AutomationGenerationLogger
{
    protected static ?AutomationRunItem $item = null;

    public static function bind(?AutomationRunItem $item): void
    {
        static::$item = $item;
    }

    public static function clear(): void
    {
        static::$item = null;
    }

    public static function step(string $step, array $context = []): void
    {
        $payload = array_merge([
            'item_id' => static::$item?->id,
            'run_id' => static::$item?->automation_run_id,
            'platform' => static::$item?->platform,
        ], $context);

        Log::channel('automation')->info($step, $payload);

        if (! static::$item) {
            return;
        }

        $metadata = static::$item->metadata ?? [];
        $steps = $metadata['generation_steps'] ?? [];
        $steps[] = array_merge([
            'step' => $step,
            'at' => now()->toIso8601String(),
        ], self::summarizeForMetadata($context));

        $metadata['generation_steps'] = $steps;

        $result = $context['result'] ?? null;
        $metadata['pipeline'] = $metadata['pipeline'] ?? [];

        $pipelineKey = match ($step) {
            'idea', 'idea_completed' => is_array($result) ? 'idea' : null,
            'copy', 'copy_completed' => is_array($result) ? 'copy' : null,
            'image_prompt', 'image_prompt_completed' => is_array($result) ? 'image_prompt' : null,
            default => null,
        };

        if ($pipelineKey !== null) {
            $metadata['pipeline'][$pipelineKey] = $result;
        }

        if (isset($context['design_prompt']) && is_string($context['design_prompt'])) {
            $metadata['pipeline']['design_prompt'] = $context['design_prompt'];
        }

        if ($step === 'image_completed' && isset($context['local_path'])) {
            $metadata['pipeline']['image'] = [
                'path' => $context['local_path'],
                'provider' => $context['image_provider'] ?? null,
            ];
        }

        if ($step === 'media_asset_created' && isset($context['asset_id'])) {
            $metadata['pipeline']['media_asset_id'] = $context['asset_id'];
        }

        static::$item->update(['metadata' => $metadata]);
        static::$item->refresh();
    }

    public static function prompt(string $step, string $systemPrompt, string $userPrompt, ?array $result = null, ?string $provider = null): void
    {
        $context = [
            'provider' => $provider,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
        ];

        if ($result !== null) {
            $context['result'] = $result;
        }

        self::step($step, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected static function summarizeForMetadata(array $context): array
    {
        $summary = [];

        foreach (['provider', 'matched_phrase', 'image_provider', 'asset_id', 'post_id', 'duration_ms', 'resume_from', 'resuming', 'local_path'] as $key) {
            if (array_key_exists($key, $context)) {
                $summary[$key] = $context[$key];
            }
        }

        if (isset($context['result']) && is_array($context['result'])) {
            $summary['result_keys'] = array_keys($context['result']);
            if (isset($context['result']['title'])) {
                $summary['title'] = $context['result']['title'];
            }
            if (isset($context['result']['caption'])) {
                $summary['caption_preview'] = mb_substr((string) $context['result']['caption'], 0, 200);
            }
            if (isset($context['result']['design_prompt'])) {
                $summary['design_prompt_preview'] = mb_substr((string) $context['result']['design_prompt'], 0, 300);
            }
        }

        if (isset($context['design_prompt'])) {
            $summary['design_prompt_preview'] = mb_substr((string) $context['design_prompt'], 0, 300);
        }

        if (isset($context['user_prompt'])) {
            $summary['user_prompt_preview'] = mb_substr((string) $context['user_prompt'], 0, 500);
            $summary['user_prompt'] = (string) $context['user_prompt'];
        }

        if (isset($context['system_prompt'])) {
            $summary['system_prompt_preview'] = mb_substr((string) $context['system_prompt'], 0, 500);
            $summary['system_prompt'] = (string) $context['system_prompt'];
        }

        if (isset($context['result']) && is_array($context['result'])) {
            $summary['result_json'] = $context['result'];
        }

        return $summary;
    }
}
