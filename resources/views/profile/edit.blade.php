<x-app-layout>
    <div class="mb-4">
        <h1 class="h3 fw-bold text-dark mb-1">Account Profile Settings</h1>
        <p class="text-muted mb-0 small">Update your name, email, account password, or delete your registration.</p>
    </div>

    <div class="row g-4" style="max-width: 700px;">
        <!-- Card 1: Profile Info -->
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4">
                <h4 class="h5 fw-bold text-dark mb-1">Profile Information</h4>
                <p class="text-muted small mb-4">Update your account's profile name and email address.</p>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-3">
                    @csrf
                    @method('patch')

                    <div class="mb-3">
                        <label for="profile_name" class="form-label small fw-bold text-muted text-uppercase mb-1">Name</label>
                        <input type="text" name="name" id="profile_name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', auth()->user()->name) }}" required autocomplete="name">
                        @error('name')
                            <div class="invalid-feedback text-xs">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="profile_email" class="form-label small fw-bold text-muted text-uppercase mb-1">Email Address</label>
                        <input type="email" name="email" id="profile_email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', auth()->user()->email) }}" required autocomplete="username">
                        @error('email')
                            <div class="invalid-feedback text-xs">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Card 2: Password Update -->
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4">
                <h4 class="h5 fw-bold text-dark mb-1">Update Password</h4>
                <p class="text-muted small mb-4">Ensure your account is using a long, random password to stay secure.</p>

                <form method="POST" action="{{ route('password.update') }}" class="space-y-3">
                    @csrf
                    @method('put')

                    <div class="mb-3">
                        <label for="update_password_current_password" class="form-label small fw-bold text-muted text-uppercase mb-1">Current Password</label>
                        <input type="password" name="current_password" id="update_password_current_password" class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" autocomplete="current-password">
                        @error('current_password', 'updatePassword')
                            <div class="invalid-feedback text-xs">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="update_password_password" class="form-label small fw-bold text-muted text-uppercase mb-1">New Password</label>
                        <input type="password" name="password" id="update_password_password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" autocomplete="new-password">
                        @error('password', 'updatePassword')
                            <div class="invalid-feedback text-xs">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="update_password_password_confirmation" class="form-label small fw-bold text-muted text-uppercase mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="update_password_password_confirmation" class="form-control" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold">Update Password</button>
                </form>
            </div>
        </div>

        <!-- Card 3: Danger Zone / Delete -->
        <div class="col-12">
            <div class="card border border-danger-subtle shadow-sm p-4 bg-danger-subtle bg-opacity-10">
                <h4 class="h5 fw-bold text-danger mb-1">Delete Account</h4>
                <p class="text-muted small mb-4">Once your account is deleted, all of its resources and post history data will be permanently deleted.</p>

                <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-3"
                    data-pp-confirm="Your account and all associated data will be permanently deleted. This cannot be undone."
                    data-pp-confirm-title="Delete your account?"
                    data-pp-confirm-variant="danger"
                    data-pp-confirm-button="Delete account">
                    @csrf
                    @method('delete')

                    <div class="mb-3">
                        <label for="delete_account_password" class="form-label small fw-bold text-muted text-uppercase mb-1">Verify Password</label>
                        <input type="password" name="password" id="delete_account_password" placeholder="Confirm password to delete..." class="form-control @error('password', 'userDeletion') is-invalid @enderror" required>
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback text-xs">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-danger fw-bold">Delete Account</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
