<?php

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

class AutomationSeeder extends Seeder
{
    public function run(?int $organizationId = null): void
    {
        $organizationId ??= \App\Models\Organization::query()->orderBy('id')->value('id');

        if ($organizationId) {
            $this->seedPrompts((int) $organizationId);
        }
    }

    public function seedPrompts(int $organizationId): void
    {
        $templates = [
            ['pinterest', 'idea', 'Pinterest Pin Idea Planner', $this->ideaPrompt('pinterest')],
            ['facebook', 'idea', 'Facebook Post Idea', $this->ideaPrompt('facebook')],
            ['instagram', 'idea', 'Instagram Post Idea', $this->ideaPrompt('instagram')],
            ['pinterest', 'copy', 'Pinterest Pin Copy', $this->copyPrompt('pinterest')],
            ['facebook', 'copy', 'Facebook Post Copy', $this->copyPrompt('facebook')],
            ['instagram', 'copy', 'Instagram Caption', $this->copyPrompt('instagram')],
            ['pinterest', 'image', 'Pinterest Infographic Image', $this->imagePrompt('pinterest')],
            ['facebook', 'image', 'Facebook Image', $this->imagePrompt('facebook')],
            ['instagram', 'image', 'Instagram Image', $this->imagePrompt('instagram')],
        ];

        foreach ($templates as [$platform, $step, $name, $template]) {
            PromptTemplate::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'platform' => $platform,
                    'step' => $step,
                    'name' => $name,
                ],
                ['template' => $template, 'is_default' => true, 'is_active' => true, 'version' => 2]
            );
        }
    }

    protected function ideaPrompt(string $platform): string
    {
        $label = ucfirst($platform);
        $titleHint = match ($platform) {
            'pinterest' => 'pin_title: search-friendly Pinterest title with a clear benefit (max 100 chars)',
            'instagram' => 'pin_title: short Instagram hook/headline (concrete, preferably under 70 chars; field max 100)',
            default => 'pin_title: clear Facebook-ready headline (specific promise, max 100 chars)',
        };

        return <<<PROMPT
Plan a high-quality {$label}-native content idea for this exact niche and audience.

Platform: {$label} ONLY — do not mention or imitate other social networks.
Do not call yourself a “digital marketer” unless topic_type is promotional.
Do not force wellness/feminine framing unless niche/audience implies it.
Never mention Canva or other design-tool brands.

Niche: {{niche}}
Audience: {{audience}}
Safety: {{safety_rules}}
Topic headline: {{topic}}
Post type: {{topic_type}}

Requirements:
- angle: one concrete editorial angle (who it helps, what tension it resolves, why it works on {$label})
- key_points: 3–5 specific, actionable points (not vague slogans)
- seo_keywords: niche-relevant discovery terms for {$label}
- {$titleHint}

Respond JSON only with keys:
- pin_title (string, max 100 chars)
- angle (string)
- key_points (array of 3-5 short strings)
- seo_keywords (array)
- safety_notes (string)
PROMPT;
    }

    protected function copyPrompt(string $platform): string
    {
        $label = ucfirst($platform);
        $length = match ($platform) {
            'pinterest' => 'caption 300-600 characters; helpful and scannable',
            'instagram' => 'caption with a strong first-line hook; useful body; platform-native Instagram voice',
            default => 'caption conversational and readable for Facebook; concrete value first',
        };

        return <<<PROMPT
Write native {$label} copy for this idea. Stay on {$label} only — never reference other social platforms.
Do not use marketing/sales language unless topic_type is promotional.
Never mention Canva or design-tool brands.
Hashtags go ONLY in the hashtags array — do not paste hashtags into the caption text.

Niche: {{niche}}
Audience: {{audience}}
Safety: {{safety_rules}}
Topic: {{topic}}
Title idea: {{pin_title}}
Angle: {{angle}}
Key points:
{{key_points}}
SEO keywords: {{seo_keywords}}
Type: {{topic_type}}
Promo link: {{link_url}}

Title rules: specific, benefit-led, under 100 characters, suited to {$label}.
Caption rules: {$length}. Match niche + audience voice.

Respond JSON only with keys:
title, caption, hashtags (array of 4-8 niche-relevant tags), link_url (optional; only when promotional and a link exists)
PROMPT;
    }

    protected function imagePrompt(string $platform): string
    {
        $label = ucfirst($platform);

        return <<<PROMPT
Write a detailed design_prompt for an AI image model for a {$label} post ONLY.
Canvas: {{width}}x{{height}}px
Platform: {$label} — do not mention or imitate other networks.
Selected visual style: {{image_style}} — follow this style only; do not switch to another layout family.
Never mention Canva, Photoshop, Figma, or other design-tool brands.

Title for on-image text: {{pin_title}}
Topic: {{topic}}
Niche: {{niche}}
Audience: {{audience}}
Caption context (mood only; do not dump full caption on image): {{caption}}
Key points:
{{key_points}}
Color direction: {{color_palette}}

Style application:
- If {{image_style}} is infographic: focal visual panel + 3–5 sections (icon + short heading + one-line body); spell exact on-image text.
- If {{image_style}} is lifestyle: photorealistic niche-relevant scene; at most one short headline (under 8 words).
- If {{image_style}} is minimal: generous negative space; one short headline only.
- If {{image_style}} is quote: centered quote card; one hero line from the title/angle.
- If {{image_style}} is tip_card: one icon, one headline, one supporting line.

Universal requirements:
- Full-bleed {{width}}x{{height}}px composition for {$label}
- Colors and mood must fit niche/audience (no default feminine wellness pastels unless niche implies them)
- Keep all on-image text inside safe margins; never clip words
- design_prompt should be 2–5 vivid sentences an image model can execute

Respond JSON only: design_prompt (string)
PROMPT;
    }
}
