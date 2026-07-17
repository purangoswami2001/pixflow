<x-guest-layout>
    <p class="text-sm text-[#434655] mb-5 leading-relaxed">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <label for="password" class="pp-label">{{ __('Password') }}</label>
            <input type="password" name="password" id="password"
                class="pp-input @error('password') is-invalid @enderror"
                required autocomplete="current-password" placeholder="••••••••">
            @error('password')
                <p class="pp-field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="pp-btn-primary px-6">{{ __('Confirm') }}</button>
        </div>
    </form>
</x-guest-layout>
