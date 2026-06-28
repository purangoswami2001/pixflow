<?php

namespace App\Enums;

enum DestinationType: string
{
    case FACEBOOK_PAGE = 'facebook_page';
    case INSTAGRAM_ACCOUNT = 'instagram_account';
    case PINTEREST_BOARD = 'pinterest_board';
}
