<?php

namespace App\Services\Automation;

class InfographicFontResolver
{
    public static function resolve(): ?string
    {
        $configured = trim((string) config('automation.infographic_font', ''));
        if ($configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $candidates = match (PHP_OS_FAMILY) {
            'Windows' => [
                'C:/Windows/Fonts/segoeuib.ttf',
                'C:/Windows/Fonts/segoeui.ttf',
                'C:/Windows/Fonts/arialbd.ttf',
                'C:/Windows/Fonts/arial.ttf',
                'C:/Windows/Fonts/georgia.ttf',
            ],
            'Darwin' => [
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial.ttf',
            ],
            default => [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            ],
        };

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
