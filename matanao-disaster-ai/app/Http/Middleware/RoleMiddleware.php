<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $role = (string) $request->session()->get('role');

        if ($role === '' || ! in_array($role, $roles, true)) {
            abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}
