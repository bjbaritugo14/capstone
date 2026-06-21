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
        $role = session('role');
        $roleLabel = match ($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'mdrrmo' => 'MDRRMO',
            'dswd' => 'DSWD',
            default => 'Guest',
        };

        $navigation = match ($role) {
            'super_admin', 'admin' => [
                ['label' => 'User Roles', 'route' => 'admin.users', 'pattern' => 'admin.users'],
                ['label' => 'Incident/Data Management', 'route' => 'admin.incident-management', 'pattern' => 'admin.incident-management*'],
                ['label' => 'Recommendation Generator', 'route' => 'admin.recommendations.index', 'pattern' => 'admin.recommendations.*'],
                ['label' => 'Recommendation History', 'route' => 'admin.recommendation-history', 'pattern' => 'admin.recommendation-history'],
                ['label' => 'Study Setup', 'route' => 'admin.settings', 'pattern' => 'admin.settings'],
            ],
            'mdrrmo' => [
                ['label' => 'Assessment Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard'],
                ['label' => 'Disaster Damage Module', 'route' => 'reports.index', 'pattern' => 'reports.*'],
                ['label' => 'Affected Families', 'route' => 'affected-families.index', 'pattern' => 'affected-families.*'],
                ['label' => 'Accident Module', 'route' => 'accidents.index', 'pattern' => 'accidents.*'],
                ['label' => 'Validation Queue', 'route' => 'validation.index', 'pattern' => 'validation.*'],
                ['label' => 'Printable Summary', 'route' => 'reports.print', 'pattern' => 'reports.print'],
            ],
            'dswd' => [
                ['label' => 'DSWD Dashboard', 'route' => 'dswd.dashboard', 'pattern' => 'dswd.dashboard'],
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
