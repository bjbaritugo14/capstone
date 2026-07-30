<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $role = (string) ($user->role?->role_name ?? '');

        if ($role === '') {
            abort(403, 'Authenticated user has no assigned role.');
        }

        if ($request->hasSession() && $request->session()->get('role') !== $role) {
            $request->session()->put('role', $role);
        }

        if (! in_array($role, $roles, true)) {
            abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}
