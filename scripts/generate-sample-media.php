<?php

/**
 * Generate social-media sample images at PixFlow channel preset sizes.
 * Run: php scripts/generate-sample-media.php
 */

$presets = [
    'facebook_flexible' => ['w' => 1200, 'h' => 630, 'label' => 'Facebook Link', 'channel' => 'facebook', 'count' => 4],
    'facebook_square'   => ['w' => 1200, 'h' => 1200, 'label' => 'Facebook Square', 'channel' => 'facebook', 'count' => 3],
    'instagram_square'  => ['w' => 1080, 'h' => 1080, 'label' => 'Instagram Feed', 'channel' => 'instagram', 'count' => 4],
    'instagram_portrait'=> ['w' => 1080, 'h' => 1350, 'label' => 'Instagram Portrait', 'channel' => 'instagram', 'count' => 4],
    'instagram_story'   => ['w' => 1080, 'h' => 1920, 'label' => 'Instagram Story', 'channel' => 'instagram', 'count' => 3],
    'pinterest_pin'     => ['w' => 1000, 'h' => 1500, 'label' => 'Pinterest Pin', 'channel' => 'pinterest', 'count' => 2],
];

$themes = [
    ['title' => 'Grow Your Brand', 'subtitle' => 'Social media made simple', 'c1' => [0, 74, 198], 'c2' => [99, 102, 241]],
    ['title' => 'Summer Sale', 'subtitle' => 'Up to 40% off this week', 'c1' => [255, 107, 53], 'c2' => [255, 193, 7]],
    ['title' => 'New Product Launch', 'subtitle' => 'Discover what is next', 'c1' => [19, 78, 74], 'c2' => [45, 212, 191]],
    ['title' => 'Behind the Scenes', 'subtitle' => 'Meet the team', 'c1' => [88, 28, 135], 'c2' => [236, 72, 153]],
    ['title' => 'Tips & Tricks', 'subtitle' => '5 ways to boost engagement', 'c1' => [30, 58, 138], 'c2' => [59, 130, 246]],
    ['title' => 'Customer Story', 'subtitle' => 'Real results, real people', 'c1' => [120, 53, 15], 'c2' => [251, 146, 60]],
    ['title' => 'Weekend Vibes', 'subtitle' => 'Relax and recharge', 'c1' => [6, 78, 59], 'c2' => [52, 211, 153]],
    ['title' => 'Quote of the Day', 'subtitle' => 'Inspire your audience', 'c1' => [49, 46, 129], 'c2' => [167, 139, 250]],
    ['title' => 'Event Reminder', 'subtitle' => 'Join us live tomorrow', 'c1' => [153, 27, 27], 'c2' => [248, 113, 113]],
    ['title' => 'Recipe Idea', 'subtitle' => 'Quick & healthy meals', 'c1' => [146, 64, 14], 'c2' => [253, 186, 116]],
    ['title' => 'Travel Inspiration', 'subtitle' => 'Explore new places', 'c1' => [12, 74, 110], 'c2' => [56, 189, 248]],
    ['title' => 'Fitness Goals', 'subtitle' => 'Start your journey today', 'c1' => [22, 101, 52], 'c2' => [74, 222, 128]],
    ['title' => 'Tech Update', 'subtitle' => 'What is new this month', 'c1' => [30, 41, 59], 'c2' => [100, 116, 139]],
    ['title' => 'Coffee Break', 'subtitle' => 'Good vibes only', 'c1' => [68, 47, 32], 'c2' => [180, 83, 9]],
    ['title' => 'Design Trends', 'subtitle' => '2026 visual guide', 'c1' => [76, 29, 149], 'c2' => [192, 132, 252]],
    ['title' => 'Podcast Episode', 'subtitle' => 'Listen now on all platforms', 'c1' => [15, 23, 42], 'c2' => [51, 65, 85]],
    ['title' => 'Workshop Alert', 'subtitle' => 'Free registration open', 'c1' => [14, 116, 144], 'c2' => [34, 211, 238]],
    ['title' => 'Sustainability', 'subtitle' => 'Small steps, big impact', 'c1' => [20, 83, 45], 'c2' => [134, 239, 172]],
    ['title' => 'Flash Giveaway', 'subtitle' => 'Tag a friend to enter', 'c1' => [190, 24, 93], 'c2' => [244, 114, 182]],
    ['title' => 'PixFlow Demo', 'subtitle' => 'Schedule smarter', 'c1' => [0, 74, 198], 'c2' => [147, 51, 234]],
];

$outDir = __DIR__ . '/../storage/app/public/sample-media';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$manifest = [];
$themeIndex = 0;

foreach ($presets as $presetKey => $preset) {
    for ($i = 1; $i <= $preset['count']; $i++) {
        $theme = $themes[$themeIndex % count($themes)];
        $themeIndex++;

        $filename = sprintf('%s_%02d.jpg', $presetKey, $i);
        $filepath = $outDir . '/' . $filename;

        renderImage(
            $filepath,
            $preset['w'],
            $preset['h'],
            $theme,
            $preset['label'],
            $preset['channel']
        );

        $manifest[] = [
            'file' => 'sample-media/' . $filename,
            'preset' => $presetKey,
            'channel' => $preset['channel'],
            'width' => $preset['w'],
            'height' => $preset['h'],
            'title' => $theme['title'],
        ];

        echo "Created: {$filename} ({$preset['w']}x{$preset['h']})\n";
    }
}

file_put_contents($outDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\nDone. " . count($manifest) . " images in storage/app/public/sample-media/\n";
echo "Access via: /storage/sample-media/<filename>\n";

function renderImage(string $path, int $w, int $h, array $theme, string $formatLabel, string $channel): void
{
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);

    [$r1, $g1, $b1] = $theme['c1'];
    [$r2, $g2, $b2] = $theme['c2'];

    for ($y = 0; $y < $h; $y++) {
        $ratio = $h > 1 ? $y / ($h - 1) : 0;
        $r = (int) ($r1 + ($r2 - $r1) * $ratio);
        $g = (int) ($g1 + ($g2 - $g1) * $ratio);
        $b = (int) ($b1 + ($b2 - $b1) * $ratio);
        $lineColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $w, $y, $lineColor);
    }

    // Decorative circles
    $accent = imagecolorallocatealpha($img, 255, 255, 255, 110);
    imagefilledellipse($img, (int) ($w * 0.85), (int) ($h * 0.15), (int) ($w * 0.35), (int) ($w * 0.35), $accent);
    imagefilledellipse($img, (int) ($w * 0.1), (int) ($h * 0.75), (int) ($w * 0.25), (int) ($w * 0.25), $accent);

    $white = imagecolorallocate($img, 255, 255, 255);
    $muted = imagecolorallocatealpha($img, 255, 255, 255, 30);

    $pad = (int) max(24, min($w, $h) * 0.06);
    $fontBold = findFont();
    $fontRegular = findFont(false);

    $badge = strtoupper($channel) . ' · ' . $formatLabel;
    $badgeSize = scaleFontSize($w, $h, 14, 11);
    drawText($img, $badge, $fontBold, $badgeSize, $pad, $pad + $badgeSize, $muted);

    $titleSize = scaleFontSize($w, $h, 52, 28);
    $titleY = (int) ($h * 0.42);
    drawWrappedText($img, $theme['title'], $fontBold, $titleSize, $pad, $titleY, $w - $pad * 2, $white, 1.15);

    $subSize = scaleFontSize($w, $h, 24, 16);
    $subY = min($h - $pad - $subSize * 2, $titleY + (int) ($titleSize * 2.2));
    drawWrappedText($img, $theme['subtitle'], $fontRegular, $subSize, $pad, $subY, $w - $pad * 2, $muted, 1.3);

    $dim = "{$w}×{$h}";
    $dimSize = scaleFontSize($w, $h, 13, 10);
    drawText($img, $dim, $fontRegular, $dimSize, $pad, $h - $pad, $muted);

    imagejpeg($img, $path, 92);
    imagedestroy($img);
}

function findFont(bool $bold = true): string
{
    $candidates = $bold
        ? ['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/calibrib.ttf']
        : ['C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/calibri.ttf'];

    foreach ($candidates as $font) {
        if (file_exists($font)) {
            return $font;
        }
    }

    return $candidates[0];
}

function scaleFontSize(int $w, int $h, int $landscape, int $portrait): int
{
    $base = min($w, $h);
    $isPortrait = $h > $w * 1.1;
    $target = $isPortrait ? $portrait : $landscape;

    return (int) max(10, round($target * ($base / 1080)));
}

function drawText($img, string $text, string $font, int $size, int $x, int $y, int $color): void
{
    if (file_exists($font)) {
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
    } else {
        imagestring($img, 5, $x, $y - 12, $text, $color);
    }
}

function drawWrappedText($img, string $text, string $font, int $size, int $x, int $y, int $maxWidth, int $color, float $lineHeight = 1.2): void
{
    $words = explode(' ', $text);
    $line = '';
    $currentY = $y;

    foreach ($words as $word) {
        $test = $line === '' ? $word : $line . ' ' . $word;
        $box = imagettfbbox($size, 0, $font, $test);
        $width = abs($box[2] - $box[0]);

        if ($width > $maxWidth && $line !== '') {
            drawText($img, $line, $font, $size, $x, $currentY, $color);
            $line = $word;
            $currentY += (int) ($size * $lineHeight);
        } else {
            $line = $test;
        }
    }

    if ($line !== '') {
        drawText($img, $line, $font, $size, $x, $currentY, $color);
    }
}
