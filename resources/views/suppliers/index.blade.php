@extends('layouts.app')

@section('title', __('Suppliers'))
@section('subtitle', __('Companies that supply products to your warehouse'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Suppliers') }}</h2>
                <p>{{ $suppliers->count() }} {{ __('suppliers') }}</p>
            </div>
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('Add Supplier') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <div class="filters-bar">
                <form action="{{ route('suppliers.index') }}" method="GET" class="search-field" style="margin: 0;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search suppliers...') }}">
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $s)
                        <tr>
                            <td>
                                <div class="cell-with-avatar">
                                    <div class="avatar-sq">{{ strtoupper(substr($s->name, 0, 2)) }}</div>
                                    <span class="cell-primary">{{ $s->name }}</span>
                                </div>
                            </td>
                            <td class="cell-muted">{{ $s->phone ?? '-' }}</td>
                            <td class="cell-muted">{{ $s->email ?? '-' }}</td>
                            <td class="cell-muted" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $s->address ?? '-' }}</td>
                            <td>
                                @if ($s->active)
                                    <span class="badge badge-green">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('suppliers.show', $s) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}"><i class="fa-regular fa-eye"></i></a>
                                    <a href="{{ route('suppliers.edit', $s) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('Edit') }}"><i class="fa-regular fa-pen-to-square"></i></a>
                                    <form action="{{ route('suppliers.destroy', $s) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-outline btn-sm btn-icon" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Delete this supplier?') }}')"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">{{ __('No suppliers found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
