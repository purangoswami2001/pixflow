<x-guest-layout>
    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Join {{ $invitation->organization->name }}</h1>
            <p class="mt-2 text-sm text-slate-600">
                You were invited as <strong>{{ \App\Models\OrganizationMembership::roleLabel($invitation->role) }}</strong>
                for <strong>{{ $invitation->email }}</strong>.
            </p>
        </div>

        @auth
            @if(strcasecmp(auth()->user()->email, $invitation->email) === 0)
                <form method="POST" action="{{ route('organizations.invitations.accept', $invitation->token) }}">
                    @csrf
                    <button type="submit" class="pp-btn-primary">Accept invitation</button>
                </form>
            @else
                <p class="text-sm text-amber-700">
                    You are signed in as {{ auth()->user()->email }}. Sign in with {{ $invitation->email }} to accept.
                </p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="pp-btn-primary">Sign out</button>
                </form>
            @endif
        @else
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="pp-btn-primary">Log in to accept</a>
                <a href="{{ route('register', ['invitation' => $invitation->token]) }}" class="text-sm text-[#004ac6] hover:underline self-center">Create an account</a>
            </div>
        @endauth
    </div>
</x-guest-layout>
