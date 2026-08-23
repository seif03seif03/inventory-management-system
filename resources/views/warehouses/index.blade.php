@extends('layouts.app')

@section('title', __('Warehouses'))
@section('subtitle', __('All warehouse locations'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Warehouses') }}</h2>
                <p>{{ $warehouses->count() }} {{ __('locations') }}</p>
            </div>
            <a href="{{ route('warehouses.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('Add Warehouse') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <div class="filters-bar">
                <form action="{{ route('warehouses.index') }}" method="GET" class="search-field" style="margin: 0;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search warehouses...') }}">
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Products') }}</th>
                        <th>{{ __('Stock Quantity') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td>
                                <div class="cell-with-avatar">
                                    <div class="avatar-sq"><i class="fa-solid fa-warehouse"></i></div>
                                    <span class="cell-primary">{{ $warehouse->name }}</span>
                                </div>
                            </td>
                            <td class="cell-muted">{{ $warehouse->location ?? '-' }}</td>
                            <td class="cell-mono">0</td>
                            <td class="cell-mono">0</td>
                            <td>
                                @if ($warehouse->active)
                                    <span class="badge badge-green">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('warehouses.show', $warehouse) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}"><i class="fa-regular fa-eye"></i></a>
                                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('Edit') }}"><i class="fa-regular fa-pen-to-square"></i></a>
                                    <form action="{{ route('warehouses.destroy', $warehouse) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-outline btn-sm btn-icon" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Delete this warehouse?') }}')"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">{{ __('No warehouses found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
