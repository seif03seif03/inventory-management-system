@extends('layouts.app')

@section('title', 'My Profile')
@section('subtitle', 'Manage your personal account information and security')

@section('content')

    <div class="card" style="max-width: 640px;">
        <div class="card-header">
            <div>
                <h2>Profile Details</h2>
                <p>Update your personal information</p>
            </div>
            <div>
                @if ($user->role)
                    <span class="badge badge-blue">{{ $user->role->name }}</span>
                @endif
            </div>
        </div>

        <div class="card-body">

            @if (session('success'))
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>Please correct the errors below.</div>
                </div>
            @endif

            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-grid">

                    <div class="form-group full">
                        <label for="name">Full Name <span style="color:var(--color-danger);">*</span></label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            class="form-control"
                            required
                        >
                        @error('name')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label for="email">Email Address <span style="color:var(--color-danger);">*</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $user->email) }}"
                            class="form-control"
                            required
                        >
                        @error('email')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label for="phone">
                            {{ __('Phone Number') }}
                            @if ($user->receive_notifications)
                                <span style="color:var(--color-danger);">*</span>
                            @endif
                        </label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="{{ old('phone', $user->phone) }}"
                            class="form-control"
                            placeholder="+201012345678"
                            maxlength="20"
                            @if ($user->receive_notifications) required @endif
                        >
                        @error('phone')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Role and notification permission are shown read-only.
                         Only an administrator can change them, so the profile
                         form deliberately does not submit those fields. --}}
                    <div class="form-group full">
                        <label>{{ __('Role') }}</label>
                        <p style="margin:0;">
                            <span class="badge badge-blue">{{ __($user->role?->name ?? 'User') }}</span>
                            @if ($user->receive_notifications)
                                <span class="badge badge-green">&#10003; {{ __('Receives notifications') }}</span>
                            @endif
                        </p>
                        <span class="form-hint" style="font-size:11px;color:var(--color-text-muted,#5F6368);">
                            {{ __('Only an administrator can change your role or notification permission.') }}
                        </span>
                    </div>

                    <div class="form-group full" style="border-top: 1px solid var(--color-border); padding-top: 16px; margin-top: 4px;">
                        <h3 style="font-size: 14px; font-weight: 700; margin: 0 0 12px; color: var(--color-text);">Change Password</h3>
                        <p class="hint" style="margin-bottom: 12px;">Leave blank if you do not want to change your password.</p>
                    </div>

                    <div class="form-group full">
                        <label for="current_password">Current Password</label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="form-control"
                            placeholder="Required if changing password"
                        >
                        @error('current_password')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="At least 8 characters"
                        >
                        @error('password')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            placeholder="Repeat new password"
                        >
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Update Profile
                    </button>
                </div>
            </form>

        </div>
    </div>

@endsection
