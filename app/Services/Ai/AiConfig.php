<?php

namespace App\Services\Ai;

class AiConfig
{
  public static function provider(): string
  {
    $provider = strtolower((string) config('ai.provider', 'openai'));

    return in_array($provider, ['openai', 'gemini'], true) ? $provider : 'openai';
  }

  public static function apiKey(): ?string
  {
    $key = config('ai.' . self::provider() . '.api_key');

    return is_string($key) && trim($key) !== '' ? trim($key) : null;
  }

  public static function model(): string
  {
    $provider = self::provider();
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

  public static function isConfigured(): bool
  {
    return self::apiKey() !== null;
  }

  public static function providerLabel(): string
  {
    return self::provider() === 'gemini' ? 'Gemini' : 'OpenAI';
  }
}
