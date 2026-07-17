<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload limits (KB) — keep in sync with UI copy and PHP limits
    |--------------------------------------------------------------------------
    |
    | PHP must allow at least this size (upload_max_filesize / post_max_size).
    | WAMP: edit bin/php/phpX.Y/php.ini. Shared hosting: public/.user.ini.
    |
    */

    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 10240),
    'max_upload_files' => (int) env('MEDIA_MAX_UPLOAD_FILES', 10),

    /*
    |--------------------------------------------------------------------------
    | Canva-style embedded image metadata (automation exports)
    |--------------------------------------------------------------------------
    */

    'canva' => [
        'author' => env('CANVA_METADATA_AUTHOR', 'Puran Goswami'),
        'title' => env('CANVA_METADATA_TITLE', 'Untitled design - 1'),
        'file_name' => env('CANVA_METADATA_FILE_NAME', 'Untitled design.png'),
        'doc_id' => env('CANVA_METADATA_DOC_ID'),
        'user_id' => env('CANVA_METADATA_USER_ID'),
        'brand_id' => env('CANVA_METADATA_BRAND_ID'),
        'fb_id' => env('CANVA_METADATA_FB_ID'),
        'touch_type' => (int) env('CANVA_METADATA_TOUCH_TYPE', 2),
    ],

];
