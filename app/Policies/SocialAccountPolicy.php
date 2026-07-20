<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;

class SocialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function connect(User $user): bool
    {
        return $user->isAdmin();
    }

    public function manage(User $user, SocialAccount $account): bool
    {
        return $user->belongsToOrganization($account->organization_id) && $user->isAdmin();
    }
}
