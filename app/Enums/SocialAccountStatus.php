<?php

namespace App\Enums;

enum SocialAccountStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case ERROR = 'error';
    case DISCONNECTED = 'disconnected';
}
