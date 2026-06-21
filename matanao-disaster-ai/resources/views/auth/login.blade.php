<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Matanao MDRRMO Assessment System</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-body">
    <section class="auth-shell">
        <div class="auth-panel auth-overview">
            <div class="pill">Matanao MDRRMO</div>
            <h1>Disaster Damage Assessment and Incident Monitoring System</h1>
            <p class="muted">
                Web-based system for geotagged disaster reporting,
                GIS visualization, assistance recommendations, and vehicular accident monitoring.
            </p>

            <div class="auth-feature-list">
                <div class="feature-card">
                    <strong>Web Report Encoding</strong>
                    <p>Disaster reports and accident incidents with geotagged location and photo documentation.</p>
                </div>
                <div class="feature-card">
                    <strong>Web-GIS Monitoring</strong>
                    <p>Mapped incidents, validation workflow, and barangay-based impact tracking.</p>
                </div>
                <div class="feature-card">
                    <strong>Decision Support</strong>
                    <p>Sample recommendations for cash assistance, food packs, and medicines.</p>
                </div>
            </div>
        </div>

        <div class="card auth-card">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Authorized Login</h2>
                    <p class="muted">Sign in with an active account from the database.</p>
                </div>
                <span class="badge badge-blue">Live Data</span>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="form-grid">
                @csrf
                @if(session('status'))
                    <div class="form-success">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="form-error">{{ $errors->first() }}</div>
                @endif
                <div>
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required>
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
                <div style="text-align: center; margin-top: 4px;">
                    <a href="{{ route('password.request') }}" style="color: var(--primary); font-size: 14px;">Forgot your password?</a>
                </div>
            </form>

            <div class="auth-role-notes">
                <div>
                    <span>MDRRMO</span>
                    <strong>Dashboard, records, recommendations, accidents, and validation</strong>
                </div>
                <div>
                    <span>Super Admin</span>
                    <strong>User roles and study setup pages</strong>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
