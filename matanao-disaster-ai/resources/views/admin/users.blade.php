@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Super Admin</div>
        <h1>User Accounts</h1>
        <p class="muted">Create accounts, assign roles, activate/deactivate users, and reset passwords.</p>
    </div>
</div>

@if ($errors->any())
    <div class="form-error">{{ $errors->first() }}</div>
@endif

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Create Account</h2>
                <p class="muted">Only super admin/admin users should create system accounts.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="form-grid">
            @csrf
            <div>
                <label>Full Name</label>
                <input name="full_name" value="{{ old('full_name') }}" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div>
                <label>Role</label>
                <select name="role_id" required>
                    @foreach($roles as $role)
                        <option value="{{ $role->role_id }}">{{ strtoupper($role->role_name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Status</label>
                <select name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div>
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" required>
            </div>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Admin Rules</h2>
                <p class="muted">Recommended account responsibilities.</p>
            </div>
        </div>
        <div class="timeline-list">
            <div class="timeline-item"><span>1</span><p>Admin creates all MDRRMO, DSWD, and validator accounts.</p></div>
            <div class="timeline-item"><span>2</span><p>Admin can reset a user's password if the user forgets it.</p></div>
            <div class="timeline-item"><span>3</span><p>Inactive accounts cannot log in.</p></div>
        </div>
    </section>
</div>

<section class="card top-gap">
    <div class="section-heading">
        <div>
            <h2>Existing Users</h2>
            <p class="muted">Edit account profile, role, status, or password.</p>
        </div>
    </div>

    <div class="admin-user-list">
        @foreach($users as $user)
            <div class="admin-user-card">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-user-form">
                    @csrf
                    @method('PUT')
                    <div>
                        <label>Name</label>
                        <input name="full_name" value="{{ $user->full_name }}" required>
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="{{ $user->email }}" required>
                    </div>
                    <div>
                        <label>Role</label>
                        <select name="role_id" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->role_id }}" @selected($user->role_id === $role->role_id)>{{ strtoupper($role->role_name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status" required>
                            <option value="active" @selected($user->status === 'active')>Active</option>
                            <option value="inactive" @selected($user->status === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">Save Account</button>
                </form>

                <form method="POST" action="{{ route('admin.users.password', $user) }}" class="admin-password-form">
                    @csrf
                    @method('PUT')
                    <div>
                        <label>New Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div>
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
            </div>
        @endforeach
    </div>
</section>
@endsection
