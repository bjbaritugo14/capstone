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
                ['label' => 'User Roles', 'route' => 'admin.users', 'pattern' => 'admin.users', 'icon' => 'users', 'short' => 'UR'],
                ['label' => 'Incident/Data Management', 'route' => 'admin.incident-management', 'pattern' => 'admin.incident-management*', 'icon' => 'database', 'short' => 'ID'],
                ['label' => 'Recommendation History', 'route' => 'admin.recommendation-history', 'pattern' => 'admin.recommendation-history', 'icon' => 'history', 'short' => 'RH'],
                ['label' => 'Audit Trail', 'route' => 'admin.audit-trail', 'pattern' => 'admin.audit-trail', 'icon' => 'activity', 'short' => 'AT'],
                ['label' => 'System Settings', 'route' => 'admin.settings', 'pattern' => 'admin.settings', 'icon' => 'settings', 'short' => 'SS'],
            ],
            'mdrrmo' => [
                ['label' => 'Assessment Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'layout-dashboard', 'short' => 'AD'],
                ['label' => 'Disaster Damage', 'route' => 'reports.index', 'pattern' => 'reports.*', 'icon' => 'map-pinned', 'short' => 'DD'],
                ['label' => 'Affected Families', 'route' => 'affected-families.index', 'pattern' => 'affected-families.*', 'icon' => 'home', 'short' => 'AF'],
                ['label' => 'Decision Support', 'route' => 'recommendations.index', 'pattern' => 'recommendations.*', 'icon' => 'git-fork', 'short' => 'DS'],
                ['label' => 'Accidents', 'route' => 'accidents.index', 'pattern' => 'accidents.*', 'icon' => 'car', 'short' => 'AC'],
                ['label' => 'Validation Queue', 'route' => 'validation.index', 'pattern' => 'validation.*', 'icon' => 'clipboard-check', 'short' => 'VQ'],
                ['label' => 'Printable Summary', 'route' => 'reports.print', 'pattern' => 'reports.print', 'icon' => 'printer', 'short' => 'PS'],
            ],
            'validator' => [
                ['label' => 'Validation Queue', 'route' => 'validation.index', 'pattern' => 'validation.*', 'icon' => 'clipboard-check', 'short' => 'VQ'],
            ],
            'field_officer' => [
                ['label' => 'Field Reporting Dashboard', 'route' => 'field-officer.dashboard', 'pattern' => 'field-officer.*', 'icon' => 'radio-tower', 'short' => 'FR'],
            ],
            'dswd' => [
                ['label' => 'DSWD Dashboard', 'route' => 'dswd.dashboard', 'pattern' => 'dswd.dashboard', 'icon' => 'layout-dashboard', 'short' => 'DD'],
                ['label' => 'Validated Areas GIS', 'route' => 'dswd.validated-areas', 'pattern' => 'dswd.validated-areas', 'icon' => 'map', 'short' => 'VA'],
                ['label' => 'Affected Families', 'route' => 'affected-families.index', 'pattern' => 'affected-families.*', 'icon' => 'home', 'short' => 'AF'],
                ['label' => 'Assistance Recommendations', 'route' => 'dswd.recommendations', 'pattern' => 'dswd.recommendations', 'icon' => 'hand-heart', 'short' => 'AR'],
            ],
            default => [],
        };
    @endphp
    <div class="app-shell">
        <div class="sidebar-backdrop" data-sidebar-close></div>

        <aside class="sidebar" id="app-sidebar" aria-label="Primary navigation">
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
                            <a
                                href="{{ route($item['route']) }}"
                                class="{{ request()->routeIs($item['pattern']) ? 'active' : '' }}"
                                title="{{ $item['label'] }}"
                            >
                                <span class="nav-icon" data-fallback="{{ $item['short'] }}" aria-hidden="true">
                                    <i data-lucide="{{ $item['icon'] }}"></i>
                                </span>
                                <span class="nav-label">{{ $item['label'] }}</span>
                            </a>
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
                        <button type="submit" class="logout-link">
                            <span class="nav-icon" data-fallback="LO" aria-hidden="true">
                                <i data-lucide="log-out"></i>
                            </span>
                            <span class="nav-label">Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="page-content">
            <div class="desktop-context-bar">
                <div>
                    <span>{{ $roleLabel }}</span>
                    <strong>{{ $title ?? 'Matanao MDRRMO Assessment System' }}</strong>
                </div>
                <time datetime="{{ now()->toDateString() }}">{{ now()->format('F j, Y') }}</time>
            </div>

            <div class="mobile-topbar">
                <button
                    type="button"
                    class="sidebar-toggle"
                    aria-label="Open navigation"
                    aria-controls="app-sidebar"
                    aria-expanded="false"
                    data-sidebar-toggle
                >
                    <i data-lucide="menu"></i>
                </button>
                <div>
                    <strong>Matanao MDRRMO</strong>
                    <span>{{ $roleLabel }}</span>
                </div>
            </div>

            @if(session('status'))
                <div class="banner">{{ session('status') }}</div>
            @endif

            @yield('content')
        </main>
    </div>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script>
        (() => {
            if (window.lucide) {
                window.lucide.createIcons();
            }

            const body = document.body;
            const toggle = document.querySelector('[data-sidebar-toggle]');
            const closeTargets = document.querySelectorAll('[data-sidebar-close], .nav-links a');

            const setSidebar = (isOpen) => {
                body.classList.toggle('sidebar-open', isOpen);
                toggle?.setAttribute('aria-expanded', String(isOpen));
            };

            toggle?.addEventListener('click', () => {
                setSidebar(!body.classList.contains('sidebar-open'));
            });

            closeTargets.forEach((target) => {
                target.addEventListener('click', () => setSidebar(false));
            });

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    setSidebar(false);
                }
            });
        })();
    </script>
    @yield('scripts')
</body>
</html>
