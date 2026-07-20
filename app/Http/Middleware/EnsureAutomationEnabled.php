<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAutomationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = app(OrganizationContext::class)->organization();

        if (! $organization?->ai_enabled) {
            if ($request->routeIs('admin.automation.index')) {
                return $next($request);
            }

            return redirect()
                ->route('admin.settings')
                ->with('error', 'AI Mode is off for this firm. Turn it on in Firm Settings to use AI features.');
        }

        if ($request->routeIs('admin.automation.index', 'admin.automation.enable')) {
            return $next($request);
        }

        if (! $organization->automation_enabled) {
            return redirect()
                ->route('admin.automation.index')
                ->with('error', 'Turn on AI automation before using this area.');
        }

        return $next($request);
    }
}
