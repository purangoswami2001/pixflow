<?php

namespace App\Support;

use App\Models\AutomationRunItem;

class AutomationRunItemPresenter
{
    /**
     * @param  array<int, array{key: string, label: string, icon: string}>  $pipelineSteps
     * @return array<string, mixed>
     */
    public static function toFeedArray(AutomationRunItem $item, array $pipelineSteps): array
    {
        $pipeline = $item->metadata['pipeline'] ?? [];
        $steps = [];

        foreach ($pipelineSteps as $step) {
            $key = $step['key'];
            $steps[$key] = match ($key) {
                'idea' => ! empty($pipeline['idea']),
                'copy' => ! empty($pipeline['copy']),
                'image_prompt' => ! empty($pipeline['image_prompt']),
                'design_prompt' => ! empty($pipeline['design_prompt']) || ! empty($item->design_prompt),
                'image' => ! empty($pipeline['image']),
                'media_asset_id' => ! empty($pipeline['media_asset_id']),
                default => false,
            };
        }

        $generationSteps = $item->metadata['generation_steps'] ?? [];

        return [
            'id' => $item->id,
            'platform' => $item->platform,
            'topic_type' => $item->topic_type,
            'status' => $item->status,
            'status_label' => str_replace('_', ' ', $item->status),
            'topic_title' => $item->topic?->title ?? '—',
            'error_message' => $item->error_message,
            'post_id' => $item->post_id,
            'pipeline_steps' => $steps,
            'active_step' => self::activeStep($item),
            'generation_steps_count' => is_array($generationSteps) ? count($generationSteps) : 0,
            'has_pipeline_artifacts' => ! empty($pipeline),
            'revision' => self::revision($item),
        ];
    }

    public static function revision(AutomationRunItem $item): string
    {
        return md5(json_encode([
            $item->status,
            $item->error_message,
            $item->post_id,
            $item->metadata['pipeline'] ?? null,
            $item->metadata['generation_steps'] ?? null,
        ]));
    }

    public static function activeStep(AutomationRunItem $item): ?string
    {
        return match ($item->status) {
            'generating_idea' => 'idea',
            'generating_copy' => 'copy',
            'generating_image_prompt' => 'image_prompt',
            'generating_image' => 'image',
            default => null,
        };
    }

    public static function isTerminalStatus(string $status): bool
    {
        return in_array($status, ['post_created', 'failed'], true);
    }
}
