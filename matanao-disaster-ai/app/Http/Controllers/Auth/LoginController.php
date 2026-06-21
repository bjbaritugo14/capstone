<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Number of failed attempts allowed before a temporary lockout.
     */
    protected const MAX_ATTEMPTS = 3;

    /**
     * Lockout duration, in seconds, once the attempt limit is reached.
     */
    protected const LOCKOUT_SECONDS = 30;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request, $validated['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors([
                    'email' => "Too many failed login attempts. Please try again in {$seconds} seconds.",
                ])
                ->onlyInput('email');
        }

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => 'active',
        ])) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            $remaining = self::MAX_ATTEMPTS - RateLimiter::attempts($throttleKey);

            $message = $remaining > 0
                ? "Invalid credentials or inactive account. {$remaining} attempt(s) remaining before a 30-second lockout."
                : "Too many failed login attempts. Login is temporarily disabled for 30 seconds.";

            return back()
                ->withErrors(['email' => $message])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $role = Auth::user()->role?->role_name ?? '';
        $request->session()->put('role', $role);

        return match ($role) {
            'admin', 'super_admin' => redirect()->route('admin.users'),
            'dswd' => redirect()->route('dswd.dashboard'),
            default => redirect()->route('dashboard'),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Build the rate-limiter key for the current login attempt,
     * scoped to the submitted email and the client IP address.
     */
    protected function throttleKey(Request $request, string $email): string
    {
        return Str::transliterate(Str::lower($email).'|'.$request->ip());
    }
}
