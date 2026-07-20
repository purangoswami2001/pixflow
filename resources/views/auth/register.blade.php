<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        @if(!empty($invitation))
            <input type="hidden" name="invitation_token" value="{{ $invitation->token }}">
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                Joining <strong>{{ $invitation->organization->name }}</strong>
                as {{ \App\Models\OrganizationMembership::roleLabel($invitation->role) }}.
            </div>
        @else
            <div>
                <label for="organization_name" class="pp-label">Firm / organization name</label>
                <input type="text" name="organization_name" id="organization_name" class="pp-input @error('organization_name') is-invalid @enderror" value="{{ old('organization_name') }}" required autofocus autocomplete="organization">
                @error('organization_name')
                    <p class="pp-field-error">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div>
            <label for="name" class="pp-label">Your full name</label>
            <input type="text" name="name" id="name" class="pp-input @error('name') is-invalid @enderror" value="{{ old('name') }}" required autocomplete="name" @if(!empty($invitation)) autofocus @endif>
            @error('name')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="pp-label">Work email</label>
            <input type="email" name="email" id="email" class="pp-input @error('email') is-invalid @enderror" value="{{ old('email', $invitation->email ?? '') }}" required autocomplete="username" placeholder="you@company.com" @if(!empty($invitation)) readonly @endif>
            @error('email')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="pp-label">Password</label>
            <input type="password" name="password" id="password" class="pp-input @error('password') is-invalid @enderror" required autocomplete="new-password">
            @error('password')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="pp-label">Confirm password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="pp-input" required autocomplete="new-password">
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
            <a class="text-sm text-[#004ac6] hover:underline" href="{{ route('login') }}">Already registered?</a>
            <button type="submit" class="pp-btn-primary">{{ !empty($invitation) ? 'Join firm' : 'Create firm' }}</button>
        </div>
    </form>
</x-guest-layout>
