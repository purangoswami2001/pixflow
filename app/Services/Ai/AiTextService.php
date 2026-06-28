<?php

namespace App\Services\Ai;

use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiTextService
{
    public function generateJson(string $systemPrompt, string $userPrompt, ?array $providers = null): array
    {
        $providers = $providers ?? config('ai.text_providers', ['openai', 'gemini']);
        $lastError = 'No text AI providers configured.';

        foreach ($providers as $provider) {
            try {
                $result = match ($provider) {
                    'gemini' => $this->geminiJson($systemPrompt, $userPrompt),
                    'openai' => $this->openAiJson($systemPrompt, $userPrompt),
                    'cursor' => app(CursorApiClient::class)->chatJson($systemPrompt, $userPrompt),
                    default => null,
                };

                if (is_array($result)) {
                    $result['_provider'] = $provider;

                    return $result;
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("AiTextService: {$provider} failed", ['error' => $lastError]);
            }
        }

        throw new AiGenerationException($lastError);
    }

    public function generateText(string $systemPrompt, string $userPrompt, ?array $providers = null): string
    {
        $data = $this->generateJson($systemPrompt, $userPrompt, $providers);
        unset($data['_provider']);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function openAiJson(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = config('ai.openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiGenerationException('OpenAI API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('ai.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'max_tokens' => 2500,
            ]);

        if (! $response->successful()) {
            throw new AiGenerationException($response->json('error.message') ?? 'OpenAI request failed.');
        }

        $content = $response->json('choices.0.message.content') ?? '{}';
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new AiGenerationException('OpenAI returned invalid JSON.');
        }

        return $data;
    }

    protected function geminiJson(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = config('ai.gemini.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiGenerationException('Gemini API key is not configured.');
        }

        $model = config('ai.gemini.model', 'gemini-2.5-flash');
        $apiVersion = config('ai.gemini.api_version', 'v1beta');
        $url = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent";

        $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
            ->timeout(90)
            ->post($url, [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [
                    ['parts' => [['text' => $userPrompt]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2500,
                ],
            ]);

        if (! $response->successful()) {
            throw new AiGenerationException($response->json('error.message') ?? 'Gemini request failed.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($data)) {
            throw new AiGenerationException('Gemini returned invalid JSON.');
        }

        return $data;
    }
}
