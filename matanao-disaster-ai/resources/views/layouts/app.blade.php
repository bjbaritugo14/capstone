<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Matanao MDRRMO Assessment System' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @yield('head')
</head>
<body>
    @php
        $role = auth()->user()?->role?->role_name ?? session('role');
        $roleLabel = match ($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'mdrrmo' => 'MDRRMO',
            'validator' => 'Validator',
            'dswd' => 'DSWD',
            'field_officer' => 'Field Officer',
            default => 'Guest',
        };

        $navigation = match ($role) {
            'super_admin', 'admin' => [
                ['label' => 'User Roles', 'route' => 'admin.users', 'pattern' => 'admin.users'],
                ['label' => 'Incident/Data Management', 'route' => 'admin.incident-management', 'pattern' => 'admin.incident-management*'],
                ['label' => 'Recommendation History', 'route' => 'admin.recommendation-history', 'pattern' => 'admin.recommendation-history'],
                ['label' => 'Audit Trail', 'route' => 'admin.audit-trail', 'pattern' => 'admin.audit-trail'],
                ['label' => 'System Settings', 'route' => 'admin.settings', 'pattern' => 'admin.settings'],
            ],
            'mdrrmo' => [
                ['label' => 'Assessment Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard'],
                ['label' => 'Disaster Damage', 'route' => 'reports.index', 'pattern' => 'reports.*'],
                ['label' => 'Affected Families', 'route' => 'affected-families.index', 'pattern' => 'affected-families.*'],
                ['label' => 'Decision Support', 'route' => 'recommendations.index', 'pattern' => 'recommendations.*'],
                ['label' => 'Accidents', 'route' => 'accidents.index', 'pattern' => 'accidents.*'],
                ['label' => 'Validation Queue', 'route' => 'validation.index', 'pattern' => 'validation.*'],
                ['label' => 'Printable Summary', 'route' => 'reports.print', 'pattern' => 'reports.print'],
            ],
            'validator' => [
                ['label' => 'Validation Queue', 'route' => 'validation.index', 'pattern' => 'validation.*'],
            ],
            'field_officer' => [
                ['label' => 'Field Reporting Dashboard', 'route' => 'field-officer.dashboard', 'pattern' => 'field-officer.*'],
            ],
            'dswd' => [
                ['label' => 'DSWD Dashboard', 'route' => 'dswd.dashboard', 'pattern' => 'dswd.dashboard'],
                ['label' => 'Validated Areas GIS', 'route' => 'dswd.validated-areas', 'pattern' => 'dswd.validated-areas'],
                ['label' => 'Affected Families', 'route' => 'affected-families.index', 'pattern' => 'affected-families.*'],
                ['label' => 'Assistance Recommendations', 'route' => 'dswd.recommendations', 'pattern' => 'dswd.recommendations'],
            ],
            default => [],
        };
    @endphp
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-inner">
                <div class="sidebar-top">
                    <div class="brand-block">
                        <div class="brand-mark">M</div>
                        <div>
                            <div class="brand">Matanao MDRRMO</div>
                            <p class="brand-subtitle">Simulated disaster damage assessment system</p>
                        </div>
                    </div>

                    <nav class="nav-links">
                        @foreach ($navigation as $item)
                            <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['pattern']) ? 'active' : '' }}">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                </div>

                <div class="sidebar-footer">
                    <div class="user-card">
                        <div class="avatar">AI</div>
                        <div>
                            <strong>{{ $roleLabel }}</strong>
                            <p>{{ $role }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="logout-link">Logout</button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="page-content">
            @if(session('status'))
                <div class="banner">{{ session('status') }}</div>
            @endif

            @yield('content')
        </main>
    </div>
    @yield('scripts')
</body>
</html>
