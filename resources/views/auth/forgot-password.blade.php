<x-guest-layout>
    <p class="text-sm text-[#434655] mb-5 leading-relaxed">
        Forgot your password? Enter your email and we will send you a reset link.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="pp-label">Email address</label>
            <input type="email" name="email" id="email" class="pp-input @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus placeholder="you@company.com">
            @error('email')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-between items-center gap-3 pt-2">
            <a class="text-sm text-[#004ac6] hover:underline" href="{{ route('login') }}">Back to login</a>
            <button type="submit" class="pp-btn-primary">Send reset link</button>
        </div>
    </form>
</x-guest-layout>
