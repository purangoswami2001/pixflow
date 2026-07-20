<?php

namespace App\Services\Ai;

class AiConfig
{
  public static function provider(): string
  {
    $provider = strtolower((string) config('ai.provider', 'openai'));

    return in_array($provider, ['openai', 'gemini', 'cursor'], true) ? $provider : 'openai';
  }

  public static function apiKey(): ?string
  {
    $provider = self::provider();

    if ($provider === 'cursor') {
      $key = config('ai.cursor.api_key');

      return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    $key = config('ai.' . $provider . '.api_key');

    return is_string($key) && trim($key) !== '' ? trim($key) : null;
  }

  public static function model(): string
  {
    $provider = self::provider();

    if ($provider === 'cursor') {
      $model = trim((string) config('ai.cursor.chat_model', 'auto'));

      return $model !== '' ? $model : 'auto';
    }

    $model = trim((string) config('ai.' . $provider . '.model'));
    $model = preg_replace('#^models/#', '', $model) ?: '';

    if ($model !== '') {
      return $model;
    }

    return $provider === 'gemini' ? 'gemini-2.5-flash' : 'gpt-4o-mini';
  }

  public static function geminiApiVersion(): string
  {
    $version = strtolower(trim((string) config('ai.gemini.api_version', 'v1beta')));

    return in_array($version, ['v1', 'v1beta'], true) ? $version : 'v1beta';
  }

  /**
   * True when any platform AI backend is ready (manual captions or automation).
   */
  public static function isConfigured(): bool
  {
    if (self::apiKey() !== null) {
      return true;
    }

    // Automation may use Cursor even when AI_PROVIDER is still openai/gemini.
    if (filled(config('ai.cursor.api_key'))) {
      return true;
    }

    if (filled(config('ai.openai.api_key')) || filled(config('ai.gemini.api_key'))) {
      return true;
    }

    return false;
  }

  /**
   * Prefer the caption provider when keyed; otherwise surface the automation/Cursor backend.
   */
  public static function providerLabel(): string
  {
    if (self::apiKey() !== null) {
      return match (self::provider()) {
        'gemini' => 'Gemini',
        'cursor' => 'Cursor',
        default => 'OpenAI',
      };
    }

    if (filled(config('ai.cursor.api_key'))) {
      return 'Cursor';
    }

    if (filled(config('ai.gemini.api_key'))) {
      return 'Gemini';
    }

    if (filled(config('ai.openai.api_key'))) {
      return 'OpenAI';
    }

    return 'AI';
  }

  /**
   * Providers to try for ad-hoc text generation (captions, etc.).
   *
   * @return list<string>
   */
  public static function textProviders(): array
  {
    $providers = [];

    if (self::apiKey() !== null && self::provider() !== 'cursor') {
      $providers[] = self::provider();
    }

    if (filled(config('ai.cursor.api_key'))) {
      $providers[] = 'cursor';
    }

    if (filled(config('ai.openai.api_key')) && ! in_array('openai', $providers, true)) {
      $providers[] = 'openai';
    }

    if (filled(config('ai.gemini.api_key')) && ! in_array('gemini', $providers, true)) {
      $providers[] = 'gemini';
    }

    return array_values(array_unique($providers));
  }
}
