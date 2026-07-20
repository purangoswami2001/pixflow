<?php

namespace App\Support;

/**
 * Controls visibility of backend AI implementation details.
 *
 * Raw provider/model data remains stored for troubleshooting, but presentation
 * layers must pass diagnostics through this class before returning them.
 */
class AiDiagnostics
{
    /**
     * Keys that reveal the backend AI implementation.
     *
     * @var list<string>
     */
    private const PRIVATE_KEYS = [
        'provider',
        '_provider',
        'text_provider',
        'image_provider',
        'cursor_model',
        'model',
        'model_params',
        'api_base_url',
        'api_url',
    ];

    public static function visible(): bool
    {
        return (bool) config('app.debug');
    }

    public static function forDisplay(mixed $value): mixed
    {
        if (self::visible()) {
            return $value;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), self::PRIVATE_KEYS, true)) {
                    continue;
                }

                $sanitized[$key] = self::forDisplay($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return preg_replace(
                '/\b(?:OpenAI|Gemini|Cursor)(?:\s+(?:Cloud|API|Agent)){0,2}\b/i',
                'AI provider',
                $value,
            ) ?? $value;
        }

        return $value;
    }
}
