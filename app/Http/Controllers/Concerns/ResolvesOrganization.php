<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;
use App\Support\OrganizationContext;

trait ResolvesOrganization
{
    protected function currentOrganization(): Organization
    {
        $organization = app(OrganizationContext::class)->organization()
            ?? request()->attributes->get('organization')
            ?? auth()->user()?->currentOrganization;

        abort_unless($organization instanceof Organization, 403, 'No active organization.');

        return $organization;
    }

    protected function currentOrganizationId(): int
    {
        return $this->currentOrganization()->id;
    }
}
