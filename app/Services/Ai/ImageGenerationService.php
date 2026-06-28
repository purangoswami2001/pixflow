<?php

namespace App\Services\Ai;

use App\Exceptions\AiGenerationException;
use App\Services\Automation\SocialImageSafeZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGenerationService
{
    public function generate(string $prompt, int $width, int $height, ?array $providers = null, ?string $platform = null, ?string $imageStyle = null): array
    {
        $providers = $providers ?? config('ai.image_providers', ['openai', 'gemini', 'cursor']);
        $lastError = 'No image AI providers configured.';

        if ($platform !== null && $platform !== '') {
            $prompt = SocialImageSafeZone::appendToDesignPrompt(
                $prompt,
                $platform,
                $width,
                $height,
                $imageStyle ?? 'lifestyle'
            );
        }

        foreach ($providers as $provider) {
            try {
                $path = match ($provider) {
                    'openai' => $this->openAiImage($prompt, $width, $height),
                    'gemini' => $this->geminiImage($prompt, $width, $height),
                    'cursor' => $this->cursorImage($prompt, $width, $height, $platform, $imageStyle),
                    default => null,
                };

                if (is_string($path) && $path !== '') {
                    $dims = $this->probeLocalImageDimensions($path);

                    return [
                        'path' => $path,
                        'provider' => $provider,
                        'width' => $dims['width'] ?? $width,
                        'height' => $dims['height'] ?? $height,
                    ];
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("ImageGenerationService: {$provider} failed", [
                    'error' => $lastError,
                    'fallback_providers' => array_slice($providers, array_search($provider, $providers, true) + 1),
                ]);
            }
        }

        throw new AiGenerationException($lastError);
    }

    protected function openAiImage(string $prompt, int $width, int $height): string
    {
        $apiKey = config('ai.openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiGenerationException('OpenAI API key is not configured.');
        }

        $model = config('ai.openai_image.model', 'dall-e-3');
        $size = $this->nearestOpenAiSize($width, $height);

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => config('ai.openai_image.quality', 'standard'),
                'response_format' => 'b64_json',
            ]);

        if (! $response->successful()) {
            throw new AiGenerationException($response->json('error.message') ?? 'OpenAI image request failed.');
        }

        $b64 = $response->json('data.0.b64_json');
        if (! is_string($b64) || $b64 === '') {
            throw new AiGenerationException('OpenAI returned no image data.');
        }

        return $this->storeBinary(base64_decode($b64), 'png');
    }

    protected function geminiImage(string $prompt, int $width, int $height): string
    {
        $apiKey = config('ai.gemini.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiGenerationException('Gemini API key is not configured.');
        }

        $model = config('ai.gemini.image_model', 'gemini-2.0-flash-preview-image-generation');
        $apiVersion = config('ai.gemini.api_version', 'v1beta');
        $url = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent";

        $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout(120)
            ->post($url, [
                'contents' => [
                    ['parts' => [['text' => "{$prompt}\n\nTarget dimensions: {$width}x{$height}px. Full-bleed, edge-to-edge canvas."]]],
                ],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                ],
            ]);

        if (! $response->successful()) {
            throw new AiGenerationException($response->json('error.message') ?? 'Gemini image request failed.');
        }

        $parts = $response->json('candidates.0.content.parts') ?? [];
        foreach ($parts as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline)) {
                continue;
            }

            $data = $inline['data'] ?? null;
            $mime = $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png';
            if (! is_string($data) || $data === '') {
                continue;
            }

            $ext = str_contains($mime, 'jpeg') ? 'jpg' : 'png';

            return $this->storeBinary(base64_decode($data), $ext);
        }

        throw new AiGenerationException('Gemini returned no image in response.');
    }

    protected function cursorImage(string $prompt, int $width, int $height, ?string $platform = null, ?string $imageStyle = null): string
    {
        return app(CursorApiClient::class)->generateImage($prompt, $width, $height, $imageStyle);
    }

    protected function nearestOpenAiSize(int $width, int $height): string
    {
        $ratio = $width / max(1, $height);

        if ($ratio > 1.4) {
            return '1792x1024';
        }

        if ($ratio < 0.75) {
            return '1024x1792';
        }

        return '1024x1024';
    }

    protected function storeBinary(string $binary, string $ext): string
    {
        if ($binary === '') {
            throw new AiGenerationException('Empty image binary received.');
        }

        $dir = 'ai-generated/' . now()->format('Y/m/d');
        Storage::disk('local')->makeDirectory($dir);
        $path = "{$dir}/" . Str::uuid() . ".{$ext}";
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    protected function probeLocalImageDimensions(string $localPath): ?array
    {
        $absolute = Storage::disk('local')->path($localPath);
        $info = @getimagesize($absolute);
        if ($info === false) {
            return null;
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }
}
