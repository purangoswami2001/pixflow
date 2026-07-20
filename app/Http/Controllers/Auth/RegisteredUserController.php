<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Services\Organizations\OrganizationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $invitation = null;

        if ($request->filled('invitation')) {
            $invitation = OrganizationInvitation::query()
                ->with('organization')
                ->where('token', $request->string('invitation'))
                ->first();
        }

        return view('auth.register', [
            'invitation' => $invitation?->isPending() ? $invitation : null,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, OrganizationService $organizations): RedirectResponse
    {
        $joiningViaInvitation = $request->filled('invitation_token');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_name' => [$joiningViaInvitation ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invitation_token' => ['nullable', 'string'],
        ]);

        $user = $organizations->registerFirm([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'organization_name' => $request->organization_name,
            'invitation_token' => $request->invitation_token,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard'));
    }
}
