<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Matanao MDRRMO</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-body">
    <section class="auth-shell" style="grid-template-columns: 1fr; max-width: 480px;">
        <div class="card auth-card">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Reset Password</h2>
                    <p class="muted">Create a new password for your account.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="form-grid">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                @if($errors->any())
                    <div class="form-error">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div>
                    <label>Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $email) }}" required>
                </div>
                <div>
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="New password" required>
                    <p class="muted" style="margin-top: 6px; font-size: 12px;">
                        Must contain: uppercase, lowercase, number, and symbol. Minimum 8 characters.
                    </p>
                </div>
                <div>
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirmation" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>

            <div style="margin-top: 16px; text-align: center;">
                <a href="{{ route('login') }}" style="color: var(--primary); font-weight: 700;">← Back to Login</a>
            </div>
        </div>
    </section>
</body>
</html>
