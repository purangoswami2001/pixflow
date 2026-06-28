<?php

return [

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
