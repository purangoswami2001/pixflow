<?php

namespace App\Support;

class AutomationPromptSchema
{
    /**
     * Expected JSON output per pipeline step (used in Prompt Studio UI).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function steps(): array
    {
        return [
            'idea' => [
                'label' => 'Idea planner',
                'description' => 'Expands a topic headline into a concrete post angle before copy is written.',
                'keys' => [
                    'pin_title' => ['type' => 'string', 'max' => 100, 'required' => true],
                    'angle' => ['type' => 'string', 'required' => true],
                    'key_points' => ['type' => 'array', 'items' => 'string', 'min' => 3, 'max' => 5],
                    'seo_keywords' => ['type' => 'array', 'items' => 'string'],
                    'safety_notes' => ['type' => 'string'],
                ],
            ],
            'copy' => [
                'label' => 'Caption & title',
                'description' => 'Writes platform-specific title, caption, hashtags, and optional promo link.',
                'keys' => [
                    'title' => ['type' => 'string', 'required' => true],
                    'caption' => ['type' => 'string', 'required' => true],
                    'hashtags' => ['type' => 'array', 'items' => 'string'],
                    'link_url' => ['type' => 'string', 'format' => 'url', 'required' => false],
                ],
            ],
            'image' => [
                'label' => 'Image prompt',
                'description' => 'Produces a design_prompt string sent to the image model.',
                'keys' => [
                    'design_prompt' => ['type' => 'string', 'required' => true],
                ],
            ],
        ];
    }

    /**
     * Template placeholders available in all prompt steps.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        return [
            'topic' => 'Selected topic headline',
            'topic_type' => 'educational, promotional, listicle, or tip',
            'niche' => 'Category niche description',
            'audience' => 'Target audience from category',
            'safety_rules' => 'Category safety rules',
            'platform' => 'pinterest, facebook, or instagram',
            'width' => 'Image width in pixels (image step only)',
            'height' => 'Image height in pixels (image step only)',
            'image_style' => 'infographic, lifestyle, minimal, quote, or tip_card (image step only)',
            'pin_title' => 'From idea step (copy & image)',
            'angle' => 'From idea step (copy)',
            'key_points' => 'From idea step (copy & image)',
            'link_url' => 'Promo URL from topic metadata',
            'color_palette' => 'Brand colors injected at runtime',
        ];
    }

    /**
     * Available image size presets per platform (campaign + pipeline).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function imagePresets(): array
    {
        return AutomationCampaignPresets::optionsByPlatform();
    }

    /**
     * Default image preset key per platform when a campaign does not override.
     *
     * @return array<string, string>
     */
    public static function defaultImagePresets(): array
    {
        return [
            'pinterest' => AutomationCampaignPresets::defaultForPlatform('pinterest'),
            'facebook' => AutomationCampaignPresets::defaultForPlatform('facebook'),
            'instagram' => AutomationCampaignPresets::defaultForPlatform('instagram'),
        ];
    }

    /**
     * Image style templates (campaign selects one per platform; drives {{image_style}}).
     *
     * @return array<string, array{key: string, label: string, description: string, layout_rules: string}>
     */
    public static function imageStyles(): array
    {
        $styles = [];
        foreach (AutomationImageStyles::options() as $key => $option) {
            $styles[$key] = [
                'key' => $key,
                'label' => $option['label'],
                'description' => $option['description'],
                'layout_rules' => $option['prompt_hint'],
            ];
        }

        return $styles;
    }

    /**
     * Default image style key per platform when a campaign does not override.
     *
     * @return array<string, string>
     */
    public static function defaultImageStyles(): array
    {
        return [
            'pinterest' => AutomationImageStyles::defaultForPlatform('pinterest'),
            'facebook' => AutomationImageStyles::defaultForPlatform('facebook'),
            'instagram' => AutomationImageStyles::defaultForPlatform('instagram'),
        ];
    }

    /**
     * Meta-prompt: paste into Cursor/ChatGPT to generate the full Prompt Studio bulk JSON.
     */
    public static function metaPrompt(): string
    {
        $bundle = [
            'version' => 1,
            'schemas' => self::steps(),
            'placeholders' => self::placeholders(),
            'image_presets' => self::imagePresets(),
            'default_image_presets' => self::defaultImagePresets(),
            'image_styles' => self::imageStyles(),
            'default_image_styles' => self::defaultImageStyles(),
            'templates' => '[GENERATE 9 ITEMS — see rules below]',
        ];

        $bundleExample = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $schemas = json_encode(self::steps(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $placeholders = json_encode(self::placeholders(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $imagePresets = json_encode(self::imagePresets(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $presetDefaults = json_encode(self::defaultImagePresets(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $imageStyles = json_encode(self::imageStyles(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $styleDefaults = json_encode(self::defaultImageStyles(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are configuring PixFlow, a Laravel social automation system. Generate the **complete Prompt Studio bulk JSON** used to import all AI prompt templates at once.

## Pipeline (per post)
1. **idea** — expand topic headline → JSON angle & key points
2. **copy** — write title, caption, hashtags → JSON
3. **image** — build design_prompt for image AI → JSON (uses {{width}} × {{height}} from campaign image preset AND {{image_style}} from campaign image template)

Platforms: pinterest, facebook, instagram (3 steps each = **9 templates**).

## Placeholders (use in template text)
{$placeholders}

### How placeholders are filled at runtime
- **Category**: {{niche}}, {{audience}}, {{safety_rules}} from the content category
- **Topic**: {{topic}}, {{topic_type}}, {{link_url}} from the selected topic
- **Campaign image preset** (per platform): {{width}}, {{height}} — e.g. Pinterest 1000×1500, Facebook 1200×630, Instagram 1080×1350
- **Campaign image template** (per platform): {{image_style}} — one of: infographic, lifestyle, minimal, quote, tip_card
- **Prior steps**: {{pin_title}}, {{angle}}, {{key_points}} from idea; copy title flows into image as {{pin_title}}
- **Brand**: {{color_palette}} injected at runtime

**Critical:** Image step templates MUST read {{image_style}} and adapt layout instructions to that style. Do NOT hardcode infographic for every platform.

## JSON schemas — AI must return ONLY these keys per step
{$schemas}

## Image presets (campaign selects one per platform → {{width}} / {{height}})
{$imagePresets}

Default preset keys if campaign does not override:
{$presetDefaults}

## Image styles / templates (campaign selects one per platform → {{image_style}})
Each campaign channel picks a visual template in the UI (infographic, quote card, minimal, etc.). At runtime PixFlow replaces {{image_style}} with the chosen key and builds the image prompt accordingly.

{$imageStyles}

Default style keys if campaign does not override:
{$styleDefaults}

## Image step rules — MUST be dynamic (use {{image_style}})

Every **image** template string you write must include this block (adapt wording per platform):

---
**Selected image template:** {{image_style}}
**Canvas:** {{width}}px wide × {{height}}px tall
**Platform:** {{platform}}

Follow the layout for {{image_style}} ONLY. Do not mix styles.

**When {{image_style}} is infographic:**
- Educational Canva-style layout: lifestyle photo panel + 3–5 scannable sections (line icon + bold heading + one-line body)
- Pinterest portrait: stacked sections, title header, optional footer CTA
- Facebook landscape (width > height): split-panel — visual left (~40%), icon sections right
- Instagram portrait/square: stacked sections with clear hierarchy
- Spell exact on-image headlines; keep section headings ≤4 words, body ≤14 words

**When {{image_style}} is quote:**
- Centered quote card: ONE main quote line (use {{pin_title}}), optional 1–2 short supporting lines max
- Serif or script headline, subtle botanical accents, soft {{color_palette}}
- FORBIDDEN: icon rows, numbered lists, infographic panels, multi-section tip blocks

**When {{image_style}} is minimal:**
- Clean flat or lifestyle composition, generous negative space
- At most ONE short headline on image (under 8 words) — use a shortened hook from {{pin_title}}
- Soft {{color_palette}}; FORBIDDEN: dark gym posters, halftone textures, icon grids, dense body text

**When {{image_style}} is lifestyle:**
- Photorealistic hero image relevant to {{topic}} and {{niche}}
- At most one short headline overlay (under 8 words)
- FORBIDDEN: icon sections, bullet lists, infographic panels

**When {{image_style}} is tip_card:**
- Single centered card: one icon, one headline, one supporting line
- FORBIDDEN: multi-section lists, infographic panels

**All styles — universal rules:**
- Full-bleed design filling the entire {{width}}×{{height}}px canvas (background and color blocks edge-to-edge)
- TEXT SAFETY: keep every character of on-image text inside ~12% safe margins from all edges; never clip or truncate words — shorten copy or reduce font size instead
- Write design_prompt as 2–4 sentences the image model can execute directly; do not put pixel dimensions inside design_prompt text (runtime appends technical requirements separately)
- Output JSON key: design_prompt only

---

Idea and copy templates do NOT need {{image_style}}. Only the three **image** step templates need it.

## Required output — match PixFlow bulk import format exactly
Return a single JSON object (no markdown fences) with this structure:

{
  "version": 1,
  "schemas": { ...same as schemas above... },
  "placeholders": { ...same as placeholders above... },
  "image_presets": { ...same as image_presets above... },
  "default_image_presets": { ...same as defaults above... },
  "image_styles": { ...same as image_styles above... },
  "default_image_styles": { ...same as style defaults above... },
  "templates": [ ...9 items... ]
}

### templates array rules
- Include exactly **9 objects**: 3 platforms × 3 steps (idea, copy, image)
- Each `template` string must instruct **valid JSON only** (no markdown) with the exact keys from schemas for that step
- **idea/copy**: reference {{niche}}, {{audience}}, {{safety_rules}}, {{topic}}, {{topic_type}} where relevant
- **copy**: may use {{pin_title}}, {{angle}}, {{key_points}}, {{link_url}} from prior steps
- **image**: MUST reference {{width}}, {{height}}, {{image_style}}, {{platform}}, {{pin_title}}, {{key_points}}, {{color_palette}} and include the dynamic style rules block above
- Image templates must NOT assume a fixed style — they must branch on {{image_style}}
- Set `is_active`: true for all; `id` may be null (import matches by platform + step)

### Per-platform tone (idea + copy only)
- **pinterest**: save-worthy, SEO-friendly pin title, educational depth
- **facebook**: conversational, debate-friendly, shorter caption
- **instagram**: bold hook, line breaks, emoji sparingly, save/share CTA

## Reference bundle shape (replace templates with your generated content)
{$bundleExample}

Generate production-ready templates for a fitness/wellness brand targeting young men (gym, performance, recovery). No [INSERT NICHE] placeholders. Respond with **JSON only**.
PROMPT;
    }

    /**
     * @return array<int, array{platform: string, step: string, label: string}>
     */
    public static function pipelineSteps(): array
    {
        return [
            ['key' => 'idea', 'label' => 'Idea', 'icon' => 'lightbulb'],
            ['key' => 'copy', 'label' => 'Copy', 'icon' => 'edit_note'],
            ['key' => 'image_prompt', 'label' => 'Image prompt', 'icon' => 'palette'],
            ['key' => 'design_prompt', 'label' => 'Design', 'icon' => 'brush'],
            ['key' => 'image', 'label' => 'Image', 'icon' => 'image'],
            ['key' => 'media_asset_id', 'label' => 'Media', 'icon' => 'perm_media'],
        ];
    }
}
