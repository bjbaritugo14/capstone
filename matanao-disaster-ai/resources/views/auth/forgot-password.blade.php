<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Matanao MDRRMO</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-body">
    <section class="auth-shell" style="grid-template-columns: 1fr; max-width: 480px;">
        <div class="card auth-card">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Forgot Password</h2>
                    <p class="muted">Enter your email address and we'll send you a link to reset your password.</p>
                </div>
            </div>

            @if(session('status'))
                <div class="form-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="form-grid">
                @csrf
                @if($errors->any())
                    <div class="form-error">{{ $errors->first() }}</div>
                @endif
                <div>
                    <label>Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="your@email.com" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Send Reset Link</button>
            </form>

            <div style="margin-top: 16px; text-align: center;">
                <a href="{{ route('login') }}" style="color: var(--primary); font-weight: 700;">← Back to Login</a>
            </div>
        </div>
    </section>
</body>
</html>
