<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ROUTE: GET /login -> login
     *
     * Shows the login form. If the user is already authenticated,
     * redirects them straight to the dashboard.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * ROUTE: POST /login -> login.post
     *
     * Validates input credentials and attempts authentication via Auth::attempt().
     * Upon success, regenerates the session ID to prevent session fixation.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => __('Please enter your email address.'),
            'email.email'       => __('Please enter a valid email address.'),
            'password.required' => __('Please enter your password.'),
        ]);

        $remember = $request->boolean('remember');
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ]);
        }

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))
                ->with('success', __('Welcome back!'));
        }

        RateLimiter::hit($throttleKey, 60);

        return back()
            ->withErrors(['email' => 'The provided credentials do not match our records.'])
            ->onlyInput('email');
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    /**
     * ROUTE: POST /logout -> logout
     *
     * Logs the current user out, invalidates their session, and regenerates CSRF token.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('info', __('You have been logged out.'));
    }
}
