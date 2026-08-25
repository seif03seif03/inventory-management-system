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
            // A user who is a notification recipient may change their number
            // but may not blank it — that would leave the permission with no
            // way to reach them.
            'phone'            => $user->receive_notifications
                                    ? 'required|string|max:20'
                                    : 'nullable|string|max:20',
            'current_password' => 'nullable|required_with:password|string',
            'password'         => 'nullable|string|min:8|confirmed',
        ], [
            'name.required'              => 'Please enter your name.',
            'email.required'             => 'Please enter your email address.',
            'email.unique'               => 'This email address is already in use.',
            'phone.required'             => 'Users who receive notifications must have a phone number.',
            'phone.max'                  => 'The phone number may not be longer than 20 characters.',
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

        // Only these three fields are assigned. role_id and
        // receive_notifications are deliberately NOT read from the request, so
        // a user cannot promote themselves or grant themselves notifications by
        // posting extra fields — those stay an administrator's decision.
        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }
}
