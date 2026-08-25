@extends('layouts.app')

@section('title', 'Add New User')
@section('subtitle', 'Create a new user account and assign a role')

@section('content')

    <div class="card" style="max-width: 680px;">
        <div class="card-header">
            <div>
                <h2>User Information</h2>
                <p>Fill in the details to register a new user</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to Users
            </a>
        </div>

        <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>Please fix the errors below before submitting.</div>
                </div>
            @endif

            <form action="{{ route('users.store') }}" method="POST">
                @csrf

                <div class="form-grid">

                    <div class="form-group full">
                        <label for="name">Full Name <span style="color:var(--color-danger);">*</span></label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            class="form-control"
                            placeholder="e.g. Ahmed Hassan"
                            required
                        >
                        @error('name')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span style="color:var(--color-danger);">*</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="form-control"
                            placeholder="user@example.com"
                            required
                        >
                        @error('email')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="{{ old('phone', '') }}"
                            class="form-control"
                            placeholder="+201012345678"
                            maxlength="20"
                        >
                        <span class="form-hint" style="font-size:11px;color:var(--color-text-muted,#5F6368);">
                            Required only for users who receive notifications.
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
                                {{ old('receive_notifications', false) ? 'checked' : '' }}
                            >
                            <span>Receive notifications</span>
                        </label>
                        <span class="form-hint" style="font-size:11px;color:var(--color-text-muted,#5F6368);">
                            Sends low-stock alerts to this user. Requires a phone number.
                        </span>
                        @error('receive_notifications')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="role_id">System Role <span style="color:var(--color-danger);">*</span></label>
                        <select id="role_id" name="role_id" class="form-control" required>
                            <option value="">Select a role...</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span style="color:var(--color-danger);">*</span></label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="At least 8 characters"
                            required
                        >
                        @error('password')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password <span style="color:var(--color-danger);">*</span></label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            placeholder="Repeat password"
                            required
                        >
                    </div>

                </div>

                <div class="form-actions">
                    <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> Create User Account
                    </button>
                </div>
            </form>

        </div>
    </div>

@endsection
