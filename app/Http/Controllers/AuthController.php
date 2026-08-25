<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))
                ->with('success', __('Welcome back!'));
        }

        return back()
            ->withErrors(['email' => 'The provided credentials do not match our records.'])
            ->onlyInput('email');
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
