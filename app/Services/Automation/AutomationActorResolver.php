<?php

namespace App\Services\Automation;

use App\Models\AutomationCampaign;
use App\Models\OrganizationMembership;
use App\Models\User;

class AutomationActorResolver
{
    public function forCampaign(AutomationCampaign $campaign): User
    {
        if ($campaign->created_by) {
            $creator = User::query()->find($campaign->created_by);

            if ($creator && $creator->membershipFor($campaign->organization)?->isAdmin()) {
                return $creator;
            }
        }

        $userId = OrganizationMembership::query()
            ->where('organization_id', $campaign->organization_id)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->whereIn('role', [
                OrganizationMembership::ROLE_OWNER,
                OrganizationMembership::ROLE_ADMIN,
            ])
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->value('user_id');

        $user = $userId ? User::query()->find($userId) : null;

        if (! $user) {
            throw new \RuntimeException('Automation needs an active firm owner or administrator.');
        }

        return $user;
    }
}
