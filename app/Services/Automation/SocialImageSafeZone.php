<?php

namespace App\Services\Automation;

use App\Support\AutomationImageStyles;

class SocialImageSafeZone
{
    public static function appendToDesignPrompt(
        string $designPrompt,
        string $platform,
        int $width,
        int $height,
        string $imageStyle = 'infographic'
    ): string {
        if (! str_contains($designPrompt, 'STYLE LOCK')) {
            $designPrompt = rtrim($designPrompt).AutomationImageStyles::designContract($imageStyle, $width, $height);
        }

        if (str_contains($designPrompt, 'Exact '.$width.'x'.$height)) {
            return $designPrompt;
        }

        $margins = AutomationImageStyles::safeMargins($width, $height);
        $ratio = round($width / max(1, $height), 3);
        $suffix = "\n\nTechnical requirements: Exact {$width}x{$height}px (ratio {$ratio}:1). "
            . "Platform: {$platform}. Full-bleed edge-to-edge — no letterboxing or blank border bars.";

        if ($imageStyle === 'infographic') {
            $suffix .= " Keep all text and icons inside {$margins['x']}px horizontal and {$margins['y']}px vertical safe margins; background and color blocks fill the entire canvas.";
        } elseif (in_array($imageStyle, ['lifestyle', 'minimal', 'quote', 'tip_card'], true)) {
            $suffix .= ' Photorealistic or clean graphic quality. Strictly follow the selected template — do not add infographic sections or extra text blocks.';
        }

        return rtrim($designPrompt).$suffix;
    }

    public static function imageStepUserPrompt(
        string $platform,
        string $title,
        string $topic,
        string $keyPoints,
        int $width,
        int $height,
        string $imageStyle,
        string $niche = '',
        string $audience = '',
        string $colorPalette = 'feminine, soft sage, blush pink, lavender, cream'
    ): string {
        $styleHint = AutomationImageStyles::promptHint($imageStyle);
        $layout = self::layoutHintForCanvas($width, $height, $imageStyle);
        $context = self::contextBlock($niche, $audience, $colorPalette);

        $margins = AutomationImageStyles::safeMargins($width, $height);
        $textSafety = AutomationImageStyles::textSafetyClause($width, $height);

        if ($imageStyle === 'infographic') {
            return "Create a detailed text-to-image design_prompt for a {$platform} social post.\n"
                . "Canvas: {$width}x{$height}px.\n"
                . "Image style: {$styleHint}\n"
                . "Title (use on image): {$title}\n"
                . "Topic: {$topic}\n"
                . "{$context}"
                . "Key points — turn each into an on-image section (icon + short heading + 1-line body):\n{$keyPoints}\n"
                . "Layout guidance: {$layout}\n"
                . "Include main title, 3–5 sections with readable on-image text. Shorten headings to fit within {$margins['x']}px side margins.\n"
                . "Colors: {$colorPalette}. High contrast, mobile-readable.\n"
                . "{$textSafety}\n"
                . 'Respond JSON: design_prompt (string)';
        }

        $forbidden = self::forbiddenElementsForStyle($imageStyle);

        return "Create a text-to-image design_prompt for a {$platform} social image.\n"
            . "Canvas: {$width}x{$height}px.\n"
            . "Image style (must match exactly): {$styleHint}\n"
            . "Title/hook: {$title}\n"
            . "Topic: {$topic}\n"
            . "{$context}"
            . "Key ideas (mood/reference only — do not dump all text on the image):\n{$keyPoints}\n"
            . "Layout guidance: {$layout}\n"
            . ($forbidden !== '' ? "FORBIDDEN in this design: {$forbidden}\n" : '')
            . "{$textSafety}\n"
            . 'Respond JSON: design_prompt (string)';
    }

    protected static function forbiddenElementsForStyle(string $imageStyle): string
    {
        return match ($imageStyle) {
            'quote' => 'icon rows, numbered sections, infographic panels, bullet lists, multi-section tip blocks',
            'minimal' => 'dark black/red gym poster style, halftone textures, icon grids, multiple headlines, dense body text',
            'tip_card' => 'multi-section lists, infographic panels, more than one tip block',
            'lifestyle' => 'icon sections, bullet lists, infographic panels, multiple text blocks',
            default => '',
        };
    }

    protected static function contextBlock(string $niche, string $audience, string $colorPalette): string
    {
        $lines = '';
        if ($niche !== '') {
            $lines .= "Niche: {$niche}\n";
        }
        if ($audience !== '') {
            $lines .= "Audience: {$audience}\n";
        }
        if ($colorPalette !== '') {
            $lines .= "Brand colors: {$colorPalette}\n";
        }

        return $lines;
    }

    protected static function layoutHintForCanvas(int $width, int $height, string $imageStyle): string
    {
        $ratio = $width / max(1, $height);

        if ($imageStyle === 'infographic') {
            if ($ratio > 1.15) {
                return 'Landscape split-panel infographic: lifestyle or hero visual on one side (~35–45%), icon+text tip columns on the other. Title banner across top.';
            }

            if ($ratio < 0.9) {
                return 'Portrait pin infographic: title header, stacked 3–5 icon+text sections, optional lifestyle photo accent, footer strip.';
            }

            return 'Square infographic: headline banner, grid or stacked sections with icons, optional corner lifestyle photo inset.';
        }

        if ($ratio > 1.15) {
            return 'Landscape: strong left-anchored hero visual with negative space on the right for optional short headline.';
        }

        if ($ratio < 0.9) {
            return 'Portrait: centered vertical composition, balanced visual weight top to bottom.';
        }

        return 'Square: centered composition with balanced visual weight.';
    }
}
