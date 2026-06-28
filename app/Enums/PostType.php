<?php

namespace App\Enums;

enum PostType: string
{
    case FACEBOOK_POST = 'facebook_post';
    case INSTAGRAM_POST = 'instagram_post';
    case INSTAGRAM_STORY = 'instagram_story';
    case INSTAGRAM_REEL = 'instagram_reel';
    case PINTEREST_PIN = 'pinterest_pin';
}
