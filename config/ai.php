<?php

return [
  /*
  |--------------------------------------------------------------------------
  | AI caption generation
  |--------------------------------------------------------------------------
  |
  | Provider: "openai" or "gemini". API keys are read from .env only.
  |
  */

  'provider' => env('AI_PROVIDER', 'openai'),

  'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
  ],

  'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'api_version' => env('GEMINI_API_VERSION', 'v1beta'),
    'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
  ],

  /*
  |--------------------------------------------------------------------------
  | Automation text + image providers (comma-separated fallback chains)
  |--------------------------------------------------------------------------
  */

  'text_providers' => array_values(array_filter(array_map(
    'trim',
    explode(',', env('AI_TEXT_PROVIDERS', 'openai,gemini'))
  ))),

  'image_providers' => array_values(array_filter(array_map(
    'trim',
    explode(',', env('AI_IMAGE_PROVIDERS', 'openai,gemini,cursor'))
  ))),

  'openai_image' => [
    'model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),
    'quality' => env('OPENAI_IMAGE_QUALITY', 'standard'),
  ],

  'cursor' => [
    'api_key' => env('CURSOR_API_KEY'),
    'api_base_url' => rtrim((string) env('CURSOR_API_BASE_URL', 'https://api.cursor.com/v1'), '/'),
    'api_mode' => env('CURSOR_API_MODE', 'cloud'),
    'auth' => env('CURSOR_AUTH', 'basic'),
    'chat_model' => env('CURSOR_CHAT_MODEL', 'auto'),
    'model_params' => json_decode((string) env('CURSOR_MODEL_PARAMS', ''), true) ?: [],
    'json_mode' => filter_var(env('CURSOR_JSON_MODE', true), FILTER_VALIDATE_BOOL),
    'poll_timeout' => (int) env('CURSOR_POLL_TIMEOUT', 360),
    'poll_interval' => (int) env('CURSOR_POLL_INTERVAL', 3),
    'archive_agents' => filter_var(env('CURSOR_ARCHIVE_AGENTS', true), FILTER_VALIDATE_BOOL),
    'image_via_artifacts' => filter_var(env('CURSOR_IMAGE_VIA_ARTIFACTS', false), FILTER_VALIDATE_BOOL),
    'image_mode' => strtolower((string) env('CURSOR_IMAGE_MODE', 'agent')),
    'agent_fallback' => filter_var(env('CURSOR_IMAGE_AGENT_FALLBACK', true), FILTER_VALIDATE_BOOL),
    'artifact_image_path' => env('CURSOR_ARTIFACT_IMAGE_PATH', 'artifacts/post-image.png'),
    'artifact_poll_timeout' => (int) env('CURSOR_ARTIFACT_POLL_TIMEOUT', 360),
    'image_agent_poll_timeout' => (int) env('CURSOR_IMAGE_AGENT_POLL_TIMEOUT', 480),
    'image_dimension_retries' => (int) env('CURSOR_IMAGE_DIMENSION_RETRIES', 2),
    'image_api_url' => env('CURSOR_IMAGE_API_URL', ''),
    'image_model' => env('CURSOR_IMAGE_MODEL', ''),
  ],
];
