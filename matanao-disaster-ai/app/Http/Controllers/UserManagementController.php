<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Rules\NoSequentialCharacters;
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

    public function store(Request $request): RedirectResponse
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

        User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.users')->with('status', 'User account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'role_id' => ['required', 'exists:roles,role_id'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $user->update($validated);

        return redirect()->route('admin.users')->with('status', 'User account updated.');
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
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

        return redirect()->route('admin.users')->with('status', 'User password changed.');
    }
}
