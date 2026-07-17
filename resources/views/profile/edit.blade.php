<x-app-layout>
    <x-pp-page-header
        title="Account Settings"
        description="Update your name, email, and password, or delete your account.">
        <x-slot name="breadcrumb">
            <span>Account</span><span>/</span><span class="text-[#004ac6]">Profile</span>
        </x-slot>
    </x-pp-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Profile information --}}
        <div class="pp-card p-6 lg:col-span-3 xl:col-span-3">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-9 h-9 rounded-lg bg-[#004ac6]/10 text-[#004ac6] flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                        </div>
                        <h2 class="font-display text-base font-semibold text-[#131b2e]">Profile Information</h2>
                    </div>
                    <p class="text-sm text-[#737686]">Update your account's name and email address.</p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('patch')

                    <div>
                        <label for="profile_name" class="pp-label">Name</label>
                        <input type="text" name="name" id="profile_name"
                            class="pp-input @error('name') is-invalid @enderror"
                            value="{{ old('name', auth()->user()->name) }}" required autocomplete="name">
                        @error('name')
                            <p class="pp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="profile_email" class="pp-label">Email Address</label>
                        <input type="email" name="email" id="profile_email"
                            class="pp-input @error('email') is-invalid @enderror"
                            value="{{ old('email', auth()->user()->email) }}" required autocomplete="username">
                        @error('email')
                            <p class="pp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    @if (session('status') === 'profile-updated')
                        <div class="pp-alert-success">Profile saved.</div>
                    @endif

                    <div class="pt-1">
                        <button type="submit" class="pp-btn-primary">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Appearance --}}
        <div class="pp-card p-6 lg:col-span-3 xl:col-span-3">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-9 h-9 rounded-lg bg-[#004ac6]/10 text-[#004ac6] flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">palette</span>
                        </div>
                        <h2 class="font-display text-base font-semibold text-[#131b2e]">Appearance</h2>
                    </div>
                    <p class="text-sm text-[#737686]">Choose light, dark, or match your device setting. Applies across all pages after login.</p>
                </div>

                <div>
                    @php $currentTheme = auth()->user()->themePreference(); @endphp
                    <div class="pp-theme-picker" role="radiogroup" aria-label="Theme preference">
                        <button type="button"
                                class="pp-theme-option {{ $currentTheme === 'light' ? 'is-active' : '' }}"
                                data-theme-option="light"
                                onclick="window.ppSetTheme('light')"
                                aria-pressed="{{ $currentTheme === 'light' ? 'true' : 'false' }}">
                            <span class="material-symbols-outlined">light_mode</span>
                            <span>Light</span>
                        </button>
                        <button type="button"
                                class="pp-theme-option {{ $currentTheme === 'dark' ? 'is-active' : '' }}"
                                data-theme-option="dark"
                                onclick="window.ppSetTheme('dark')"
                                aria-pressed="{{ $currentTheme === 'dark' ? 'true' : 'false' }}">
                            <span class="material-symbols-outlined">dark_mode</span>
                            <span>Dark</span>
                        </button>
                        <button type="button"
                                class="pp-theme-option {{ $currentTheme === 'system' ? 'is-active' : '' }}"
                                data-theme-option="system"
                                onclick="window.ppSetTheme('system')"
                                aria-pressed="{{ $currentTheme === 'system' ? 'true' : 'false' }}">
                            <span class="material-symbols-outlined">contrast</span>
                            <span>System</span>
                        </button>
                    </div>
                    @if (session('status') === 'theme-updated')
                        <div class="pp-alert-success mt-4">Theme preference saved.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Password --}}
        <div class="pp-card p-6 lg:col-span-3 xl:col-span-3">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-9 h-9 rounded-lg bg-[#4648d4]/10 text-[#4648d4] flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </div>
                        <h2 class="font-display text-base font-semibold text-[#131b2e]">Update Password</h2>
                    </div>
                    <p class="text-sm text-[#737686]">Use a long, random password to keep your account secure.</p>
                </div>

                <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    @method('put')

                    <div>
                        <label for="update_password_current_password" class="pp-label">Current Password</label>
                        <input type="password" name="current_password" id="update_password_current_password"
                            class="pp-input @error('current_password', 'updatePassword') is-invalid @enderror"
                            autocomplete="current-password">
                        @error('current_password', 'updatePassword')
                            <p class="pp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="update_password_password" class="pp-label">New Password</label>
                        <input type="password" name="password" id="update_password_password"
                            class="pp-input @error('password', 'updatePassword') is-invalid @enderror"
                            autocomplete="new-password">
                        @error('password', 'updatePassword')
                            <p class="pp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="update_password_password_confirmation" class="pp-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="update_password_password_confirmation"
                            class="pp-input" autocomplete="new-password">
                    </div>

                    @if (session('status') === 'password-updated')
                        <div class="pp-alert-success">Password saved.</div>
                    @endif

                    <div class="pt-1">
                        <button type="submit" class="pp-btn-primary">
                            <span class="material-symbols-outlined text-[18px]">key</span>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Danger zone --}}
        <div class="pp-card p-6 lg:col-span-3 xl:col-span-3 border-[#ba1a1a]/30 bg-[#ba1a1a]/[0.02]">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-9 h-9 rounded-lg bg-[#ba1a1a]/10 text-[#ba1a1a] flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">warning</span>
                        </div>
                        <h2 class="font-display text-base font-semibold text-[#ba1a1a]">Delete Account</h2>
                    </div>
                    <p class="text-sm text-[#737686]">Once deleted, all of your posts and history are permanently removed. This cannot be undone.</p>
                </div>

                <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-4"
                    data-pp-confirm="Your account and all associated data will be permanently deleted. This cannot be undone."
                    data-pp-confirm-title="Delete your account?"
                    data-pp-confirm-variant="danger"
                    data-pp-confirm-button="Delete account">
                    @csrf
                    @method('delete')

                    <div>
                        <label for="delete_account_password" class="pp-label">Verify Password</label>
                        <input type="password" name="password" id="delete_account_password"
                            placeholder="Confirm password to delete…"
                            class="pp-input @error('password', 'userDeletion') is-invalid @enderror" required>
                        @error('password', 'userDeletion')
                            <p class="pp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-1">
                        <button type="submit" class="pp-btn-primary pp-btn-danger">
                            <span class="material-symbols-outlined text-[18px]">delete_forever</span>
                            Delete Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
