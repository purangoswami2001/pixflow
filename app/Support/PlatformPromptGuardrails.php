<?php

namespace App\Support;

class PlatformPromptGuardrails
{
    public static function platformLabel(string $platform): string
    {
        return match ($platform) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'pinterest' => 'Pinterest',
            default => ucfirst($platform),
        };
    }

    /**
     * Other channel names the model must not inject into output for this platform.
     */
    public static function otherPlatforms(string $platform): string
    {
        $all = ['Facebook', 'Instagram', 'Pinterest', 'TikTok', 'LinkedIn', 'Twitter', 'X', 'YouTube', 'Threads'];
        $current = self::platformLabel($platform);

        return implode(', ', array_values(array_filter(
            $all,
            fn (string $name) => strcasecmp($name, $current) !== 0
        )));
    }

    public static function crossPlatformBan(string $platform): string
    {
        $label = self::platformLabel($platform);
        $others = self::otherPlatforms($platform);

        return "This content is for {$label} ONLY. "
            ."Do not mention, compare to, or style after other networks ({$others}). "
            ."Never write cross-channel phrases like “Pinterest-style” or “Instagram aesthetic” unless naming {$label} itself.";
    }

    public static function toolBrandBan(): string
    {
        return 'Never mention design-tool or template brands (Canva, Photoshop, Figma, Illustrator, Midjourney templates, etc.). Describe layout and visual craft directly.';
    }

    public static function marketingGuard(string $topicType): string
    {
        if ($topicType === 'promotional') {
            return 'Post type is promotional: a clear, honest CTA and offer language are allowed. Still avoid empty hype, fake urgency, and growth-hacking jargon.';
        }

        return 'Post type is NOT promotional: do not use marketing/sales language, CTA funnels, “convert”, “funnel”, “lead magnet”, discounts, or hard-sell tactics. '
            .'Write useful, credible content for the niche. Promo framing only if the niche itself is marketing/advertising.';
    }

    public static function nicheFidelity(): string
    {
        return 'Ground every title, angle, and visual choice in the provided niche and audience. '
            .'Do not assume wellness, fitness, beauty, feminine aesthetics, sage/blush palettes, or medical framing unless the niche/audience clearly implies them.';
    }

    public static function colorPalette(?string $niche, ?string $audience): string
    {
        $blob = mb_strtolower(trim(($niche ?? '').' '.($audience ?? '')));

        if ($blob === '') {
            return 'clean high-contrast palette suited to the niche; colors must serve mobile readability';
        }

        $feminineWellness = str_contains($blob, 'women')
            || str_contains($blob, 'wellness')
            || str_contains($blob, 'self-care')
            || str_contains($blob, 'skincare')
            || str_contains($blob, 'beauty')
            || str_contains($blob, 'menopause');

        if ($feminineWellness) {
            return 'soft sage, blush, lavender, cream — calm feminine wellness-friendly contrast';
        }

        if (str_contains($blob, 'fitness') || str_contains($blob, 'gym') || str_contains($blob, 'performance')) {
            return 'bold high-contrast athletic palette (deep charcoal, white, one strong accent); avoid soft pastel wellness looks';
        }

        if (str_contains($blob, 'tech') || str_contains($blob, 'saas') || str_contains($blob, 'software')) {
            return 'modern product palette (navy/slate, white, one bright accent); crisp and minimal';
        }

        if (str_contains($blob, 'food') || str_contains($blob, 'recipe') || str_contains($blob, 'cooking')) {
            return 'appetizing warm food photography colors (natural greens, terracotta, cream); high contrast for text';
        }

        return 'palette derived from the niche and audience mood — readable on mobile, high contrast for on-image text, no default feminine wellness pastels unless implied by niche';
    }

    public static function titleGuidance(string $platform): string
    {
        return match ($platform) {
            'pinterest' => 'Titles must be search-friendly Pinterest pin titles (clear benefit, specific, under 100 chars, no empty clickbait).',
            'instagram' => 'Titles are short working headlines/hooks suited to Instagram (punchy, concrete, under ~70 chars preferred).',
            'facebook' => 'Titles are clear Facebook-ready headlines that earn a stop-scroll (specific promise, plain language, under 100 chars).',
            default => 'Titles must be clear, specific, and useful — under 100 characters.',
        };
    }

    public static function copyLengthGuidance(string $platform): string
    {
        return match ($platform) {
            'pinterest' => 'Caption/description: 300–600 characters, scannable, helpful; put hashtags ONLY in the hashtags array (never inside caption text).',
            'instagram' => 'Caption: strong hook in the first line; useful body copy; put hashtags ONLY in the hashtags array (never inside caption text).',
            'facebook' => 'Caption: conversational and readable; put hashtags ONLY in the hashtags array (3–6 tags, never inside caption text).',
            default => 'Write platform-native caption length; hashtags only in the hashtags array.',
        };
    }

    public static function ideaSystem(string $platform, string $topicType): string
    {
        $label = self::platformLabel($platform);

        return implode(' ', [
            "You are a senior {$label} content strategist and editorial planner.",
            'Respond with valid JSON only — no markdown fences, no commentary.',
            self::crossPlatformBan($platform),
            self::toolBrandBan(),
            self::marketingGuard($topicType),
            self::nicheFidelity(),
            self::titleGuidance($platform),
            'Produce a concrete publishable angle with specific, actionable key_points (not vague slogans).',
        ]);
    }

    public static function copySystem(string $platform, string $topicType): string
    {
        $label = self::platformLabel($platform);

        return implode(' ', [
            "You are an expert {$label} copywriter.",
            'Respond with valid JSON only — no markdown fences, no commentary.',
            self::crossPlatformBan($platform),
            self::toolBrandBan(),
            self::marketingGuard($topicType),
            self::nicheFidelity(),
            self::titleGuidance($platform),
            self::copyLengthGuidance($platform),
            'Caption must stand alone without repeating hashtags already listed in the hashtags array.',
        ]);
    }

    public static function imageSystem(string $platform, string $imageStyle, string $topicType): string
    {
        $label = self::platformLabel($platform);
        $styleBit = AutomationImageStyles::systemPromptForStyle($imageStyle);

        return implode(' ', [
            $styleBit,
            "Target platform: {$label} only at the requested canvas size.",
            self::crossPlatformBan($platform),
            self::toolBrandBan(),
            self::marketingGuard($topicType),
            self::nicheFidelity(),
            "Follow image style “{$imageStyle}” exactly — do not switch to another layout family.",
            'design_prompt must list exact on-image text strings, layout zones, lighting/mood, and color direction from the niche.',
        ]);
    }

    public static function qualityFooter(string $platform, string $topicType, string $imageStyle = ''): string
    {
        $label = self::platformLabel($platform);
        $others = self::otherPlatforms($platform);
        $styleLine = $imageStyle !== ''
            ? "- Selected visual style: {$imageStyle} — follow only that layout family.\n"
            : '';

        return trim(<<<TXT
QUALITY RULES (mandatory):
- Platform: {$label} only. Do not name or imitate: {$others}.
- Do not mention Canva or other design-tool brands.
- Match niche + audience; do not force wellness/feminine aesthetics unless implied.
- Marketing/sales tone: only if topic_type is promotional (current: {$topicType}).
{$styleLine}- Titles must be specific and useful, not fluff.
TXT);
    }
}
