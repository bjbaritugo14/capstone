<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Matanao MDRRMO Assessment System' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-theme.css') }}">
    @yield('head')
</head>
<body class="dashboard-body">
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
                ['label' => 'User Accounts', 'route' => 'admin.users', 'pattern' => 'admin.users', 'icon' => 'users', 'short' => 'UA'],
                ['label' => 'Incident Records', 'route' => 'admin.incident-management', 'pattern' => 'admin.incident-management*', 'icon' => 'database', 'short' => 'IR'],
                ['label' => 'Recommendation Logs', 'route' => 'admin.recommendation-history', 'pattern' => 'admin.recommendation-history', 'icon' => 'history', 'short' => 'RL'],
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
                ['label' => 'Field Reports', 'route' => 'field-officer.dashboard', 'pattern' => 'field-officer.*', 'icon' => 'radio-tower', 'short' => 'FR'],
            ],
            'dswd' => [
                ['label' => 'DSWD Overview', 'route' => 'dswd.dashboard', 'pattern' => 'dswd.dashboard', 'icon' => 'layout-dashboard', 'short' => 'DO'],
                ['label' => 'Validated Area Map', 'route' => 'dswd.validated-areas', 'pattern' => 'dswd.validated-areas', 'icon' => 'map', 'short' => 'VA'],
                ['label' => 'Affected Families', 'route' => 'affected-families.index', 'pattern' => 'affected-families.*', 'icon' => 'home', 'short' => 'AF'],
                ['label' => 'Relief Recommendations', 'route' => 'dswd.recommendations', 'pattern' => 'dswd.recommendations', 'icon' => 'hand-heart', 'short' => 'RR'],
            ],
            default => [],
        };
    @endphp
    @php
        $userName = auth()->user()?->full_name ?? $roleLabel;
        $initials = collect(explode(' ', $userName))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
        $activeNavigation = collect($navigation)->first(fn ($item) => request()->routeIs($item['route']))
            ?? collect($navigation)->first(fn ($item) => request()->routeIs($item['pattern']));
        $pageLabel = $activeNavigation['label'] ?? $title ?? 'Overview';
    @endphp
    <a href="#main-content" class="skip-link">Skip to content</a>
    <header class="app-topbar">
        <div class="topbar-brand">
            <button type="button" class="sidebar-toggle" aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false" data-sidebar-toggle>
                <x-icon name="menu" />
            </button>
            <a href="{{ count($navigation) ? route($navigation[0]['route']) : url('/') }}" class="brand-link">
                <span class="brand-mark">M</span>
                <span>Matanao<span class="topbar-brand-detail"> MDRRMO</span></span>
            </a>
        </div>
        <div class="navigation-search">
            <x-icon name="search" />
            <input type="search" id="navigation-search" placeholder="Search navigation" aria-label="Search navigation" aria-controls="primary-navigation">
            <kbd>/</kbd>
        </div>
        <div class="topbar-actions">
            <span class="workspace-label"><span></span>{{ $roleLabel }} workspace</span>
            <details class="account-menu">
                <summary aria-label="User account menu"><span class="avatar">{{ $initials }}</span><x-icon name="chevron-down" /></summary>
                <div class="account-dropdown">
                    <strong>{{ $userName }}</strong>
                    <span>{{ auth()->user()?->email }}</span>
                    <span class="account-role">{{ $roleLabel }}</span>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="log-out" />Sign out</button></form>
                </div>
            </details>
        </div>
    </header>
    <div class="app-shell">
        <div class="sidebar-backdrop" data-sidebar-close></div>

        <aside class="sidebar" id="app-sidebar" aria-label="Primary navigation">
            <div class="sidebar-inner">
                <div class="sidebar-top">
                    <div class="sidebar-workspace">
                        <span class="workspace-icon"><x-icon name="layout-dashboard" /></span>
                        <div><strong>Command Center</strong><span>Disaster & incident monitoring</span></div>
                    </div>
                    <div class="nav-section-label">Workspace</div>
                    <nav class="nav-links" id="primary-navigation" aria-label="Workspace pages">
                        @foreach ($navigation as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                class="{{ ($activeNavigation['route'] ?? null) === $item['route'] ? 'active' : '' }}"
                                @if (($activeNavigation['route'] ?? null) === $item['route']) aria-current="page" @endif
                                title="{{ $item['label'] }}"
                            >
                                <span class="nav-icon" data-fallback="{{ $item['short'] }}" aria-hidden="true">
                                    <x-icon :name="$item['icon']" />
                                </span>
                                <span class="nav-label">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                    <p class="navigation-empty" hidden>No matching pages.</p>
                </div>

                <div class="sidebar-footer">
                    <div class="user-card">
                        <div class="avatar">{{ $initials }}</div>
                        <div>
                            <strong>{{ $userName }}</strong>
                            <p>{{ $roleLabel }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="logout-link">
                            <span class="nav-icon" data-fallback="LO" aria-hidden="true">
                                <x-icon name="log-out" />
                            </span>
                            <span class="nav-label">Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="page-content" id="main-content" tabindex="-1">
            <div class="desktop-context-bar">
                <div class="breadcrumb" aria-label="Breadcrumb">
                    <x-icon name="home" /><span>Workspace</span><x-icon name="chevron-right" /><strong>{{ $pageLabel }}</strong>
                </div>
                <time datetime="{{ now()->toDateString() }}">{{ now()->format('F j, Y') }}</time>
            </div>

            @if(session('status'))
                <div class="banner">{{ session('status') }}</div>
            @endif

            @yield('content')
            <footer class="app-footer"><span>&copy; {{ now()->year }} Matanao MDRRMO</span><span>Damage assessment & incident monitoring</span></footer>
        </main>
    </div>
    <script>
        (() => {
            const body = document.body;
            const toggle = document.querySelector('[data-sidebar-toggle]');
            const closeTargets = document.querySelectorAll('[data-sidebar-close], .nav-links a');
            const sidebar = document.getElementById('app-sidebar');
            const search = document.getElementById('navigation-search');
            const mobile = window.matchMedia('(max-width: 860px)');

            const setSidebar = (isOpen) => {
                body.classList.toggle('sidebar-open', isOpen);
                toggle?.setAttribute('aria-expanded', String(isOpen));
                toggle?.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
                if (mobile.matches) sidebar.inert = !isOpen;
            };

            toggle?.addEventListener('click', () => {
                setSidebar(!body.classList.contains('sidebar-open'));
                if (mobile.matches && body.classList.contains('sidebar-open')) sidebar.querySelector('a:not([hidden]), button')?.focus();
            });

            closeTargets.forEach((target) => {
                target.addEventListener('click', () => { setSidebar(false); if (mobile.matches) toggle?.focus(); });
            });

            search?.addEventListener('input', () => {
                const query = search.value.trim().toLowerCase();
                const links = [...document.querySelectorAll('.nav-links a')];
                links.forEach(link => { link.hidden = !link.textContent.toLowerCase().includes(query); });
                document.querySelector('.navigation-empty').hidden = links.some(link => !link.hidden);
            });
            mobile.addEventListener('change', () => { setSidebar(false); sidebar.inert = mobile.matches; });
            sidebar.inert = mobile.matches;
            document.addEventListener('click', (event) => {
                document.querySelectorAll('.account-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
            });

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    const sidebarWasOpen = body.classList.contains('sidebar-open');
                    setSidebar(false);
                    document.querySelectorAll('.account-menu[open]').forEach(menu => { menu.open = false; });
                    if (mobile.matches && sidebarWasOpen) toggle?.focus();
                }
                if (event.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
                    event.preventDefault();
                    search?.focus();
                }
                if (event.key === 'Tab' && mobile.matches && body.classList.contains('sidebar-open')) {
                    const targets = [toggle, ...sidebar.querySelectorAll('a:not([hidden]), button')];
                    const index = targets.indexOf(document.activeElement);
                    event.preventDefault();
                    targets[(index + (event.shiftKey ? -1 : 1) + targets.length) % targets.length]?.focus();
                }
            });
        })();
    </script>
    @yield('scripts')
</body>
</html>
