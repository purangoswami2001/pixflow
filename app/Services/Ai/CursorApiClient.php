<?php

namespace App\Services\Ai;

use App\Exceptions\AiGenerationException;
use App\Support\AutomationImageStyles;
use App\Services\Automation\CursorInfographicImageService;
use App\Services\Automation\InfographicFontResolver;
use App\Services\Ai\Cursor\CursorCloudAgentsApi;
use App\Services\Ai\Cursor\CursorModelResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CursorApiClient
{
    protected ?string $sessionAgentId = null;

    protected ?string $sessionName = null;

    public function __construct(
        protected CursorCloudAgentsApi $cloudApi
    ) {
    }

    public function usesCloudApi(): bool
    {
        if (strtolower((string) config('ai.cursor.api_mode', 'cloud')) === 'openai') {
            return false;
        }

        $base = strtolower(rtrim((string) config('ai.cursor.api_base_url', 'https://api.cursor.com/v1'), '/'));

        return $base === '' || str_contains($base, 'api.cursor.com');
    }

    public function supportsImage(): bool
    {
        if (! $this->apiKey()) {
            return false;
        }

        if ($this->imageMode() === 'layout') {
            return InfographicFontResolver::resolve() !== null;
        }

        return true;
    }

    public function imageMode(): string
    {
        $mode = strtolower((string) config('ai.cursor.image_mode', 'api'));

        if ($mode === 'api' && (bool) config('ai.cursor.image_via_artifacts', false) && $this->usesCloudApi()) {
            return 'agent';
        }

        if (! in_array($mode, ['api', 'agent', 'layout'], true)) {
            return 'api';
        }

        return $mode;
    }

    public function beginSession(string $name = 'PixFlow automation'): void
    {
        $this->sessionAgentId = null;
        $this->sessionName = Str::limit($name, 100, '');
    }

    public function endSession(): void
    {
        if ($this->sessionAgentId && config('ai.cursor.archive_agents', true)) {
            try {
                $this->cloudApi->archiveAgent($this->sessionAgentId);
            } catch (\Throwable $e) {
                Log::debug('Cursor agent archive failed', [
                    'agent_id' => $this->sessionAgentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->sessionAgentId = null;
        $this->sessionName = null;
    }

    public function assertConfigured(bool $requireImage = false): void
    {
        if (! $this->apiKey()) {
            throw new AiGenerationException('CURSOR_API_KEY is not configured.');
        }

        if ($this->usesCloudApi()) {
            if ($requireImage && ! $this->supportsImage()) {
                throw new AiGenerationException(
                    'Cursor image generation is not configured. Set CURSOR_IMAGE_MODE=api (default), agent, or layout.'
                );
            }

            return;
        }

        if (! $this->chatUrl()) {
            throw new AiGenerationException('CURSOR_API_BASE_URL is not configured for OpenAI-compatible mode.');
        }

        if ($requireImage && ! $this->imageUrl()) {
            throw new AiGenerationException('Set CURSOR_API_BASE_URL or CURSOR_IMAGE_API_URL for image generation.');
        }
    }

    public function chatJson(string $systemPrompt, string $userPrompt): array
    {
        $this->assertConfigured();

        return $this->usesCloudApi()
            ? $this->parseJsonFromText($this->runCloudPrompt($systemPrompt, $userPrompt))
            : $this->chatJsonViaOpenAiCompatible($systemPrompt, $userPrompt);
    }

    public function generateImage(string $prompt, int $width, int $height, ?string $imageStyle = null): string
    {
        $this->assertConfigured(requireImage: true);
        $mode = $this->imageMode();

        if ($mode === 'layout') {
            return app(CursorInfographicImageService::class)->generate($prompt, $width, $height);
        }

        if ($mode === 'agent') {
            return $this->generateImageViaAgent($prompt, $width, $height, $imageStyle);
        }

        try {
            return $this->generateImageViaApi($prompt, $width, $height);
        } catch (AiGenerationException $e) {
            if ($this->usesCloudApi() && (bool) config('ai.cursor.agent_fallback', true)) {
                Log::warning('Cursor image API failed, falling back to cloud agent', [
                    'error' => $e->getMessage(),
                ]);

                return $this->generateImageViaAgent($prompt, $width, $height, $imageStyle);
            }

            throw $e;
        }
    }

    protected function generateImageViaApi(string $prompt, int $width, int $height): string
    {
        $url = $this->imageUrl();
        if ($url === null) {
            throw new AiGenerationException('CURSOR_IMAGE_API_URL is not configured for direct image API calls.');
        }

        $payload = [
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->nearestImageApiSize($width, $height),
            'response_format' => 'b64_json',
        ];

        if ($model = config('ai.cursor.image_model')) {
            $payload['model'] = $model;
        }

        $response = $this->proxyHttp(180)->post($url, $payload);

        if (! $response->successful()) {
            throw new AiGenerationException(
                $response->json('error.message') ?? $response->json('message') ?? 'Cursor image API request failed (HTTP '.$response->status().').'
            );
        }

        $b64 = $response->json('data.0.b64_json')
            ?? $response->json('image_base64')
            ?? $response->json('b64_json');

        if (is_string($b64) && $b64 !== '') {
            return $this->storeBinary(base64_decode($b64), 'png');
        }

        $imageUrl = $response->json('data.0.url') ?? $response->json('url');
        if (is_string($imageUrl) && $imageUrl !== '') {
            $binary = Http::timeout(120)->get($imageUrl)->body();
            if ($binary !== '') {
                return $this->storeBinary($binary, 'png');
            }
        }

        throw new AiGenerationException('Image API returned no image data.');
    }

    protected function nearestImageApiSize(int $width, int $height): string
    {
        $ratio = $width / max(1, $height);

        if ($ratio > 1.2) {
            return '1792x1024';
        }

        if ($ratio < 0.85) {
            return '1024x1792';
        }

        return '1024x1024';
    }

    protected function runCloudPrompt(string $systemPrompt, string $userPrompt, ?int $timeoutSeconds = null): string
    {
        $promptText = "SYSTEM INSTRUCTIONS:\n{$systemPrompt}\n\n"
            . "USER REQUEST:\n{$userPrompt}\n\n"
            . 'Reply with valid JSON only. No markdown fences, no explanation.';

        if ($this->sessionAgentId) {
            return $this->createFollowUpRun($promptText, $timeoutSeconds);
        }

        $payload = [
            'prompt' => ['text' => $promptText],
            'name' => $this->sessionName ?: 'PixFlow '.now()->format('Y-m-d H:i:s'),
        ];

        $payload = array_merge($payload, app(CursorModelResolver::class)->resolvePayload());

        $created = $this->cloudApi->createAgent($payload);
        $agentId = $created['agent']['id'] ?? null;
        $runId = $created['run']['id'] ?? null;

        if (! is_string($agentId) || ! is_string($runId)) {
            throw new AiGenerationException('Cursor POST /v1/agents response missing agent or run id.');
        }

        if ($this->sessionName !== null) {
            $this->sessionAgentId = $agentId;
        }

        return $this->cloudApi->waitForRunResult($agentId, $runId, $timeoutSeconds);
    }

    protected function createFollowUpRun(string $promptText, ?int $timeoutSeconds = null): string
    {
        $agentId = $this->sessionAgentId;
        if (! is_string($agentId) || $agentId === '') {
            throw new AiGenerationException('Cursor agent session is not active.');
        }

        $attempts = 0;
        while ($attempts < 5) {
            $attempts++;

            try {
                $created = $this->cloudApi->createRun($agentId, [
                    'prompt' => ['text' => $promptText],
                ]);
            } catch (AiGenerationException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'busy')) {
                    throw $e;
                }

                sleep((int) config('ai.cursor.poll_interval', 3));

                continue;
            }

            $runId = $created['run']['id'] ?? null;
            if (! is_string($runId)) {
                throw new AiGenerationException('Cursor POST /v1/agents/{id}/runs response missing run id.');
            }

            return $this->cloudApi->waitForRunResult($agentId, $runId, $timeoutSeconds);
        }

        throw new AiGenerationException('Cursor agent is busy; could not queue follow-up run.');
    }

    protected function generateImageViaAgent(string $prompt, int $width, int $height, ?string $imageStyle = null): string
    {
        $savedAgentId = $this->sessionAgentId;
        $savedName = $this->sessionName;
        $timeout = $this->computeImageAgentTimeout($width, $height, $imageStyle);
        $brief = $this->briefForAgentImage($prompt, $imageStyle, $width, $height);
        $styleInstructions = AutomationImageStyles::isValid((string) $imageStyle)
            ? AutomationImageStyles::agentImageInstructions((string) $imageStyle, $width, $height)
            : AutomationImageStyles::textSafetyClause($width, $height);
        $canvasSpec = AutomationImageStyles::canvasSpec($width, $height);
        $maxAttempts = max(1, (int) config('ai.cursor.image_dimension_retries', 2));
        $lastActual = null;

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $artifactPath = 'artifacts/pp-'.Str::uuid().'.png';
                $ephemeralAgentId = null;

                try {
                    $this->sessionAgentId = null;
                    $this->sessionName = 'PixFlow image '.$width.'x'.$height;

                    $correction = '';
                    if ($attempt > 1 && is_array($lastActual)) {
                        $correction = "\n\nCORRECTION (required): Your previous export was {$lastActual['width']}×{$lastActual['height']}px — that is WRONG. "
                            . "You must produce exactly {$width}×{$height}px. {$canvasSpec}\n";
                    }

                    $imagePrompt = "Generate a high-quality social media image using your image generation capability.\n\n"
                        . "OUTPUT SIZE (mandatory — highest priority):\n"
                        . "- {$canvasSpec}\n"
                        . "- Aspect ratio: ".round($width / max(1, $height), 4).":1\n"
                        . "- If your image tool defaults to another size, explicitly resize or re-export to {$width}×{$height}px before saving\n"
                        . $correction."\n"
                        . "DESIGN BRIEF:\n{$brief}\n\n"
                        . "Requirements:\n"
                        . "- The PNG file must be exactly {$width}x{$height}px when opened in an image viewer\n"
                        . "- Professional full-bleed design within that canvas — no extra canvas, no crop marks\n"
                        . "- {$styleInstructions}\n"
                        . "- Render readable on-image text, icons, and layout when described\n"
                        . "- Save the finished PNG to exactly `{$artifactPath}` under the artifacts directory\n"
                        . '- Produce the actual image file, not text-only instructions';

                    Log::info('Cursor image agent starting', [
                        'width' => $width,
                        'height' => $height,
                        'image_style' => $imageStyle,
                        'timeout_sec' => $timeout,
                        'brief_chars' => mb_strlen($brief),
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                    ]);

                    $this->runCloudPrompt(
                        'You are a visual designer with image generation capabilities. Produce a real high-quality PNG at the artifact path. '
                        . "{$canvasSpec} Match the design brief and never export a different size or orientation.",
                        $imagePrompt,
                        $timeout
                    );

                    $ephemeralAgentId = $this->sessionAgentId;
                    if (! is_string($ephemeralAgentId) || $ephemeralAgentId === '') {
                        throw new AiGenerationException('Cursor cloud agent did not start for image generation.');
                    }

                    $path = $this->waitForImageArtifact($ephemeralAgentId, $artifactPath, $timeout);
                    if ($path === null) {
                        throw new AiGenerationException('Cursor cloud agent did not produce an image file.');
                    }

                    $localPath = $this->downloadArtifact($ephemeralAgentId, $path);
                    $actual = $this->probeLocalImageDimensions($localPath);

                    if ($actual !== null && $this->imageDimensionsAcceptable($actual, $width, $height)) {
                        if ($actual['width'] !== $width || $actual['height'] !== $height) {
                            Log::info('Cursor image ratio OK — will scale to preset in media step', [
                                'expected' => "{$width}x{$height}",
                                'actual' => "{$actual['width']}x{$actual['height']}",
                                'attempt' => $attempt,
                            ]);
                        }

                        return $localPath;
                    }

                    $lastActual = $actual;
                    Log::warning('Cursor image agent wrong dimensions', [
                        'expected' => "{$width}x{$height}",
                        'actual' => is_array($actual) ? "{$actual['width']}x{$actual['height']}" : 'unknown',
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                    ]);

                    Storage::disk('local')->delete($localPath);
                } finally {
                    $this->archiveAgentQuietly($ephemeralAgentId);
                }
            }

            $actualLabel = is_array($lastActual)
                ? "{$lastActual['width']}×{$lastActual['height']}"
                : 'unknown';

            throw new AiGenerationException(
                "Cursor agent exported the wrong image size ({$actualLabel}); required exactly {$width}×{$height}px ({$canvasSpec})."
            );
        } finally {
            $this->sessionAgentId = $savedAgentId;
            $this->sessionName = $savedName;
        }
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

    /**
     * @param  array{width: int, height: int}  $actual
     */
    protected function imageDimensionsAcceptable(array $actual, int $targetW, int $targetH, float $ratioTolerance = 0.02): bool
    {
        if ($actual['width'] === $targetW && $actual['height'] === $targetH) {
            return true;
        }

        $targetRatio = $targetW / max(1, $targetH);
        $actualRatio = $actual['width'] / max(1, $actual['height']);

        return abs($targetRatio - $actualRatio) < $ratioTolerance;
    }

    protected function computeImageAgentTimeout(int $width, int $height, ?string $imageStyle = null): int
    {
        $configured = (int) config('ai.cursor.image_agent_poll_timeout', 480);
        $pixels = $width * $height;
        $estimated = match (true) {
            $pixels >= 1_500_000 => 420,
            $pixels >= 1_000_000 => 300,
            default => 240,
        };

        if ($imageStyle === 'infographic') {
            $estimated += 90;
        }

        return max($configured, $estimated);
    }

    protected function briefForAgentImage(string $prompt, ?string $imageStyle = null, int $width = 1080, int $height = 1350): string
    {
        $prompt = trim($prompt);

        if ($imageStyle === 'infographic' && mb_strlen($prompt) > 1800) {
            $prompt = $this->condenseInfographicBrief($prompt, $width, $height);
        }

        if (is_string($imageStyle) && AutomationImageStyles::isValid($imageStyle) && ! str_contains($prompt, 'STYLE LOCK')) {
            $prompt = AutomationImageStyles::styleLockClause($imageStyle)."\n".$prompt;
        }

        if (! str_contains($prompt, 'TEXT SAFETY')) {
            $prompt .= "\n".AutomationImageStyles::textSafetyClause($width, $height);
        }

        return $this->truncateForArtifactPrompt($prompt, 2800);
    }

    protected function condenseInfographicBrief(string $prompt, int $width = 1000, int $height = 1500): string
    {
        if (preg_match('/"([^"]{12,120})"/', $prompt, $title)) {
            $headline = $title[1];
        } else {
            $headline = 'Educational infographic';
        }

        $sections = [];
        if (preg_match_all('/Section \d+[^:]*:\s*heading\s*"([^"]+)"[^"]*"([^"]+)"/i', $prompt, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 5) as $match) {
                $sections[] = '- '.$match[1].': '.$match[2];
            }
        }

        if ($sections === [] && preg_match_all('/heading\s*"([^"]+)"\s*,\s*body\s*"([^"]+)"/i', $prompt, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 5) as $match) {
                $sections[] = '- '.$match[1].': '.$match[2];
            }
        }

        $margins = AutomationImageStyles::safeMargins($width, $height);
        $summary = "Vertical educational infographic pin.\n"
            . "Headline on image: \"{$headline}\"\n"
            . "Layout: split panel with lifestyle photo + 4–5 icon sections with short headings and one-line body text.\n"
            . "Palette: sage, blush, lavender, cream. Full-bleed, mobile-readable typography.\n"
            . "Keep all text inside {$margins['x']}px horizontal and {$margins['y']}px vertical safe margins — shorten copy rather than clip.\n";

        if ($sections !== []) {
            $summary .= "Sections:\n".implode("\n", $sections);
        } else {
            $summary .= mb_substr($prompt, 0, 900);
        }

        return $summary;
    }

    /** @deprecated use generateImageViaAgent */
    protected function generateImageViaArtifacts(string $prompt, int $width, int $height): string
    {
        return $this->generateImageViaAgent($prompt, $width, $height);
    }

    protected function truncateForArtifactPrompt(string $prompt, int $max = 4500): string
    {
        $prompt = trim($prompt);
        if (mb_strlen($prompt) <= $max) {
            return $prompt;
        }

        return mb_substr($prompt, 0, $max).'…';
    }

    protected function downloadArtifact(string $agentId, string $path): string
    {
        $downloadUrl = $this->cloudApi->artifactDownloadUrl($agentId, $path);
        $binary = Http::timeout(120)->get($downloadUrl)->body();

        if ($binary === '') {
            throw new AiGenerationException('Failed to download Cursor image artifact.');
        }

        $ext = str_ends_with(strtolower($path), '.jpg') || str_ends_with(strtolower($path), '.jpeg') ? 'jpg' : 'png';

        return $this->storeBinary($binary, $ext);
    }

    protected function archiveAgentQuietly(?string $agentId): void
    {
        if (! is_string($agentId) || $agentId === '' || ! config('ai.cursor.archive_agents', true)) {
            return;
        }

        try {
            $this->cloudApi->archiveAgent($agentId);
        } catch (\Throwable $e) {
            Log::debug('Cursor agent archive failed', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
        }
    }

    protected function waitForImageArtifact(string $agentId, string $preferredPath, ?int $timeoutSeconds = null): ?string
    {
        $timeout = $timeoutSeconds ?? (int) config('ai.cursor.artifact_poll_timeout', 360);
        $interval = max(2, (int) config('ai.cursor.poll_interval', 3));
        $deadline = time() + $timeout;
        $lastItems = [];

        while (time() < $deadline) {
            $lastItems = $this->cloudApi->listArtifacts($agentId);
            $path = $this->resolveArtifactPath($lastItems, $preferredPath);
            if ($path !== null) {
                return $path;
            }

            sleep($interval);
        }

        Log::warning('Cursor artifact image missing after polling', [
            'agent_id' => $agentId,
            'preferred_path' => $preferredPath,
            'artifacts' => array_values(array_filter(array_map(
                fn ($item) => $item['path'] ?? null,
                $lastItems
            ))),
            'poll_timeout_sec' => $timeout,
        ]);

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function resolveArtifactPath(array $items, string $preferred): ?string
    {
        foreach ($items as $item) {
            if (($item['path'] ?? null) === $preferred) {
                return $item['path'];
            }
        }

        foreach ($items as $item) {
            $path = $item['path'] ?? null;
            if (is_string($path) && preg_match('/\.(png|jpe?g|webp)$/i', $path)) {
                return $path;
            }
        }

        return null;
    }

    protected function chatJsonViaOpenAiCompatible(string $systemPrompt, string $userPrompt): array
    {
        $payload = [
            'model' => config('ai.cursor.chat_model', 'composer-2.5'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.7,
        ];

        if (config('ai.cursor.json_mode', true)) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->proxyHttp()->post($this->chatUrl(), $payload);

        if (! $response->successful()) {
            throw new AiGenerationException(
                $response->json('error.message') ?? $response->json('message') ?? 'Cursor chat request failed.'
            );
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new AiGenerationException('Cursor returned empty chat content.');
        }

        return $this->parseJsonFromText($content);
    }

    protected function parseJsonFromText(string $text): array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $data = json_decode($text, true);
        if (is_array($data)) {
            return $data;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $data = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($data)) {
                return $data;
            }
        }

        throw new AiGenerationException('Cursor returned invalid JSON.');
    }

    protected function proxyHttp(int $timeout = 90): PendingRequest
    {
        $request = Http::timeout($timeout)->acceptJson();
        $auth = strtolower((string) config('ai.cursor.auth', 'bearer'));

        return match ($auth) {
            'basic' => $request->withBasicAuth($this->apiKey(), ''),
            default => $request->withToken($this->apiKey()),
        };
    }

    protected function apiKey(): ?string
    {
        $key = config('ai.cursor.api_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    protected function chatUrl(): ?string
    {
        $base = rtrim((string) config('ai.cursor.api_base_url', ''), '/');
        if ($base === '' || str_contains(strtolower($base), 'api.cursor.com')) {
            return null;
        }

        return str_ends_with($base, '/chat/completions') ? $base : "{$base}/chat/completions";
    }

    protected function imageUrl(): ?string
    {
        $explicit = trim((string) config('ai.cursor.image_api_url', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $base = rtrim((string) config('ai.cursor.api_base_url', 'https://api.cursor.com/v1'), '/');
        if ($base === '') {
            return null;
        }

        if ($this->imageMode() === 'api') {
            return str_ends_with($base, '/images/generations') ? $base : "{$base}/images/generations";
        }

        if (str_contains(strtolower($base), 'api.cursor.com')) {
            return null;
        }

        return "{$base}/images/generations";
    }

    protected function storeBinary(string $binary, string $ext): string
    {
        if ($binary === '') {
            throw new AiGenerationException('Empty image binary received from Cursor.');
        }

        $dir = 'ai-generated/'.now()->format('Y/m/d');
        Storage::disk('local')->makeDirectory($dir);
        $path = "{$dir}/".Str::uuid().".{$ext}";
        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
