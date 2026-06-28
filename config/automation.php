<?php

return [
    'enabled' => env('AUTOMATION_ENABLED', true),

    'ai_agent_email' => env('AUTOMATION_AI_AGENT_EMAIL', 'ai-agent@pixflow.local'),

    'max_posts_per_run' => (int) env('AUTOMATION_MAX_POSTS_PER_RUN', 10),

    'image_daily_limit' => (int) env('AUTOMATION_IMAGE_DAILY_LIMIT', 50),

    'topic_cooldown_days' => (int) env('AUTOMATION_TOPIC_COOLDOWN_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | AI provider for automation (idea, copy, image)
    |--------------------------------------------------------------------------
    |
    | openai, gemini, or cursor — uses only that provider's API key (no fallback chain).
    |
    */

    'ai_provider' => strtolower(trim((string) env('AUTOMATION_AI_PROVIDER', env('AI_PROVIDER', 'gemini')))),

    /*
    |--------------------------------------------------------------------------
    | Image provider override (optional)
    |--------------------------------------------------------------------------
    |
    | openai, gemini, or cursor. Same provider is used for all platforms unless
    | AUTOMATION_IMAGE_STRICT_CURSOR=true (cursor even when Gemini/OpenAI keys exist).
    |
    */

    'image_provider' => strtolower(trim((string) env('AUTOMATION_IMAGE_PROVIDER', ''))),

    /*
    |--------------------------------------------------------------------------
    | Safety filter & prompt rules
    |--------------------------------------------------------------------------
    |
    | When false (default), captions are not blocked for words like "cure" and
    | category safety_rules are not injected into AI prompts.
    |
    */

    'safety_enabled' => env('AUTOMATION_SAFETY_ENABLED', false),

    'default_presets' => [
        'pinterest' => 'pinterest_pin',
        'facebook' => 'facebook_flexible',
        'instagram' => 'instagram_portrait',
    ],

    'default_image_styles' => [
        'pinterest' => 'infographic',
        'facebook' => 'infographic',
        'instagram' => 'infographic',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local infographic font (Cursor layout mode)
    |--------------------------------------------------------------------------
    |
    | Optional path to a .ttf file. When empty, Windows/Mac/Linux system fonts
    | are auto-detected for rendering Cursor-generated infographic layouts.
    |
    */

    'infographic_font' => env('AUTOMATION_INFOGRAPHIC_FONT', ''),
];
