<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Matanao MDRRMO Assessment System</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-body login-body">
    <section class="auth-shell">
        <div class="auth-panel auth-overview">
            <div class="auth-brand-row">
                <div class="brand-mark auth-brand-mark">M</div>
                <div>
                    <strong>Matanao MDRRMO</strong>
                    <span>Disaster response operations</span>
                </div>
            </div>

            <div>
                <div class="pill auth-pill">Command Center</div>
                <h1>Disaster Damage Assessment and Incident Monitoring System</h1>
                <p class="muted">
                    Secure access for monitoring reports, validation activity, GIS records, and assistance recommendations.
                </p>
            </div>

            <div class="auth-preview" aria-hidden="true">
                <div class="auth-preview-top">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="auth-map-preview">
                    <span class="map-pin pin-one"></span>
                    <span class="map-pin pin-two"></span>
                    <span class="map-pin pin-three"></span>
                </div>
                <div class="auth-preview-stats">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>

            <div class="auth-feature-list">
                <div class="feature-card">
                    <strong>Field Reports</strong>
                    <p>Geotagged disaster and accident records with field validation support.</p>
                </div>
                <div class="feature-card">
                    <strong>GIS Monitoring</strong>
                    <p>Barangay-level map views for incidents, impact areas, and response tracking.</p>
                </div>
                <div class="feature-card">
                    <strong>Decision Support</strong>
                    <p>Assistance recommendations based on validated impact and household data.</p>
                </div>
            </div>
        </div>

        <div class="card auth-card">
            <div class="auth-card-header">
                <div class="auth-lock-mark" aria-hidden="true">
                    <span></span>
                </div>
                <div>
                    <h2>Authorized Login</h2>
                    <p class="muted">Sign in with an active account from the database.</p>
                </div>
                <span class="badge badge-blue">Secure Access</span>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="form-grid auth-login-form">
                @csrf
                @if(session('status'))
                    <div class="form-success">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="form-error">{{ $errors->first() }}</div>
                @endif
                <div class="auth-field">
                    <label for="email">Email address</label>
                    <div class="input-shell">
                        <span class="field-icon" aria-hidden="true">ID</span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" autocomplete="email" required autofocus>
                    </div>
                </div>
                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="input-shell">
                        <span class="field-icon" aria-hidden="true">PW</span>
                        <input id="password" type="password" name="password" placeholder="Password" autocomplete="current-password" required data-password-input>
                        <button type="button" class="password-toggle" data-password-toggle>Show</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary auth-submit">Sign In</button>
                <div class="auth-form-footer">
                    <a href="{{ route('password.request') }}">Forgot your password?</a>
                </div>
            </form>

            <div class="auth-role-notes">
                <div>
                    <span>MDRRMO</span>
                    <strong>Dashboard, records, recommendations, accidents, and validation</strong>
                </div>
                <div>
                    <span>Validator</span>
                    <strong>Browser-based disaster and accident reporting when Expo is unavailable</strong>
                </div>
                <div>
                    <span>Super Admin</span>
                    <strong>User roles, barangay options, and system settings</strong>
                </div>
            </div>
        </div>
    </section>
    <script>
        (() => {
            const input = document.querySelector('[data-password-input]');
            const toggle = document.querySelector('[data-password-toggle]');

            toggle?.addEventListener('click', () => {
                const isHidden = input?.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                toggle.textContent = isHidden ? 'Hide' : 'Show';
            });
        })();
    </script>
</body>
</html>
