<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * ROUTE: GET /users -> users.index
     *
     * List all users with eager-loaded role. Admin only.
     */
    public function index(Request $request)
    {
        $query = User::with('role')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleId = $request->input('role_id')) {
            $query->where('role_id', $roleId);
        }

        $users = $query->paginate(20)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * ROUTE: GET /users/create -> users.create
     *
     * Show form to create a new user account. Admin only.
     */
    public function create()
    {
        $roles = Role::orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    /**
     * ROUTE: POST /users -> users.store
     *
     * Store a new user in database with hashed password. Admin only.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'role_id'  => 'required|exists:roles,id',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required'     => 'Please enter the user\'s name.',
            'email.required'    => 'Please enter an email address.',
            'email.unique'      => 'This email address is already registered to another user.',
            'role_id.required'  => 'Please select a system role for this user.',
            'password.required' => 'Please enter a password for this user.',
            'password.min'      => 'The password must be at least 8 characters long.',
            'password.confirmed'=> 'The password confirmation does not match.',
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'role_id'  => $validated['role_id'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User account created successfully.');
    }

    /**
     * ROUTE: GET /users/{user}/edit -> users.edit
     *
     * Show form to edit user account details and role. Admin only.
     */
    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * ROUTE: PUT /users/{user} -> users.update
     *
     * Update user details, role, and optional password change. Admin only.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_id'  => 'required|exists:roles,id',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'name.required'     => 'Please enter the user\'s name.',
            'email.required'    => 'Please enter an email address.',
            'email.unique'      => 'This email address is already registered to another user.',
            'role_id.required'  => 'Please select a system role.',
            'password.min'      => 'The password must be at least 8 characters long.',
            'password.confirmed'=> 'The password confirmation does not match.',
        ]);

        $user->name    = $validated['name'];
        $user->email   = $validated['email'];
        $user->role_id = $validated['role_id'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with('success', 'User account updated successfully.');
    }

    /**
     * ROUTE: DELETE /users/{user} -> users.destroy
     *
     * Delete user account. Protects against self-deletion and deleting last admin. Admin only.
     */
    public function destroy(User $user)
    {
        // 1. Prevent self-deletion
        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account while logged in.');
        }

        // 2. Prevent deleting the last Admin account
        if ($user->isAdmin()) {
            $adminCount = User::whereHas('role', function ($q) {
                $q->where('name', 'Admin');
            })->count();

            if ($adminCount <= 1) {
                return back()->with('error', 'Cannot delete the last remaining Administrator account.');
            }
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User account deleted successfully.');
    }
}
