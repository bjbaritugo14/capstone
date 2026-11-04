@props(['name'])
@php
    $paths = match ($name) {
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
        'map', 'map-pinned' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Zm6-3v15m6-12v15"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'history' => '<path d="M3 11a9 9 0 1 1 2.6 7.4M3 4v7h7m2-4v5l3 2"/>',
        'activity' => '<path d="M3 12h4l3-9 4 18 3-9h4"/>',
        'settings' => '<path d="m9 3-1 3-3 1-2 4 2 2v4l4 2 3-1 3 1 4-2v-4l2-2-2-4-3-1-1-3Z"/><circle cx="12" cy="11" r="3"/>',
        'git-fork' => '<circle cx="6" cy="3" r="2"/><circle cx="18" cy="3" r="2"/><circle cx="6" cy="21" r="2"/><path d="M6 5v14m12-14v3a4 4 0 0 1-4 4H6"/>',
        'hand-heart' => '<path d="M2 14h4v8H2Zm4 1 4-3h4a2 2 0 0 1 0 4h-3m3 0 5-3a2 2 0 0 1 2 3l-7 5H6M12 9 8 5a2.5 2.5 0 0 1 4-3 2.5 2.5 0 0 1 4 3Z"/>',
        'car' => '<path d="m5 7 2-4h10l2 4m-16 0h18v11H3Zm3 11v3m12-3v3M3 12h18"/><circle cx="7" cy="15" r="1"/><circle cx="17" cy="15" r="1"/>',
        'clipboard-check' => '<rect x="7" y="2" width="10" height="4" rx="1"/><path d="M7 4H4v18h16V4h-3m-9 9 3 3 5-6"/>',
        'printer' => '<path d="M6 9V2h12v7M6 18H3V9h18v9h-3M6 14h12v8H6Z"/>',
        'radio-tower' => '<path d="m8 21 4-14 4 14M9 17h6M5 4a10 10 0 0 0 0 14M19 4a10 10 0 0 1 0 14M8 7a6 6 0 0 0 0 7m8-7a6 6 0 0 1 0 7"/><circle cx="12" cy="7" r="1"/>',
        'log-out' => '<path d="M9 21H3V3h6m7 4 5 5-5 5M8 12h13"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M7 7h10v10"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        default => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>',
    };
@endphp
<svg {{ $attributes }} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
