<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="pp-label">Full name</label>
            <input type="text" name="name" id="name" class="pp-input @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="pp-label">Email address</label>
            <input type="email" name="email" id="email" class="pp-input @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="username" placeholder="you@company.com">
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
            <button type="submit" class="pp-btn-primary">Register</button>
        </div>
    </form>
</x-guest-layout>
