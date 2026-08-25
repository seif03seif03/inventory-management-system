@extends('layouts.app')

@section('title', __('Warehouses'))
@section('subtitle', __('All warehouse locations'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Warehouses') }}</h2>
                <p>{{ $warehouses->total() }} {{ __('locations') }}</p>
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

        @if (session('error'))
            <div class="card-body" style="padding-bottom: 0;">
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ session('error') }}</div>
                </div>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('warehouses.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by name or location...') }}">
                </div>

                <select name="active" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                </select>

                @if (request()->hasAny(['search', 'active']))
                    <a href="{{ route('warehouses.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
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
                            <td class="cell-mono">{{ $warehouseStock[$warehouse->id]['products'] ?? 0 }}</td>
                            <td class="cell-mono">{{ number_format($warehouseStock[$warehouse->id]['quantity'] ?? 0) }}</td>
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

        @if ($warehouses->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $warehouses->firstItem() }}-{{ $warehouses->lastItem() }}
                    {{ __('of') }} {{ $warehouses->total() }} {{ __('locations') }}
                </span>
                <div class="pagination-controls">
                    @if ($warehouses->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $warehouses->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($warehouses->getUrlRange(1, $warehouses->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $warehouses->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($warehouses->hasMorePages())
                        <a href="{{ $warehouses->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
