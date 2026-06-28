<?php

namespace App\Services\Automation;

class InfographicPalette
{
    /** @var array<string, string> */
    public const COLORS = [
        'sage' => '#8FAF7E',
        'blush' => '#E8B4B8',
        'lavender' => '#C5B4E3',
        'cream' => '#FAF6F0',
        'charcoal' => '#2D2D2D',
        'white' => '#FFFFFF',
        'deep_sage' => '#5C7354',
    ];

    public static function hex(string $accent): string
    {
        return self::COLORS[$accent] ?? self::COLORS['cream'];
    }

    /**
     * @return list<string>
     */
    public static function accentCycle(): array
    {
        return ['sage', 'blush', 'lavender', 'cream', 'blush'];
    }
}
