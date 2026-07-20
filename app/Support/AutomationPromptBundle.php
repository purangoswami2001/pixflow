<?php

namespace App\Support;

use App\Models\PromptTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AutomationPromptBundle
{
    /**
     * Full export: schemas + placeholders + all templates (for bulk edit).
     *
     * @return array<string, mixed>
     */
    public static function export(?int $organizationId = null): array
    {
        $organizationId ??= app(OrganizationContext::class)->id();

        $templates = PromptTemplate::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->orderBy('platform')
            ->orderBy('step')
            ->get()
            ->map(fn (PromptTemplate $t) => [
                'id' => $t->id,
                'platform' => $t->platform,
                'step' => $t->step,
                'name' => $t->name,
                'template' => $t->template,
                'is_active' => $t->is_active,
            ])
            ->values()
            ->all();

        return [
            'version' => 1,
            'schemas' => AutomationPromptSchema::steps(),
            'placeholders' => AutomationPromptSchema::placeholders(),
            'image_presets' => AutomationPromptSchema::imagePresets(),
            'default_image_presets' => AutomationPromptSchema::defaultImagePresets(),
            'image_styles' => AutomationPromptSchema::imageStyles(),
            'default_image_styles' => AutomationPromptSchema::defaultImageStyles(),
            'templates' => $templates,
        ];
    }

    public static function exportJson(
        int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ?int $organizationId = null,
    ): string
    {
        return json_encode(self::export($organizationId), $flags) ?: '{}';
    }

    /**
     * Import templates from bulk JSON. Updates existing rows by id or platform+step.
     *
     * @return array{updated: int, errors: array<int, string>}
     */
    public static function import(string $json, ?int $organizationId = null): array
    {
        $organizationId ??= app(OrganizationContext::class)->id();
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'prompt_bundle' => 'Invalid JSON. Paste a valid object with a "templates" array.',
            ]);
        }

        $templates = $decoded['templates'] ?? null;
        if (! is_array($templates) || $templates === []) {
            throw ValidationException::withMessages([
                'prompt_bundle' => 'JSON must include a non-empty "templates" array.',
            ]);
        }

        $updated = 0;
        $errors = [];

        foreach ($templates as $index => $row) {
            if (! is_array($row)) {
                $errors[] = "Row {$index}: must be an object.";

                continue;
            }

            $validator = Validator::make($row, [
                'id' => 'nullable|integer|exists:prompt_templates,id',
                'platform' => 'required_without:id|in:pinterest,facebook,instagram',
                'step' => 'required_without:id|in:idea,copy,image',
                'name' => 'required|string|max:120',
                'template' => 'required|string',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                $errors[] = 'Row '.($index + 1).': '.implode(' ', $validator->errors()->all());

                continue;
            }

            $data = $validator->validated();
            $model = null;

            if (! empty($data['id'])) {
                $model = PromptTemplate::query()
                    ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
                    ->find($data['id']);
            } else {
                $model = PromptTemplate::query()
                    ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
                    ->where('platform', $data['platform'])
                    ->where('step', $data['step'])
                    ->first();
            }

            if (! $model) {
                $errors[] = 'Row '.($index + 1).': template not found (use id or platform+step).';

                continue;
            }

            $model->update([
                'name' => $data['name'],
                'template' => $data['template'],
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $model->is_active,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'errors' => $errors];
    }
}
