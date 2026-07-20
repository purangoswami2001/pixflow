<?php

namespace App\Policies;

use App\Models\OrganizationMembership;
use App\Models\User;

class OrganizationMembershipPolicy
{
    public function invite(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, OrganizationMembership $membership): bool
    {
        return $user->belongsToOrganization($membership->organization_id) && $user->isAdmin();
    }

    public function remove(User $user, OrganizationMembership $membership): bool
    {
        return $user->belongsToOrganization($membership->organization_id) && $user->isAdmin();
    }
}
