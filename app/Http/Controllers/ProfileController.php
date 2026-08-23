<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * ROUTE: GET /profile -> profile.edit
     *
     * Displays profile settings form for currently logged-in user.
     */
    public function edit()
    {
        $user = Auth::user();

        return view('profile.index', compact('user'));
    }

    /**
     * ROUTE: PUT /profile -> profile.update
     *
     * Updates name, email, and optional password for currently logged-in user.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'nullable|required_with:password|string',
            'password'         => 'nullable|string|min:8|confirmed',
        ], [
            'name.required'              => 'Please enter your name.',
            'email.required'             => 'Please enter your email address.',
            'email.unique'               => 'This email address is already in use.',
            'current_password.required_with' => 'Please enter your current password to set a new password.',
            'password.min'               => 'The new password must be at least 8 characters.',
            'password.confirmed'         => 'The new password confirmation does not match.',
        ]);

        if (! empty($validated['password'])) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Your current password is incorrect.'])->withInput();
            }

            $user->password = Hash::make($validated['password']);
        }

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }
}
