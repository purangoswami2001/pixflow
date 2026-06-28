<x-guest-layout>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="pp-label">Email address</label>
            <input type="email" name="email" id="email" class="pp-input @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com">
            @error('email')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="pp-label">Password</label>
            <input type="password" name="password" id="password" class="pp-input @error('password') is-invalid @enderror" required autocomplete="current-password" placeholder="••••••••">
            @error('password')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 cursor-pointer text-sm text-[#434655]">
            <input type="checkbox" name="remember" id="remember_me" class="rounded border-[#c3c6d7] text-[#004ac6]">
            <span>Remember me</span>
        </label>

        <div class="flex items-center justify-between gap-3 pt-2">
            @if (Route::has('password.request'))
                <a class="text-sm text-[#004ac6] hover:underline" href="{{ route('password.request') }}">Forgot password?</a>
            @else
                <span></span>
            @endif
            <button type="submit" class="pp-btn-primary px-6">Log in</button>
        </div>
    </form>
</x-guest-layout>
