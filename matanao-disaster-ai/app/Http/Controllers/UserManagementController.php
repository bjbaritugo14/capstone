<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Rules\NoSequentialCharacters;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('role')
            ->orderBy('full_name')
            ->get();

        $roles = Role::query()
            ->orderBy('role_name')
            ->get();

        return view('admin.users', compact('users', 'roles'));
    }

    public function store(Request $request, AuditTrailService $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,role_id'],
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&#^()_\-+=\[\]{}|\\\\:;"\'<>,.\\/~`]/',
                new NoSequentialCharacters(),
            ],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'password.regex' => 'Password must contain uppercase, lowercase, number, and symbol.',
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);
        $user->load('role');

        $auditTrail->log(
            $request,
            'User Management',
            'user_created',
            'Created user account for '.$user->full_name.'.',
            $user,
            [
                'email' => $user->email,
                'assigned_role' => $user->role?->role_name,
                'status' => $user->status,
            ],
            $this->userCode($user),
        );

        return redirect()->route('admin.users')->with('status', 'User account created.');
    }

    public function update(Request $request, User $user, AuditTrailService $auditTrail): RedirectResponse
    {
        $user->load('role');
        $before = [
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role?->role_name,
            'status' => $user->status,
        ];

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'role_id' => ['required', 'exists:roles,role_id'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $user->update($validated);
        $user->load('role');

        $changes = [];

        if ($before['full_name'] !== $user->full_name) {
            $changes['full_name'] = [
                'before' => $before['full_name'],
                'after' => $user->full_name,
            ];
        }

        if ($before['email'] !== $user->email) {
            $changes['email'] = [
                'before' => $before['email'],
                'after' => $user->email,
            ];
        }

        if ($before['role'] !== $user->role?->role_name) {
            $changes['role'] = [
                'before' => $before['role'],
                'after' => $user->role?->role_name,
            ];
        }

        if ($before['status'] !== $user->status) {
            $changes['status'] = [
                'before' => $before['status'],
                'after' => $user->status,
            ];
        }

        $auditTrail->log(
            $request,
            'User Management',
            'user_updated',
            'Updated user account for '.$user->full_name.'.',
            $user,
            $changes === [] ? [
                'note' => 'Account was saved without field changes.',
            ] : $changes,
            $this->userCode($user),
        );

        return redirect()->route('admin.users')->with('status', 'User account updated.');
    }

    public function updatePassword(Request $request, User $user, AuditTrailService $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&#^()_\-+=\[\]{}|\\\\:;"\'<>,.\\/~`]/',
                new NoSequentialCharacters(),
            ],
        ], [
            'password.regex' => 'Password must contain uppercase, lowercase, number, and symbol.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $auditTrail->log(
            $request,
            'User Management',
            'user_password_reset',
            'Reset password for '.$user->full_name.'.',
            $user,
            [
                'email' => $user->email,
                'password_reset' => true,
            ],
            $this->userCode($user),
        );

        return redirect()->route('admin.users')->with('status', 'User password changed.');
    }

    protected function userCode(User $user): string
    {
        return 'USR-'.str_pad((string) $user->user_id, 4, '0', STR_PAD_LEFT);
    }
}
