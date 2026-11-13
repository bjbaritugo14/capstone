@extends('layouts.app')

@section('content')
@php
    $roleLabels = [
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'mdrrmo' => 'MDRRMO Officer',
        'dswd' => 'DSWD Officer',
        'field_officer' => 'Field Officer',
        'validator' => 'Validator',
    ];

    $roleBadges = [
        'super_admin' => 'badge-purple',
        'admin' => 'badge-blue',
        'mdrrmo' => 'badge-green',
        'dswd' => 'badge-amber',
        'field_officer' => 'badge-teal',
        'validator' => 'badge-indigo',
    ];

    $roleAvatarTheme = [
        'super_admin' => 'purple',
        'admin' => 'blue',
        'mdrrmo' => 'green',
        'dswd' => 'amber',
        'field_officer' => 'teal',
        'validator' => 'indigo',
    ];

    $totalUsers = $users->count();
    $activeCount = $users->where('status', 'active')->count();
    $adminCount = $users->filter(fn($u) => in_array($u->role?->role_name, ['admin', 'super_admin']))->count();
    $opsCount = $users->filter(fn($u) => in_array($u->role?->role_name, ['mdrrmo', 'dswd', 'field_officer', 'validator']))->count();
@endphp

<div class="page-header">
    <div>
        <h1>User Accounts</h1>
        <p class="muted">Manage system access, assign organizational roles, and configure user security.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" id="btn-toggle-create">
            <x-icon name="plus" />
            <span id="create-btn-text">Create Account</span>
        </button>
    </div>
</div>

@if ($errors->any())
    <div class="form-error" role="alert">
        <strong>Please review the form:</strong> {{ $errors->first() }}
    </div>
@endif

<div class="stats-grid">
    <div class="stat-card overview-stat">
        <div>
            <span>Total Accounts</span>
            <strong>{{ $totalUsers }}</strong>
            <small>Registered user profiles</small>
        </div>
        <div class="stat-icon">
            <x-icon name="users" />
        </div>
    </div>
    <div class="stat-card overview-stat">
        <div>
            <span>Active Users</span>
            <strong>{{ $activeCount }}</strong>
            <small>{{ $totalUsers > 0 ? round(($activeCount / $totalUsers) * 100) : 0 }}% enabled</small>
        </div>
        <div class="stat-icon green">
            <x-icon name="user-check" />
        </div>
    </div>
    <div class="stat-card overview-stat">
        <div>
            <span>Administrators</span>
            <strong>{{ $adminCount }}</strong>
            <small>System & governance access</small>
        </div>
        <div class="stat-icon purple">
            <x-icon name="shield" />
        </div>
    </div>
    <div class="stat-card overview-stat">
        <div>
            <span>Operations Staff</span>
            <strong>{{ $opsCount }}</strong>
            <small>MDRRMO, DSWD & Field</small>
        </div>
        <div class="stat-icon amber">
            <x-icon name="activity" />
        </div>
    </div>
</div>

<section class="card bottom-gap" id="create-user-card" style="{{ $errors->any() ? '' : 'display: none;' }}">
    <div class="section-heading">
        <div>
            <h2>New User Account</h2>
            <p class="muted">Encode identity details and assign initial security credentials.</p>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-close-create">
            <x-icon name="x" />
            <span>Cancel</span>
        </button>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="form-grid">
        @csrf
        <div>
            <label for="create_full_name">Full Name <span class="required-star">*</span></label>
            <input id="create_full_name" name="full_name" value="{{ old('full_name') }}" placeholder="e.g. Maria Clara Santos" required>
        </div>
        <div>
            <label for="create_email">Email Address <span class="required-star">*</span></label>
            <input type="email" id="create_email" name="email" value="{{ old('email') }}" placeholder="officer@example.com" required>
        </div>
        <div>
            <label for="create_role_id">Organizational Role <span class="required-star">*</span></label>
            <select id="create_role_id" name="role_id" required>
                @foreach($roles as $role)
                    <option value="{{ $role->role_id }}" @selected(old('role_id') == $role->role_id)>
                        {{ $roleLabels[$role->role_name] ?? strtoupper($role->role_name) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="create_status">Account Status <span class="required-star">*</span></label>
            <select id="create_status" name="status" required>
                <option value="active" @selected(old('status', 'active') === 'active')>Active (Can login)</option>
                <option value="inactive" @selected(old('status') === 'inactive')>Inactive (Login disabled)</option>
            </select>
        </div>
        <div>
            <label for="create_password">Initial Password <span class="required-star">*</span></label>
            <input type="password" id="create_password" name="password" placeholder="At least 8 characters" required>
        </div>
        <div>
            <label for="create_password_confirmation">Confirm Password <span class="required-star">*</span></label>
            <input type="password" id="create_password_confirmation" name="password_confirmation" placeholder="Repeat password" required>
        </div>
        <div class="full-width">
            <p class="field-hint">
                <x-icon name="shield" />
                Password must contain at least 8 characters with uppercase, lowercase, number, and special symbol.
            </p>
        </div>
        <div class="full-width form-actions-row">
            <button type="submit" class="btn btn-primary">
                <x-icon name="plus" />
                <span>Create User</span>
            </button>
            <button type="button" class="btn btn-secondary" id="btn-cancel-create-bottom">Cancel</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Existing User Accounts</h2>
            <p class="muted">Search, update roles, or reset credentials for registered accounts.</p>
        </div>
    </div>

    <div class="directory-toolbar">
        <div class="directory-search">
            <x-icon name="search" />
            <input type="search" id="user-filter-search" placeholder="Search by name or email..." aria-label="Search users">
        </div>
        <select id="user-filter-role" class="directory-filter" aria-label="Filter by role">
            <option value="">All Roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->role_name }}">{{ $roleLabels[$role->role_name] ?? strtoupper($role->role_name) }}</option>
            @endforeach
        </select>
        <select id="user-filter-status" class="directory-filter" aria-label="Filter by status">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <span class="directory-count" id="user-results-count">Showing {{ $totalUsers }} accounts</span>
    </div>

    <div class="admin-user-list" id="users-directory-list">
        @forelse($users as $user)
            @php
                $roleKey = $user->role?->role_name ?? 'other';
                $theme = $roleAvatarTheme[$roleKey] ?? 'slate';
                $badge = $roleBadges[$roleKey] ?? 'badge-slate';
                $nameParts = explode(' ', $user->full_name);
                $initials = count($nameParts) >= 2
                    ? mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1)
                    : mb_substr($user->full_name, 0, 2);
            @endphp
            <div class="user-item admin-user-card" data-user-item data-name="{{ strtolower($user->full_name) }}" data-email="{{ strtolower($user->email) }}" data-role="{{ $roleKey }}" data-status="{{ $user->status }}">
                <div class="user-summary">
                    <div class="user-identity">
                        <div class="user-avatar {{ $theme }}">{{ strtoupper($initials) }}</div>
                        <div class="user-identity-text">
                            <strong>{{ $user->full_name }}</strong>
                            <span>{{ $user->email }}</span>
                        </div>
                    </div>

                    <div class="user-badges">
                        <span class="badge {{ $badge }}">{{ $roleLabels[$roleKey] ?? strtoupper($roleKey) }}</span>
                        <span class="badge {{ $user->status === 'active' ? 'badge-green' : 'badge-amber' }}">
                            <i class="status-dot {{ $user->status === 'active' ? 'active' : 'inactive' }}"></i>
                            {{ ucfirst($user->status) }}
                        </span>
                    </div>

                    <div class="user-actions">
                        <button type="button" class="btn btn-secondary btn-sm js-toggle-drawer" data-target="edit-{{ $user->user_id }}" aria-expanded="false">
                            <x-icon name="edit" />
                            <span>Edit</span>
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm js-toggle-drawer" data-target="pwd-{{ $user->user_id }}" aria-expanded="false">
                            <x-icon name="key" />
                            <span>Password</span>
                        </button>
                    </div>
                </div>

                {{-- Inline Edit Drawer --}}
                <div class="user-drawer" id="drawer-edit-{{ $user->user_id }}">
                    <div class="drawer-title">
                        <x-icon name="edit" />
                        <span>Edit Account Profile & Role</span>
                    </div>
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-user-form form-grid">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="edit_name_{{ $user->user_id }}">Full Name <span class="required-star">*</span></label>
                            <input id="edit_name_{{ $user->user_id }}" name="full_name" value="{{ $user->full_name }}" required>
                        </div>
                        <div>
                            <label for="edit_email_{{ $user->user_id }}">Email Address <span class="required-star">*</span></label>
                            <input type="email" id="edit_email_{{ $user->user_id }}" name="email" value="{{ $user->email }}" required>
                        </div>
                        <div>
                            <label for="edit_role_{{ $user->user_id }}">Organizational Role <span class="required-star">*</span></label>
                            <select id="edit_role_{{ $user->user_id }}" name="role_id" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}" @selected($user->role_id === $role->role_id)>
                                        {{ $roleLabels[$role->role_name] ?? strtoupper($role->role_name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="edit_status_{{ $user->user_id }}">Account Status <span class="required-star">*</span></label>
                            <select id="edit_status_{{ $user->user_id }}" name="status" required>
                                <option value="active" @selected($user->status === 'active')>Active</option>
                                <option value="inactive" @selected($user->status === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="full-width form-actions-row">
                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                            <button type="button" class="btn btn-secondary btn-sm js-close-drawer" data-target="edit-{{ $user->user_id }}">Cancel</button>
                        </div>
                    </form>
                </div>

                {{-- Inline Password Reset Drawer --}}
                <div class="user-drawer" id="drawer-pwd-{{ $user->user_id }}">
                    <div class="drawer-title">
                        <x-icon name="key" />
                        <span>Reset Account Password for {{ $user->full_name }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}" class="admin-password-form form-grid">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="pwd_{{ $user->user_id }}">New Password <span class="required-star">*</span></label>
                            <input type="password" id="pwd_{{ $user->user_id }}" name="password" placeholder="At least 8 characters" required>
                        </div>
                        <div>
                            <label for="pwd_conf_{{ $user->user_id }}">Confirm New Password <span class="required-star">*</span></label>
                            <input type="password" id="pwd_conf_{{ $user->user_id }}" name="password_confirmation" placeholder="Repeat new password" required>
                        </div>
                        <div class="full-width">
                            <p class="field-hint">
                                <x-icon name="shield" />
                                Requires uppercase, lowercase, number, and special character.
                            </p>
                        </div>
                        <div class="full-width form-actions-row">
                            <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
                            <button type="button" class="btn btn-secondary btn-sm js-close-drawer" data-target="pwd-{{ $user->user_id }}">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <p>No user accounts found in the database.</p>
            </div>
        @endforelse

        <div class="empty-state" id="user-filter-empty" style="display: none;">
            <p>No user accounts match your search or filter criteria.</p>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Toggle Create Account Card
        const createCard = document.getElementById('create-user-card');
        const toggleBtn = document.getElementById('btn-toggle-create');
        const closeBtn = document.getElementById('btn-close-create');
        const cancelBtn = document.getElementById('btn-cancel-create-bottom');
        const btnText = document.getElementById('create-btn-text');

        function toggleCreate(show) {
            const isVisible = show !== undefined ? show : (createCard.style.display === 'none' || createCard.style.display === '');
            createCard.style.display = isVisible ? 'block' : 'none';
            if (btnText) {
                btnText.textContent = isVisible ? 'Close Form' : 'Create Account';
            }
            if (isVisible) {
                createCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const nameInput = document.getElementById('create_full_name');
                if (nameInput) nameInput.focus();
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => toggleCreate());
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', () => toggleCreate(false));
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => toggleCreate(false));
        }

        // Toggle User Edit / Password Drawers
        document.querySelectorAll('.js-toggle-drawer').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = btn.dataset.target;
                const drawer = document.getElementById('drawer-' + targetId);
                if (!drawer) return;

                const isOpen = drawer.classList.contains('is-open');

                // Close other open drawers inside the same card
                const userCard = btn.closest('.user-item');
                if (userCard) {
                    userCard.querySelectorAll('.user-drawer').forEach(d => {
                        if (d !== drawer) d.classList.remove('is-open');
                    });
                    userCard.querySelectorAll('.js-toggle-drawer').forEach(b => {
                        if (b !== btn) b.setAttribute('aria-expanded', 'false');
                    });
                }

                drawer.classList.toggle('is-open', !isOpen);
                btn.setAttribute('aria-expanded', String(!isOpen));
                if (!isOpen) {
                    const firstInput = drawer.querySelector('input');
                    if (firstInput) setTimeout(() => firstInput.focus(), 50);
                }
            });
        });

        // Close Drawer buttons
        document.querySelectorAll('.js-close-drawer').forEach((btn) => {
            btn.addEventListener('click', () => {
                const targetId = btn.dataset.target;
                const drawer = document.getElementById('drawer-' + targetId);
                if (drawer) {
                    drawer.classList.remove('is-open');
                }
                const toggle = document.querySelector(`.js-toggle-drawer[data-target="${targetId}"]`);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });
        });

        // Realtime Client-side Search and Filter
        const searchInput = document.getElementById('user-filter-search');
        const roleFilter = document.getElementById('user-filter-role');
        const statusFilter = document.getElementById('user-filter-status');
        const userItems = document.querySelectorAll('[data-user-item]');
        const countDisplay = document.getElementById('user-results-count');
        const emptyDisplay = document.getElementById('user-filter-empty');

        function filterUsers() {
            const query = (searchInput?.value || '').trim().toLowerCase();
            const role = (roleFilter?.value || '').toLowerCase();
            const status = (statusFilter?.value || '').toLowerCase();

            let visibleCount = 0;

            userItems.forEach((item) => {
                const name = item.dataset.name || '';
                const email = item.dataset.email || '';
                const itemRole = item.dataset.role || '';
                const itemStatus = item.dataset.status || '';

                const matchesQuery = !query || name.includes(query) || email.includes(query);
                const matchesRole = !role || itemRole === role;
                const matchesStatus = !status || itemStatus === status;

                const isVisible = matchesQuery && matchesRole && matchesStatus;
                item.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            if (countDisplay) {
                countDisplay.textContent = `Showing ${visibleCount} of ${userItems.length} accounts`;
            }
            if (emptyDisplay) {
                emptyDisplay.style.display = (visibleCount === 0 && userItems.length > 0) ? 'block' : 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterUsers);
        if (roleFilter) roleFilter.addEventListener('change', filterUsers);
        if (statusFilter) statusFilter.addEventListener('change', filterUsers);
    });
</script>
@endsection
