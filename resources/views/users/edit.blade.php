@extends('layouts.app')

@section('title', __('Edit User'))
@section('subtitle', __('Update user details and role assignment'))

@section('content')

    <div class="card" style="max-width: 680px;">
        <div class="card-header">
            <div>
                <h2>Edit Account — {{ $user->name }}</h2>
                <p>{{ __('Modify user information or change password') }}</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Users') }}
            </a>
        </div>

        <div class="card-body">

            <form action="{{ route('users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-grid">

                    <div class="form-group full">
                        <label for="name">{{ __('Full Name') }} <span style="color:var(--color-danger);">*</span></label>
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

                    <div class="form-group">
                        <label for="email">{{ __('Email Address') }} <span style="color:var(--color-danger);">*</span></label>
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

                    <div class="form-group">
                        <label for="phone">{{ __('Phone Number') }}</label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="{{ old('phone', $user->phone) }}"
                            class="form-control"
                            placeholder="+201012345678"
                            maxlength="20"
                        >
                        <span class="form-hint" style="font-size:11px;color:var(--color-text-muted,#5F6368);">
                            {{ __('Required only for users who receive notifications.') }}
                        </span>
                        @error('phone')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input
                                type="checkbox"
                                name="receive_notifications"
                                value="1"
                                {{ old('receive_notifications', $user->receive_notifications) ? 'checked' : '' }}
                            >
                            <span>{{ __('Receive notifications') }}</span>
                        </label>
                        <span class="form-hint" style="font-size:11px;color:var(--color-text-muted,#5F6368);">
                            {{ __('Sends low-stock alerts to this user. Requires a phone number.') }}
                        </span>
                        @error('receive_notifications')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="role_id">{{ __('System Role') }} <span style="color:var(--color-danger);">*</span></label>
                        <select id="role_id" name="role_id" class="form-control" required>
                            <option value="">{{ __('Select a role...') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full" style="border-top: 1px solid var(--color-border); padding-top: 16px; margin-top: 4px;">
                        <label for="password">{{ __('New Password') }} <span class="hint">(Leave blank to keep existing password)</span></label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="{{ __('Enter new password if changing') }}"
                        >
                        @error('password')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label for="password_confirmation">{{ __('Confirm New Password') }}</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            placeholder="{{ __('Confirm new password') }}"
                        >
                    </div>

                </div>

                <div class="form-actions">
                    <a href="{{ route('users.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Changes') }}
                    </button>
                </div>
            </form>

        </div>
    </div>

@endsection
