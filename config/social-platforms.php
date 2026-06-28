<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Social Platforms Config
    |--------------------------------------------------------------------------
    |
    | Defines supported providers, authentication scopes, API parameters,
    | image presets, and character limits.
    |
    */

    'mock_mode' => env('SOCIAL_MOCK_MODE', true),

    'platforms' => [
        'facebook' => [
            'name' => 'Facebook',
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
            'redirect_uri' => env('FACEBOOK_REDIRECT_URI'),
            'sandbox' => env('FACEBOOK_SANDBOX', false),
            'api_version' => 'v20.0',
            'api_url' => 'https://graph.facebook.com',
            'scopes' => array_values(array_filter(array_map(
                'trim',
                explode(',', env(
                    'FACEBOOK_OAUTH_SCOPES',
                    'public_profile,pages_show_list,pages_read_engagement,business_management'
                ))
            ))),
            'publish_scopes' => ['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'],
            'post_types' => [
                'text' => 'Text Post',
                'image' => 'Image Post',
                'link' => 'Link Post',
            ],
            'max_caption_length' => 63206,
            'caption_warning_threshold' => 5000,
        ],

        'instagram' => [
            'name' => 'Instagram',
            'client_id' => env('INSTAGRAM_CLIENT_ID'),
            'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
            'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),
            'sandbox' => env('INSTAGRAM_SANDBOX', false),
            'api_version' => 'v20.0',
            'api_url' => 'https://graph.facebook.com',
            'scopes' => array_values(array_filter(array_map(
                'trim',
                explode(',', env(
                    'INSTAGRAM_OAUTH_SCOPES',
                    'public_profile,instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement,business_management'
                ))
            ))),
            'publish_scopes' => ['instagram_content_publish', 'instagram_basic', 'pages_show_list'],
            'post_types' => [
                'image' => 'Single Image Post',
            ],
            'max_caption_length' => 2200,
            'caption_warning_threshold' => 2000,
        ],

        'pinterest' => [
            'name' => 'Pinterest',
            'client_id' => env('PINTEREST_CLIENT_ID'),
            'client_secret' => env('PINTEREST_CLIENT_SECRET'),
            'redirect_uri' => env('PINTEREST_REDIRECT_URI'),
            'api_version' => 'v5',
            'api_url' => env('PINTEREST_API_URL', 'https://api.pinterest.com'),
            'sandbox' => env('PINTEREST_SANDBOX', true),
            'scopes' => [
                'user_accounts:read',
                'boards:read',
                'boards:write',
                'pins:read',
                'pins:write',
            ],
            'post_types' => [
                'pin' => 'Pinterest Pin',
            ],
            'max_caption_length' => 500, // Description limit
            'title_limit' => 100,
            'caption_warning_threshold' => 450,
        ],
    ],

    /*
    | Channel → media type → available size presets for upload & post filtering.
    */
    'channel_media' => [
        'facebook' => [
            'icon' => 'face',
            'color' => 'primary',
            'types' => [
                'image' => ['presets' => ['facebook_flexible', 'facebook_square']],
                'video' => ['presets' => []],
            ],
        ],
        'instagram' => [
            'icon' => 'photo_camera',
            'color' => 'danger',
            'types' => [
                'image' => ['presets' => ['instagram_square', 'instagram_portrait']],
                'video' => ['presets' => ['instagram_story']],
            ],
        ],
        'pinterest' => [
            'icon' => 'push_pin',
            'color' => 'danger',
            'types' => [
                'image' => ['presets' => ['pinterest_pin']],
                'video' => ['presets' => []],
            ],
        ],
    ],

    'presets' => [
        'facebook_flexible' => [
            'name' => 'Facebook Flexible (1200x630)',
            'width' => 1200,
            'height' => 630,
            'aspect_ratio' => '1.91:1',
        ],
        'facebook_square' => [
            'name' => 'Facebook Square (1200x1200)',
            'width' => 1200,
            'height' => 1200,
            'aspect_ratio' => '1:1',
        ],
        'instagram_square' => [
            'name' => 'Instagram Square (1080x1080)',
            'width' => 1080,
            'height' => 1080,
            'aspect_ratio' => '1:1',
        ],
        'instagram_portrait' => [
            'name' => 'Instagram Portrait (1080x1350)',
            'width' => 1080,
            'height' => 1350,
            'aspect_ratio' => '4:5',
        ],
        'instagram_story' => [
            'name' => 'Instagram Story/Reel (1080x1920)',
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
        ],
        'pinterest_pin' => [
            'name' => 'Pinterest Pin (1000x1500)',
            'width' => 1000,
            'height' => 1500,
            'aspect_ratio' => '2:3',
        ],
    ],
];
