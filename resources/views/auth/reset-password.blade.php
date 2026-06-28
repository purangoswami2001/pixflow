<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="d-flex flex-column gap-3">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <label for="email" class="form-label small fw-bold text-muted text-uppercase mb-1">Email Address</label>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            @error('email')
                <div class="invalid-feedback text-xs">{{ $message }}</div>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="form-label small fw-bold text-muted text-uppercase mb-1">New Password</label>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback text-xs">{{ $message }}</div>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="form-label small fw-bold text-muted text-uppercase mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>

        <div class="d-flex justify-content-end mt-2">
            <button type="submit" class="btn btn-primary fw-bold">
                Reset Password
            </button>
        </div>
    </form>
</x-guest-layout>
