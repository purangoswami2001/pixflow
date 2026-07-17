<?php

namespace App\Support;

use App\Models\AutomationCampaign;

class AutomationImageStyles
{
    /**
     * @return array<string, array{label: string, description: string, prompt_hint: string, preview: string}>
     */
    public static function options(): array
    {
        return [
            'infographic' => [
                'label' => 'Educational infographic',
                'description' => 'Photo or graphic panel plus 3–5 icon sections with short on-image headings.',
                'prompt_hint' => 'Professional educational infographic: clear hierarchy with a focal visual panel and 3–5 scannable sections (simple line icon + bold heading + one-line body). Mixed readable typography. Full-bleed composition. Colors must fit the given niche/audience — not a generic pastel wellness look unless the niche requires it.',
                'preview' => 'images/automation/styles/infographic.svg',
            ],
            'lifestyle' => [
                'label' => 'Lifestyle photo',
                'description' => 'Realistic photography with at most one short headline.',
                'prompt_hint' => 'High-quality photorealistic lifestyle image matching the niche and audience. Soft natural or scene-appropriate light. At most one short headline overlay (under 8 words). No icon grids or tip lists.',
                'preview' => 'images/automation/styles/lifestyle.svg',
            ],
            'minimal' => [
                'label' => 'Minimal graphic',
                'description' => 'Clean flat design, bold headline, little body text.',
                'prompt_hint' => 'Minimal modern graphic: generous negative space, one short headline only (under 8 words), palette suited to the niche. No icon grids, no aggressive gym-poster look, no multi-line body text.',
                'preview' => 'images/automation/styles/minimal.svg',
            ],
            'quote' => [
                'label' => 'Quote card',
                'description' => 'Single strong quote on a styled background.',
                'prompt_hint' => 'Centered quote card: one main quote line in serif or clean display type on a styled background with subtle relevant accents. No icon rows, numbered sections, or infographic panels.',
                'preview' => 'images/automation/styles/quote.svg',
            ],
            'tip_card' => [
                'label' => 'Quick tip card',
                'description' => 'Single tip with icon — fast to read in feed.',
                'prompt_hint' => 'Single-tip social card: one icon, one headline, one supporting line. Centered, mobile-readable, niche-appropriate colors.',
                'preview' => 'images/automation/styles/tip_card.svg',
            ],
        ];
    }

    public static function previewUrl(string $style): string
    {
        $path = self::options()[$style]['preview'] ?? self::options()['infographic']['preview'];

        return asset($path);
    }

    public static function defaultForPlatform(string $platform): string
    {
        return (string) config("automation.default_image_styles.{$platform}", 'infographic');
    }

    public static function resolveStyleKey(AutomationCampaign $campaign, string $platform): string
    {
        $styles = $campaign->platform_image_styles ?? [];
        if (! is_array($styles)) {
            $styles = [];
        }

        $key = $styles[$platform] ?? null;
        if (is_string($key) && $key !== '' && isset(self::options()[$key])) {
            return $key;
        }

        return self::defaultForPlatform($platform);
    }

    public static function isValid(string $style): bool
    {
        return isset(self::options()[$style]);
    }

    /**
     * @return array<string, string>
     */
    public static function normalizeInput(?array $input, array $selectedPlatforms): array
    {
        $input = $input ?? [];
        $normalized = [];

        foreach ($selectedPlatforms as $platform) {
            $key = $input[$platform] ?? self::defaultForPlatform($platform);
            $normalized[$platform] = is_string($key) && self::isValid($key)
                ? $key
                : self::defaultForPlatform($platform);
        }

        return $normalized;
    }

    public static function promptHint(string $style): string
    {
        return self::options()[$style]['prompt_hint'] ?? self::options()['infographic']['prompt_hint'];
    }

    public static function systemPromptForStyle(string $style): string
    {
        return match ($style) {
            'infographic' => 'You are an expert visual designer for social educational infographics. '
                . 'Return JSON with design_prompt (string). The prompt must be detailed for an AI image model: layout zones, exact on-image headlines, 3–5 icon sections with heading+body text, color direction from the niche, and an optional focal photo/graphic panel. '
                . 'Keep headlines short enough to fit without clipping. Spell all on-image text exactly. No markdown. Never mention Canva or other tool brands.',
            'quote' => 'You are an expert social quote-card designer. Return JSON with design_prompt (string) for an image model. '
                . 'Design MUST be a centered quote card with one hero quote line and optional subtle relevant accents. '
                . 'FORBIDDEN: infographic layouts, icon bullet rows, numbered sections, photo panels with tip lists. Never mention Canva or other tool brands.',
            'tip_card' => 'You are an expert social designer for quick-tip cards. Return JSON with design_prompt (string) for an image model. '
                . 'Exactly one icon, one headline, one supporting line — centered layout. No multi-section lists. Never mention Canva or other tool brands.',
            'minimal' => 'You are an expert minimalist social graphic designer. Return JSON with design_prompt (string) for an image model. '
                . 'Design MUST leave generous negative space with at most ONE short headline (under 8 words). Colors must fit the niche. '
                . 'FORBIDDEN: dark aggressive posters, icon grids, multiple text blocks, dense body copy. Never mention Canva or other tool brands.',
            default => 'You are an expert social visual director for lifestyle/scene photography matching the niche. Return JSON with design_prompt (string) for an image model. '
                . 'Visual-first, photorealistic when appropriate. At most one short headline overlay. Never mention Canva or other tool brands.',
        };
    }

    public static function safeMargins(int $width, int $height): array
    {
        return [
            'x' => max(48, (int) round($width * 0.12)),
            'y' => max(48, (int) round($height * 0.12)),
        ];
    }

    public static function textSafetyClause(int $width, int $height): string
    {
        $m = self::safeMargins($width, $height);

        return "TEXT SAFETY (mandatory): Every character of on-image text must be fully visible inside the canvas with at least {$m['x']}px horizontal and {$m['y']}px vertical inset from edges. "
            . 'Never truncate, clip, or cut off words at frame edges. If space is tight, reduce font size or shorten copy — never crop text.';
    }

    public static function styleLockClause(string $style): string
    {
        return match ($style) {
            'quote' => 'STYLE LOCK — Quote card template only: centered typography on a styled background, one main quote line, optional 1–2 short supporting lines maximum. '
                . 'FORBIDDEN: icon rows, numbered lists, infographic sections, bullet panels, photo-dominant layouts with tip blocks.',
            'minimal' => 'STYLE LOCK — Minimal graphic template only: clean composition, at most ONE short headline on image (under 8 words), generous negative space. '
                . 'FORBIDDEN: aggressive dark posters, icon grids, multiple headlines, dense on-image body text.',
            'infographic' => 'STYLE LOCK — Educational infographic template only: 3–5 scannable sections (line icon + bold heading + one-line body). '
                . 'Headline max 2 lines; section headings max 4 words; section body max 14 words. Wrap text inside panels — never overflow or clip.',
            'tip_card' => 'STYLE LOCK — Quick tip card template only: one icon, one headline, one supporting line, centered. '
                . 'FORBIDDEN: multi-section lists, icon grids, infographic panels.',
            'lifestyle' => 'STYLE LOCK — Lifestyle photo template only: photorealistic hero image with at most one short headline overlay (under 8 words). '
                . 'FORBIDDEN: icon sections, bullet lists, infographic panels.',
            default => '',
        };
    }

    public static function designContract(string $style, int $width, int $height): string
    {
        if (! self::isValid($style)) {
            return "\n\n".self::textSafetyClause($width, $height);
        }

        $lock = self::styleLockClause($style);

        return "\n\n".($lock !== '' ? $lock."\n" : '').self::textSafetyClause($width, $height);
    }

    public static function agentImageInstructions(string $style, int $width, int $height): string
    {
        $instructions = [
            'Follow the selected template style exactly — do not substitute a different layout or aesthetic.',
            self::textSafetyClause($width, $height),
            'Never mention Canva or other design-tool brands in prompts or captions.',
        ];

        $lock = self::styleLockClause($style);
        if ($lock !== '') {
            array_unshift($instructions, $lock);
        }

        return implode("\n", $instructions);
    }

    /**
     * Explicit canvas contract for the Cursor image agent (orientation + exact pixels).
     */
    public static function canvasSpec(int $width, int $height): string
    {
        $ratio = self::simplifiedAspectRatio($width, $height);
        $orientation = match (true) {
            $height > (int) round($width * 1.05) => 'PORTRAIT — vertical canvas (height must be greater than width)',
            $width > (int) round($height * 1.05) => 'LANDSCAPE — horizontal canvas (width must be greater than height)',
            default => 'SQUARE — equal width and height',
        };

        return "{$orientation}. Export exactly {$width}×{$height}px (aspect ratio {$ratio}). "
            . "The PNG must measure {$width}×{$height} in any image viewer. "
            . 'Never use default model sizes such as 1536×1024, 1024×1024, 1792×1024, or 1024×1792.';
    }

    public static function simplifiedAspectRatio(int $width, int $height): string
    {
        $gcd = self::gcd($width, $height);

        return ($width / $gcd).':'.($height / $gcd);
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, $a);
    }
}
