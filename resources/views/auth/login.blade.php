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

        <div class="flex flex-col gap-3 pt-2">
            <button type="submit" class="pp-btn-primary w-full justify-center py-2.5">
                Log in
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
            @if (Route::has('register'))
                <a class="text-sm text-center text-[#004ac6] hover:underline" href="{{ route('register') }}">Register your firm</a>
            @endif
            @if (Route::has('password.request'))
                <a class="text-sm text-center text-[#004ac6] hover:underline" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>
    </form>
</x-guest-layout>
