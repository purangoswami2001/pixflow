<?php

namespace App\Services\Posts;

use App\Exceptions\AiGenerationException;
use App\Services\Ai\AiConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptionSuggestionService
{
  /**
   * Generate title, caption, and hashtags from a post brief.
   */
  public function suggest(string $brief, string $platform = 'instagram', string $category = 'general'): array
  {
    $brief = trim($brief);

    if ($brief === '') {
      return $this->getTemplateSuggestion('', $platform, $category);
    }

    $apiKey = AiConfig::apiKey();
    if ($apiKey !== null) {
      return match (AiConfig::provider()) {
        'gemini' => $this->getGeminiSuggestion($brief, $platform, $category, $apiKey),
        default => $this->getOpenAiSuggestion($brief, $platform, $category, $apiKey),
      };
    }

    return $this->getTemplateSuggestion($brief, $platform, $category);
  }

  protected function getTemplateSuggestion(string $brief, string $platform, string $category): array
  {
    $title = $this->templateTitle($brief);
    $desc = $brief !== '' ? $brief : 'Check out our latest update!';

    $templates = [
      'instagram' => [
        "✨ {$title} ✨\n\n{$desc}\n\nWhat do you think? Let us know in the comments! 👇",
        "📷 Spotlight: {$title}\n\n{$desc}\n\nDouble tap if you love this! ❤️",
      ],
      'facebook' => [
        "📢 NEW: {$title}\n\n{$desc}\n\nLearn more here or ask us anything! 💬",
        "🎯 Focus on {$title}.\n\n{$desc}\n\nRead more details in the link below. 👇",
      ],
      'pinterest' => [
        "💡 Pin of the Day: {$title}\n\n{$desc}\n\nSave this for later!",
        "📌 {$title}\n\n{$desc}\n\nFollow our board for more ideas!",
      ],
    ];

    $hashtagsMap = [
      'general' => ['#PixFlow', '#socialmedia', '#marketing', '#scheduling'],
      'design' => ['#design', '#inspiration', '#creativity', '#graphicdesign'],
      'marketing' => ['#marketingtips', '#digitalmarketing', '#branding', '#growth'],
      'lifestyle' => ['#lifestyle', '#inspiration', '#dailyvibe', '#trending'],
    ];

    $platformTemplates = $templates[$platform] ?? $templates['instagram'];
    $selectedTemplate = $platformTemplates[array_rand($platformTemplates)];
    $tags = $hashtagsMap[$category] ?? $hashtagsMap['general'];

    return [
      'title' => $title,
      'caption' => $selectedTemplate,
      'hashtags' => $tags,
      'source' => 'template',
    ];
  }

  protected function templateTitle(string $brief): string
  {
    if ($brief === '') {
      return 'New campaign';
    }

    $words = preg_split('/\s+/', $brief, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $title = implode(' ', array_slice($words, 0, 8));

    return mb_strlen($title) > 100 ? mb_substr($title, 0, 97) . '...' : $title;
  }

  protected function buildPrompt(string $brief, string $platform, string $category): string
  {
    $titleHint = $platform === 'pinterest'
      ? 'Pin title (max 100 characters, shown on Pinterest)'
      : 'Internal campaign title (concise, max 100 characters)';

    return "You are a professional social media copywriter. Create content for a {$platform} post.\n\n"
      . "User brief:\n{$brief}\n\n"
      . "Category: {$category}\n\n"
      . "Respond with JSON only using these keys:\n"
      . "- title: {$titleHint}\n"
      . "- caption: engaging post caption with appropriate emojis for {$platform}\n"
      . "- hashtags: array of 4-8 hashtag strings (each must start with #)";
  }

  protected function normalizeAiResult(array $data, string $brief, string $platform, string $category): ?array
  {
    $caption = trim((string) ($data['caption'] ?? ''));
    if ($caption === '') {
      return null;
    }

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
      $title = $this->templateTitle($brief);
    }

    if (mb_strlen($title) > 100) {
      $title = mb_substr($title, 0, 97) . '...';
    }

    $rawHashtags = $data['hashtags'] ?? [];
    if (! is_array($rawHashtags)) {
      $rawHashtags = [];
    }

    $hashtags = array_values(array_filter(array_map(function ($tag) {
      $tag = trim((string) $tag);
      if ($tag === '') {
        return null;
      }

      return str_starts_with($tag, '#') ? $tag : "#{$tag}";
    }, $rawHashtags)));

    return [
      'title' => $title,
      'caption' => $caption,
      'hashtags' => $hashtags,
    ];
  }

  protected function getOpenAiSuggestion(string $brief, string $platform, string $category, string $apiKey): array
  {
    try {
      $response = $this->requestOpenAi($apiKey, $brief, $platform, $category);

      if ($response->status() === 429) {
        $retryAfter = (int) ($response->header('Retry-After') ?: 2);
        sleep(max(1, min($retryAfter, 5)));
        $response = $this->requestOpenAi($apiKey, $brief, $platform, $category);
      }

      if ($response->successful()) {
        $content = $response->json('choices.0.message.content') ?? '{}';
        $data = json_decode($content, true);
        if (is_array($data)) {
          $normalized = $this->normalizeAiResult($data, $brief, $platform, $category);
          if ($normalized !== null) {
            $normalized['source'] = 'openai';

            return $normalized;
          }
        }

        throw new AiGenerationException('OpenAI returned an empty or invalid response. Try again.');
      }

      $this->logProviderFailure('OpenAI', $response);
      throw new AiGenerationException($this->openAiErrorMessage($response), $this->clientStatus($response));
    } catch (AiGenerationException $e) {
      throw $e;
    } catch (\Throwable $e) {
      Log::error('OpenAI caption generation exception: ' . $e->getMessage());
      throw new AiGenerationException('Could not reach OpenAI. Check your network and API key.');
    }
  }

  protected function requestOpenAi(string $apiKey, string $brief, string $platform, string $category): Response
  {
    return Http::withToken($apiKey)
      ->timeout(30)
      ->post('https://api.openai.com/v1/chat/completions', [
        'model' => AiConfig::model(),
        'messages' => [
          ['role' => 'system', 'content' => 'You are a professional social media copywriter. Always respond with valid JSON.'],
          ['role' => 'user', 'content' => $this->buildPrompt($brief, $platform, $category)],
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.7,
        'max_tokens' => 600,
      ]);
  }

  protected function getGeminiSuggestion(string $brief, string $platform, string $category, string $apiKey): array
  {
    try {
      $response = $this->requestGemini($apiKey, $brief, $platform, $category);

      if ($response->status() === 429) {
        sleep(2);
        $response = $this->requestGemini($apiKey, $brief, $platform, $category);
      }

      if ($response->successful()) {
        $text = $response->json('candidates.0.content.parts.0.text');
        if (is_string($text) && $text !== '') {
          $data = json_decode($text, true);
          if (is_array($data)) {
            $normalized = $this->normalizeAiResult($data, $brief, $platform, $category);
            if ($normalized !== null) {
              $normalized['source'] = 'gemini';

              return $normalized;
            }
          }
        }

        throw new AiGenerationException('Gemini returned an empty or invalid response. Try again.');
      }

      $this->logProviderFailure('Gemini', $response);
      throw new AiGenerationException($this->geminiErrorMessage($response), $this->clientStatus($response));
    } catch (AiGenerationException $e) {
      throw $e;
    } catch (\Throwable $e) {
      Log::error('Gemini caption generation exception: ' . $e->getMessage());
      throw new AiGenerationException('Could not reach Gemini. Check your network and API key.');
    }
  }

  protected function requestGemini(string $apiKey, string $brief, string $platform, string $category): Response
  {
    $model = AiConfig::model();
    $apiVersion = AiConfig::geminiApiVersion();
    $url = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent";

    return Http::withHeaders(['x-goog-api-key' => $apiKey])
      ->timeout(30)
      ->post($url, [
        'contents' => [
          [
            'parts' => [
              ['text' => $this->buildPrompt($brief, $platform, $category)],
            ],
          ],
        ],
        'generationConfig' => [
          'responseMimeType' => 'application/json',
          'temperature' => 0.7,
          'maxOutputTokens' => 600,
        ],
      ]);
  }

  protected function openAiErrorMessage(Response $response): string
  {
    $apiMessage = $response->json('error.message');
    $errorCode = $response->json('error.code');

    return match ($response->status()) {
      401 => 'Invalid OpenAI API key. Verify OPENAI_API_KEY in your .env file and run php artisan config:clear.',
      429 => $this->rateLimitMessage('OpenAI', $errorCode, $apiMessage),
      402, 403 => 'OpenAI billing or permission issue. Add credits at platform.openai.com/account/billing.',
      default => is_string($apiMessage) && $apiMessage !== ''
        ? $apiMessage
        : 'OpenAI request failed (HTTP ' . $response->status() . ').',
    };
  }

  protected function geminiErrorMessage(Response $response): string
  {
    $apiMessage = $response->json('error.message');
    $status = $response->status();

    if ($status === 404 || (is_string($apiMessage) && str_contains(strtolower($apiMessage), 'not found'))) {
      return 'Gemini model "' . AiConfig::model() . '" is not available. Set GEMINI_MODEL in .env '
        . '(e.g. gemini-2.5-flash or gemini-3.5-flash) and run php artisan config:clear.';
    }

    return match ($status) {
      400, 401, 403 => is_string($apiMessage) && $apiMessage !== ''
        ? $apiMessage
        : 'Invalid Gemini API key or permission denied. Check GEMINI_API_KEY in your .env file.',
      429 => $this->rateLimitMessage('Gemini', null, $apiMessage),
      default => is_string($apiMessage) && $apiMessage !== ''
        ? $apiMessage
        : 'Gemini request failed (HTTP ' . $status . ').',
    };
  }

  protected function rateLimitMessage(string $provider, ?string $errorCode, mixed $apiMessage): string
  {
    if ($errorCode === 'insufficient_quota' || (is_string($apiMessage) && str_contains(strtolower($apiMessage), 'quota'))) {
      return "{$provider} quota exceeded. Add billing credits on your {$provider} account, or switch to the other provider in AI_PROVIDER.";
    }

    if (is_string($apiMessage) && $apiMessage !== '') {
      return $apiMessage;
    }

    return "{$provider} rate limit reached. Wait a minute and try again.";
  }

  protected function clientStatus(Response $response): int
  {
    $status = $response->status();

    return in_array($status, [401, 402, 403, 429], true) ? $status : 502;
  }

  protected function logProviderFailure(string $provider, Response $response): void
  {
    Log::warning("{$provider} caption generation failed.", [
      'status' => $response->status(),
      'error' => $response->json('error.message') ?? $response->json('error') ?? null,
      'code' => $response->json('error.code'),
    ]);
  }
}
