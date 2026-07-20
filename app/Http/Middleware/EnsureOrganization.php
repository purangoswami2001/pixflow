<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganization
{
    public function __construct(protected OrganizationContext $organizationContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $organization = $this->organizationContext->resolveForUser($user);

        if (! $organization) {
            abort(403, 'You are not a member of any organization.');
        }

        $request->attributes->set('organization', $organization);
        view()->share('currentOrganization', $organization);

        return $next($request);
    }
}
