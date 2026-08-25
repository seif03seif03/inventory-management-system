@extends('layouts.app')

@section('title', __('User Management'))
@section('subtitle', __('Manage system user accounts and role assignments'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Users') }}</h2>
                <p>{{ $users->total() }} {{ __('registered') }}</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-user-plus"></i> {{ __('Add User') }}
            </a>
        </div>

        <div class="card-body" style="padding-bottom: 0;">

            @if (session('success'))
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            <form action="{{ route('users.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="{{ __('Search users by name or email...') }}"
                    >
                </div>

                <select name="role_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Roles') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>
                            {{ __( $role->name ) }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-secondary btn-sm">{{ __('Filter') }}</button>

                @if (request()->hasAny(['search', 'role_id']))
                    <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Notifications') }}</th>
                        <th>{{ __('Created At') }}</th>
                        <th style="text-align: inherit;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="cell-with-avatar">
                                    <div class="avatar-sq">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <span class="cell-primary">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="cell-muted">{{ $user->email }}</td>
                            <td class="cell-mono">{{ $user->phone ?: '—' }}</td>
                            <td>
                                @if ($user->isAdmin())
                                    <span class="badge badge-blue">{{ __('Admin') }}</span>
                                @elseif ($user->isManager())
                                    <span class="badge badge-green">{{ __('Warehouse Manager') }}</span>
                                @else
                                    <span class="badge badge-gray">{{ __('Warehouse Employee') }}</span>
                                @endif
                            </td>
                            <td>
                                {{-- Enabled but no phone = misconfigured: the
                                     permission is set yet nothing can reach them. --}}
                                @if ($user->canReceiveNotifications())
                                    <span class="badge badge-green">&#10003; {{ __('Enabled') }}</span>
                                @elseif ($user->receive_notifications)
                                    <span class="badge badge-amber">{{ __('No phone') }}</span>
                                @else
                                    <span class="badge badge-gray">&mdash; {{ __('Disabled') }}</span>
                                @endif
                            </td>
                            <td class="cell-muted">{{ $user->created_at ? $user->created_at->format('d M Y') : '-' }}</td>
                            <td>
                                <div class="row-actions" style="justify-content: flex-start;">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('Edit') }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    @if (auth()->id() !== $user->id)
                                        <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('{{ __('Delete User') }}?');" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="{{ __('Delete') }}" style="color: var(--color-danger);">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #888;">
                                {{ __('No users found matching criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $users->firstItem() }}-{{ $users->lastItem() }}
                    {{ __('of') }} {{ $users->total() }} {{ __('registered') }}
                </span>
                <div class="pagination-controls">
                    @if ($users->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $users->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif

    </div>

@endsection
