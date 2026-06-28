<?php

namespace App\Support;

use App\Models\ContentCategory;
use App\Models\ContentTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutomationContentBundle
{
    public static function metaPrompt(string $brief = ''): string
    {
        $template = json_encode(self::template($brief), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $briefBlock = "\n## User category brief\n"
            . (trim($brief) !== '' ? trim($brief) : '(describe your niche — audience, tone, platform focus)')
            . "\n";

        return <<<PROMPT
You are configuring PixFlow, a Laravel social automation app. Generate a **content library JSON bundle**: one category (niche config) plus **7–10 topic headlines** with details for AI post generation.

{$briefBlock}
## Category fields (injected into every automation prompt)
- **name**: short label for admin UI (e.g. "Health & Wellness")
- **niche_prompt**: 1–2 sentences describing the niche, tone, and content style for AI
- **safety_rules**: hard limits (no cure claims, evidence-based language, etc.) — or empty string
- **audience**: who the content is for (demographics + interests)
- **is_active**: true

## Topic fields (headlines the AI picks per post)
- **title**: scroll-stopping hook headline (max 500 chars) — question or bold promise style
- **topic_type**: one of educational, listicle, tip, promotional
- **summary**: 1 sentence explaining the post angle
- **angle**: how to approach the post for engagement
- **key_points**: array of 3–5 short strings (supporting ideas)
- **link_url**: optional URL for promotional topics only
- **is_active**: true

Include **at least 7 and at most 10** topics. Mix mostly educational/listicle/tip; at most 1 promotional topic.

## Required output — JSON only (no markdown fences)
{
  "version": 1,
  "category": { "name", "niche_prompt", "safety_rules", "audience", "is_active" },
  "topics": [ { "title", "topic_type", "summary", "angle", "key_points", "link_url", "is_active" }, ... ]
}

## Reference template (replace with your generated content)
{$template}

Respond with **valid JSON only**.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public static function template(string $brief = ''): array
    {
        $name = 'Health & Wellness';
        if (trim($brief) !== '') {
            $name = Str::limit(trim($brief), 80, '');
        }

        return [
            'version' => 1,
            'category' => [
                'name' => $name,
                'niche_prompt' => 'Evidence-based wellness tips for women. Feminine aesthetic, actionable advice, Pinterest-friendly hooks.',
                'safety_rules' => 'No cure claims or misleading medical advice. Use may-help language. Encourage consulting healthcare professionals.',
                'audience' => 'Women interested in health, wellness, stress relief, and self-care',
                'is_active' => true,
            ],
            'topics' => [
                [
                    'title' => 'Menopause Hot Flashes Driving You Crazy? The Secret Hack You Haven\'t Tried to Cool Them Instantly!',
                    'topic_type' => 'educational',
                    'summary' => 'Practical cooling strategies for menopause hot flashes.',
                    'angle' => 'Empathetic, evidence-based tips women can try today.',
                    'key_points' => ['Layer breathable fabrics', 'Cool-down breathing', 'Track triggers in a journal'],
                    'link_url' => null,
                    'is_active' => true,
                ],
            ],
        ];
    }

    public static function exportJson(?string $brief = null, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return json_encode(self::template($brief ?? ''), $flags) ?: '{}';
    }

    /**
     * @return array{category: ContentCategory, topics_created: int}
     */
    public static function import(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'content_bundle' => 'Invalid JSON. Paste a valid object with "category" and "topics".',
            ]);
        }

        return self::importArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{category: ContentCategory, topics_created: int}
     */
    public static function importArray(array $decoded): array
    {
        $categoryData = $decoded['category'] ?? null;
        $topics = $decoded['topics'] ?? null;

        if (! is_array($categoryData) || ! is_array($topics) || $topics === []) {
            throw ValidationException::withMessages([
                'content_bundle' => 'JSON must include a "category" object and a non-empty "topics" array.',
            ]);
        }

        $name = trim((string) ($categoryData['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'content_bundle' => 'Category "name" is required.',
            ]);
        }

        return DB::transaction(function () use ($categoryData, $topics, $name) {
            $category = ContentCategory::create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::random(4),
                'niche_prompt' => trim((string) ($categoryData['niche_prompt'] ?? '')) ?: null,
                'safety_rules' => trim((string) ($categoryData['safety_rules'] ?? '')) ?: null,
                'audience' => trim((string) ($categoryData['audience'] ?? '')) ?: null,
                'is_active' => (bool) ($categoryData['is_active'] ?? true),
                'sort_order' => (int) ContentCategory::max('sort_order') + 1,
            ]);

            $created = 0;

            foreach ($topics as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $topicType = strtolower(trim((string) ($row['topic_type'] ?? 'educational')));
                if (! in_array($topicType, ['educational', 'promotional', 'listicle', 'tip'], true)) {
                    $topicType = 'educational';
                }

                $metadata = array_filter([
                    'link_url' => self::nullableString($row['link_url'] ?? null),
                    'summary' => self::nullableString($row['summary'] ?? null),
                    'angle' => self::nullableString($row['angle'] ?? null),
                    'key_points' => self::normalizeKeyPoints($row['key_points'] ?? null),
                ], fn ($value) => $value !== null && $value !== [] && $value !== '');

                ContentTopic::create([
                    'content_category_id' => $category->id,
                    'title' => $title,
                    'topic_type' => $topicType,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'metadata' => $metadata ?: null,
                ]);

                $created++;
            }

            if ($created === 0) {
                throw ValidationException::withMessages([
                    'content_bundle' => 'No valid topics found. Each topic needs a "title".',
                ]);
            }

            return ['category' => $category, 'topics_created' => $created];
        });
    }

    /**
     * @return list<string>
     */
    protected static function normalizeKeyPoints(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($point) => is_string($point) ? trim($point) : '',
            $value
        )));
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
