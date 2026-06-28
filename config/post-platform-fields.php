<?php

/**
 * Platform-specific post fields shown in the create-post wizard (step: Details).
 * Keys are form names under platformFields[...]; api_key is the provider API parameter.
 *
 * Title, caption/description, and hashtags are entered in the main form for all channels.
 */
return [
    'pinterest' => [
        [
            'key' => 'link_url',
            'label' => 'Destination link',
            'type' => 'url',
            'api_key' => 'link',
            'placeholder' => 'https://yoursite.com/page',
            'hint' => 'Where pin clicks go (optional).',
        ],
    ],

    'facebook' => [],

    'instagram' => [],
];
