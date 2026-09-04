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
        $roles = Role::select('id', 'name')->orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * ROUTE: GET /users/create -> users.create
     *
     * Show form to create a new user account. Admin only.
     */
    public function create()
    {
        $roles = Role::select('id', 'name')->orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    /**
     * ROUTE: POST /users -> users.store
     *
     * Store a new user in database with hashed password. Admin only.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            array_merge([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:255|unique:users,email',
                'role_id'  => 'required|exists:roles,id',
                'password' => 'required|string|min:8|confirmed',
            ], $this->notificationRules($request)),
            array_merge([
                'name.required'     => __('Please enter the user\'s name.'),
                'email.required'    => __('Please enter an email address.'),
                'email.unique'      => __('This email address is already registered to another user.'),
                'role_id.required'  => __('Please select a system role for this user.'),
                'password.required' => __('Please enter a password for this user.'),
                'password.min'      => __('The password must be at least 8 characters long.'),
                'password.confirmed'=> __('The password confirmation does not match.'),
            ], $this->notificationMessages())
        );

        User::create([
            'name'                  => $validated['name'],
            'email'                 => $validated['email'],
            'phone'                 => $validated['phone'] ?? null,
            'role_id'               => $validated['role_id'],
            'receive_notifications' => $request->boolean('receive_notifications'),
            'password'              => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', __('User account created successfully.'));
    }

    /**
     * ROUTE: GET /users/{user}/edit -> users.edit
     *
     * Show form to edit user account details and role. Admin only.
     */
    public function edit(User $user)
    {
        $roles = Role::select('id', 'name')->orderBy('name')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * ROUTE: PUT /users/{user} -> users.update
     *
     * Update user details, role, and optional password change. Admin only.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate(
            array_merge([
                'name'     => 'required|string|max:255',
                'email'    => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'role_id'  => 'required|exists:roles,id',
                'password' => 'nullable|string|min:8|confirmed',
            ], $this->notificationRules($request, $user)),
            array_merge([
                'name.required'     => __('Please enter the user\'s name.'),
                'email.required'    => __('Please enter an email address.'),
                'email.unique'      => __('This email address is already registered to another user.'),
                'role_id.required'  => __('Please select a system role.'),
                'password.min'      => __('The password must be at least 8 characters long.'),
                'password.confirmed'=> __('The password confirmation does not match.'),
            ], $this->notificationMessages())
        );

        $user->name    = $validated['name'];
        $user->email   = $validated['email'];
        $user->role_id = $validated['role_id'];

        // Only overwrite the stored number when one was submitted. Revoking the
        // permission must NOT wipe it: the number is kept so the permission can
        // be restored later without re-entering it.
        if ($request->filled('phone')) {
            $user->phone = $validated['phone'];
        }

        $user->receive_notifications = $request->boolean('receive_notifications');

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with('success', __('User account updated successfully.'));
    }

    /*
    |--------------------------------------------------------------------------
    | Notification permission rules
    |--------------------------------------------------------------------------
    | receive_notifications is only meaningful if there is a number to reach the
    | user on, so enabling it makes phone required. The rule is built here, on
    | the server, from the submitted checkbox — never left to the browser.
    |
    | On update the user's ALREADY STORED number satisfies the requirement, so
    | an admin re-enabling the permission for someone who has a number does not
    | have to retype it. Enabling it for someone who never had one is refused.
    */

    private function notificationRules(Request $request, ?User $user = null): array
    {
        $wantsNotifications = $request->boolean('receive_notifications');
        $alreadyHasPhone    = $user && filled($user->phone);

        return [
            'phone' => $wantsNotifications && ! $alreadyHasPhone
                ? 'required|string|max:20'
                : 'nullable|string|max:20',
        ];
    }

    private function notificationMessages(): array
    {
        return [
            'phone.required' => __('Users who receive notifications must have a phone number.'),
            'phone.max'      => __('The phone number may not be longer than 20 characters.'),
        ];
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
            return back()->with('error', __('You cannot delete your own account while logged in.'));
        }

        // 2. Prevent deleting the last Admin account
        if ($user->isAdmin()) {
            $adminCount = User::whereHas('role', function ($q) {
                $q->where('name', 'Admin');
            })->count();

            if ($adminCount <= 1) {
                return back()->with('error', __('Cannot delete the last remaining Administrator account.'));
            }
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', __('User account deleted successfully.'));
    }
}
