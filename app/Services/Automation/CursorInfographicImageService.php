<?php

namespace App\Services\Automation;

use App\Exceptions\AiGenerationException;
use App\Services\Ai\CursorApiClient;

class CursorInfographicImageService
{
    public function __construct(
        protected CursorApiClient $cursor,
        protected InfographicRenderer $renderer
    ) {
    }

    public function generate(string $designBrief, int $width, int $height): string
    {
        $layout = $this->resolveLayout($designBrief, $width, $height);
        AutomationGenerationLogger::step('infographic_layout', $layout);

        return $this->renderer->renderToStorage($layout, $width, $height);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveLayout(string $designBrief, int $width, int $height): array
    {
        $system = 'You are a social media infographic layout designer. '
            . 'Follow the niche and tone in the brief — do not force wellness or feminine aesthetics unless the brief implies them. '
            . 'Never mention Canva or other design-tool brands. Return valid JSON only — no markdown.';

        $user = "Convert this design brief into a structured infographic layout for a {$width}x{$height}px canvas.\n\n"
            . "Brief:\n".mb_substr(trim($designBrief), 0, 3500)."\n\n"
            . "Return JSON with exactly these keys:\n"
            . "- title (string, main headline, max 80 chars, may use \\n for two lines)\n"
            . "- subtitle (optional string, tagline under title, max 60 chars)\n"
            . "- sections (array of 3–5 objects, each with: heading string max 40 chars, body string max 110 chars, accent one of: sage, blush, lavender, cream, navy, charcoal, terracotta)\n"
            . "- footer (optional string, short CTA max 70 chars)\n\n"
            . 'Use concise, scannable copy matching the brief. Alternate accent colors across sections.';

        try {
            $data = $this->cursor->chatJson($system, $user);

            return $this->normalizeLayout($data);
        } catch (AiGenerationException $e) {
            AutomationGenerationLogger::step('infographic_layout_fallback', [
                'reason' => $e->getMessage(),
            ]);

            return $this->fallbackLayout($designBrief);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeLayout(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Wellness Tips';
        }

        $sections = [];
        $accents = InfographicPalette::accentCycle();
        $rawSections = is_array($data['sections'] ?? null) ? $data['sections'] : [];

        foreach (array_values($rawSections) as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? ''));
            $body = trim((string) ($section['body'] ?? ''));
            if ($heading === '' && $body === '') {
                continue;
            }

            $accent = strtolower(trim((string) ($section['accent'] ?? $accents[$index % count($accents)])));
            if (! isset(InfographicPalette::COLORS[$accent])) {
                $accent = $accents[$index % count($accents)];
            }

            $sections[] = [
                'heading' => $heading !== '' ? $heading : 'Tip '.($index + 1),
                'body' => $body,
                'accent' => $accent,
            ];
        }

        if ($sections === []) {
            return $this->fallbackLayout($title);
        }

        return [
            'title' => $title,
            'subtitle' => trim((string) ($data['subtitle'] ?? '')),
            'sections' => array_slice($sections, 0, 5),
            'footer' => trim((string) ($data['footer'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fallbackLayout(string $brief): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $brief) ?: [])));
        $title = $lines[0] ?? 'Wellness Tips';
        $sections = [];
        $accents = InfographicPalette::accentCycle();

        foreach (array_slice($lines, 1, 5) as $index => $line) {
            $line = preg_replace('/^[-•*]\s*/', '', $line) ?? $line;
            if (mb_strlen($line) < 8) {
                continue;
            }

            $sections[] = [
                'heading' => mb_strlen($line) > 42 ? mb_substr($line, 0, 39).'…' : $line,
                'body' => '',
                'accent' => $accents[$index % count($accents)],
            ];
        }

        if ($sections === []) {
            $sections[] = [
                'heading' => 'Key takeaway',
                'body' => mb_substr($brief, 0, 110),
                'accent' => 'sage',
            ];
        }

        return [
            'title' => mb_substr($title, 0, 80),
            'subtitle' => '',
            'sections' => $sections,
            'footer' => 'Save for later',
        ];
    }
}
