<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="pp-label">Email Address</label>
            <input type="email" name="email" id="email"
                class="pp-input @error('email') is-invalid @enderror"
                value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                placeholder="you@company.com">
            @error('email')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="pp-label">New Password</label>
            <input type="password" name="password" id="password"
                class="pp-input @error('password') is-invalid @enderror"
                required autocomplete="new-password" placeholder="••••••••">
            @error('password')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="pp-label">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                class="pp-input" required autocomplete="new-password" placeholder="••••••••">
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
            <a class="text-sm text-[#004ac6] hover:underline" href="{{ route('login') }}">Back to login</a>
            <button type="submit" class="pp-btn-primary px-6">Reset Password</button>
        </div>
    </form>
</x-guest-layout>
