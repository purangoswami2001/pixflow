<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Services\Organizations\OrganizationService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function switch(Request $request, Organization $organization, OrganizationService $organizations, OrganizationContext $context): RedirectResponse
    {
        abort_unless($request->user()->belongsToOrganization($organization), 403);

        $organizations->switchOrganization($request->user(), $organization);
        $context->set($organization);

        return redirect()->route('dashboard')->with('status', "Switched to {$organization->name}.");
    }

    public function showInvitation(string $token): View|RedirectResponse
    {
        $invitation = OrganizationInvitation::query()
            ->with('organization')
            ->where('token', $token)
            ->firstOrFail();

        if (! $invitation->isPending()) {
            return redirect()->route('login')->with('error', 'This invitation is no longer valid.');
        }

        return view('organizations.accept-invitation', [
            'invitation' => $invitation,
        ]);
    }

    public function acceptInvitation(Request $request, string $token, OrganizationService $organizations): RedirectResponse
    {
        $invitation = OrganizationInvitation::query()
            ->with('organization')
            ->where('token', $token)
            ->firstOrFail();

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->with('status', 'Log in or register with the invited email to accept this invitation.');
        }

        $organizations->acceptInvitation($invitation, $user);
        $organizations->switchOrganization($user, $invitation->organization);

        return redirect()->route('dashboard')->with('status', "You joined {$invitation->organization->name}.");
    }
}
