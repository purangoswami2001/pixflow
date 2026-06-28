<?php

namespace App\Services\Automation;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;

class InfographicRenderer
{
    public function renderToStorage(array $layout, int $width, int $height): string
    {
        $binary = $this->render($layout, $width, $height);
        $dir = 'ai-generated/'.now()->format('Y/m/d');
        Storage::disk('local')->makeDirectory($dir);
        $path = "{$dir}/".Str::uuid().'.png';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function render(array $layout, int $width, int $height): string
    {
        $font = InfographicFontResolver::resolve();
        if ($font === null) {
            throw new \RuntimeException('No readable TTF font found for infographic rendering. Set AUTOMATION_INFOGRAPHIC_FONT in .env.');
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->createImage($width, $height)->fill(InfographicPalette::hex('cream'));

        $pad = (int) round($width * 0.04);
        $title = trim((string) ($layout['title'] ?? 'Wellness Tips'));
        $subtitle = trim((string) ($layout['subtitle'] ?? ''));
        $sections = is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
        $footer = trim((string) ($layout['footer'] ?? ''));

        $headerHeight = (int) round($height * ($width >= $height ? 0.22 : 0.16));
        $footerHeight = $footer !== '' ? (int) round($height * 0.07) : 0;
        $contentTop = $headerHeight + $pad;
        $contentBottom = $height - $footerHeight - $pad;
        $contentHeight = max(100, $contentBottom - $contentTop);

        $this->drawBlock($image, 0, 0, $width, $headerHeight, InfographicPalette::hex('sage'));
        $this->drawText($image, $font, $title, $width / 2, $pad, (int) round($width * 0.12), InfographicPalette::hex('white'), 'center', 'top', true);

        if ($subtitle !== '') {
            $this->drawText(
                $image,
                $font,
                $subtitle,
                $width / 2,
                $pad + (int) round($headerHeight * 0.42),
                (int) round($width * 0.055),
                InfographicPalette::hex('cream'),
                'center',
                'top'
            );
        }

        $sectionCount = max(1, min(5, count($sections)));
        $gap = (int) round($pad * 0.6);
        $sectionHeight = (int) floor(($contentHeight - ($gap * max(0, $sectionCount - 1))) / $sectionCount);
        $y = $contentTop;

        foreach (array_slice($sections, 0, 5) as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $accent = (string) ($section['accent'] ?? InfographicPalette::accentCycle()[$index % 5]);
            $heading = trim((string) ($section['heading'] ?? 'Tip '.($index + 1)));
            $body = trim((string) ($section['body'] ?? ''));
            $bg = InfographicPalette::hex($accent);
            $textColor = in_array($accent, ['sage', 'deep_sage'], true)
                ? InfographicPalette::hex('white')
                : InfographicPalette::hex('charcoal');

            $this->drawBlock($image, $pad, $y, $width - ($pad * 2), $sectionHeight, $bg);

            $iconSize = (int) min(56, round($sectionHeight * 0.28));
            $iconX = $pad + (int) round($pad * 0.8);
            $iconY = $y + (int) round(($sectionHeight - $iconSize) / 2);
            $this->drawIconCircle($image, $iconX, $iconY, $iconSize, InfographicPalette::hex('white'), (string) ($index + 1));

            $textX = $iconX + $iconSize + (int) round($pad * 0.9);
            $textWidth = $width - $textX - ($pad * 2);
            $headingY = $y + (int) round($sectionHeight * 0.18);
            $this->drawText($image, $font, $heading, $textX, $headingY, (int) round($width * 0.048), $textColor, 'left', 'top', true, $textWidth);
            $this->drawText(
                $image,
                $font,
                $body,
                $textX,
                $headingY + (int) round($sectionHeight * 0.38),
                (int) round($width * 0.036),
                $textColor,
                'left',
                'top',
                false,
                $textWidth
            );

            $y += $sectionHeight + $gap;
        }

        if ($footer !== '') {
            $this->drawBlock($image, 0, $height - $footerHeight, $width, $footerHeight, InfographicPalette::hex('lavender'));
            $this->drawText(
                $image,
                $font,
                $footer,
                $width / 2,
                $height - $footerHeight + (int) round($footerHeight * 0.28),
                (int) round($width * 0.038),
                InfographicPalette::hex('charcoal'),
                'center',
                'top'
            );
        }

        return (string) $image->encode(new PngEncoder())->toString();
    }

    protected function drawBlock(
        \Intervention\Image\Interfaces\ImageInterface $image,
        int $x,
        int $y,
        int $w,
        int $h,
        string $color
    ): void {
        $image->drawRectangle(function (RectangleFactory $rectangle) use ($x, $y, $w, $h, $color) {
            $rectangle->size($w, $h);
            $rectangle->at($x, $y);
            $rectangle->background($color);
            $rectangle->border($color, 0);
        });
    }

    protected function drawIconCircle(
        \Intervention\Image\Interfaces\ImageInterface $image,
        int $x,
        int $y,
        int $size,
        string $fill,
        string $label
    ): void {
        $centerX = $x + (int) ($size / 2);
        $centerY = $y + (int) ($size / 2);

        $image->drawCircle(function ($circle) use ($centerX, $centerY, $size, $fill) {
            $circle->radius((int) ($size / 2));
            $circle->at($centerX, $centerY);
            $circle->background($fill);
            $circle->border(InfographicPalette::hex('white'), 2);
        });

        $font = InfographicFontResolver::resolve();
        if ($font !== null) {
            $this->drawText(
                $image,
                $font,
                $label,
                $x + (int) ($size / 2),
                $y + (int) ($size / 2) - 6,
                (int) round($size * 0.38),
                InfographicPalette::hex('deep_sage'),
                'center',
                'center',
                true
            );
        }
    }

    protected function drawText(
        \Intervention\Image\Interfaces\ImageInterface $image,
        string $fontPath,
        string $text,
        int|float $x,
        int $y,
        int $size,
        string $color,
        string $align = 'left',
        string $valign = 'top',
        bool $bold = false,
        ?int $wrapWidth = null
    ): void {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $image->text($text, (int) $x, $y, function ($font) use ($fontPath, $size, $color, $align, $valign, $bold, $wrapWidth) {
            $font->file($bold ? $this->boldVariant($fontPath) : $fontPath);
            $font->size($size);
            $font->color($color);
            $font->align($align, $valign);
            if ($wrapWidth !== null) {
                $font->wrap($wrapWidth);
            }
        });
    }

    protected function boldVariant(string $fontPath): string
    {
        $bold = preg_replace('/(\.ttf)$/i', 'b$1', $fontPath);
        if (is_string($bold) && is_readable($bold)) {
            return $bold;
        }

        $bold = str_replace(['Regular', 'regular'], ['Bold', 'Bold'], $fontPath);
        if (is_readable($bold)) {
            return $bold;
        }

        return $fontPath;
    }
}
