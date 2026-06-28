<?php

namespace App\Services\Automation;

use App\Models\AutomationRunItem;
use Illuminate\Support\Facades\Storage;

class AutomationPipelineState
{
    /** @var list<string> */
    public const STEPS = ['idea', 'copy', 'image_prompt', 'image', 'media', 'post'];

    public static function save(AutomationRunItem $item, string $key, mixed $value): void
    {
        $metadata = $item->metadata ?? [];
        $metadata['pipeline'] = $metadata['pipeline'] ?? [];
        $metadata['pipeline'][$key] = $value;
        $item->update(['metadata' => $metadata]);
        $item->refresh();
    }

    public static function get(AutomationRunItem $item, string $key, mixed $default = null): mixed
    {
        return $item->metadata['pipeline'][$key] ?? $default;
    }

    public static function resumeFrom(AutomationRunItem $item): string
    {
        if ($item->post_id) {
            return 'done';
        }

        $pipeline = $item->metadata['pipeline'] ?? [];

        if (! empty($pipeline['media_asset_id'])) {
            return 'post';
        }

        $imagePath = $pipeline['image']['path'] ?? null;
        if (is_string($imagePath) && $imagePath !== '' && Storage::disk('local')->exists($imagePath)) {
            return 'media';
        }

        $designPrompt = $item->design_prompt ?: ($pipeline['design_prompt'] ?? null);
        if (is_string($designPrompt) && trim($designPrompt) !== '') {
            return 'image';
        }

        if (! empty($pipeline['copy']) && is_array($pipeline['copy'])) {
            return 'image_prompt';
        }

        if (! empty($pipeline['idea']) && is_array($pipeline['idea'])) {
            return 'copy';
        }

        return 'idea';
    }

    public static function hydrateFromGenerationSteps(AutomationRunItem $item): void
    {
        $pipeline = $item->metadata['pipeline'] ?? [];
        $changed = false;

        foreach ($item->metadata['generation_steps'] ?? [] as $log) {
            $step = $log['step'] ?? '';
            $result = $log['result'] ?? null;

            $map = [
                'idea' => 'idea',
                'idea_completed' => 'idea',
                'copy' => 'copy',
                'copy_completed' => 'copy',
                'image_prompt' => 'image_prompt',
                'image_prompt_completed' => 'image_prompt',
            ];

            if (isset($map[$step]) && is_array($result) && empty($pipeline[$map[$step]])) {
                $pipeline[$map[$step]] = $result;
                $changed = true;
            }

            if ($step === 'image_prompt_completed' && ! empty($log['design_prompt_preview']) && empty($pipeline['design_prompt'])) {
                $pipeline['design_prompt'] = $log['design_prompt_preview'];
                $changed = true;
            }

            if ($step === 'image_completed' && ! empty($log['local_path']) && empty($pipeline['image'])) {
                $pipeline['image'] = [
                    'path' => $log['local_path'],
                    'provider' => $log['image_provider'] ?? null,
                ];
                $changed = true;
            }

            if ($step === 'media_asset_created' && ! empty($log['asset_id']) && empty($pipeline['media_asset_id'])) {
                $pipeline['media_asset_id'] = $log['asset_id'];
                $changed = true;
            }
        }

        if ($item->design_prompt && empty($pipeline['design_prompt'])) {
            $pipeline['design_prompt'] = $item->design_prompt;
            $changed = true;
        }

        if ($changed) {
            $metadata = $item->metadata ?? [];
            $metadata['pipeline'] = $pipeline;
            $item->update(['metadata' => $metadata]);
            $item->refresh();
        }
    }

    /**
     * True when $startStep is later than $completedStep (artifact already done).
     */
    public static function shouldReuse(string $completedStep, string $startStep): bool
    {
        $order = array_flip(self::STEPS);

        return ($order[$startStep] ?? 0) > ($order[$completedStep] ?? 0);
    }
}
