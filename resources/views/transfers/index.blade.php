@extends('layouts.app')

@section('title', __('Transfers'))
@section('subtitle', __('Move products between warehouses'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Warehouse Transfers') }}</h2>
                <p>{{ $transfers->total() }} {{ __('transfers recorded') }}</p>
            </div>
            <a href="{{ route('transfers.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('New Transfer') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('transfers.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by transfer # or product...') }}">
                </div>

                <input type="date" name="date" value="{{ request('date') }}"
                       class="select-field" onchange="this.form.submit()">

                <select name="from_warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Sources') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}"
                            {{ request('from_warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>

                <select name="to_warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Destinations') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}"
                            {{ request('to_warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                </select>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Transfer #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('From Warehouse') }}</th>
                        <th>{{ __('To Warehouse') }}</th>
                        <th>{{ __('Total Items') }}</th>
                        <th>{{ __('Total Quantity') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr>
                            <td class="cell-mono cell-primary">{{ $transfer->reference_number }}</td>
                            <td class="cell-muted">{{ $transfer->transfer_date->format('d M Y') }}</td>
                            <td class="cell-muted">{{ $transfer->fromWarehouse->name }}</td>
                            <td class="cell-muted">{{ $transfer->toWarehouse->name }}</td>
                            <td class="cell-mono">{{ $transfer->items_count }}</td>
                            <td class="cell-mono">{{ $transfer->items_sum_quantity ?? 0 }}</td>
                            <td>
                                @if ($transfer->status === 'completed')
                                    <span class="badge badge-green">{{ __('Completed') }}</span>
                                @elseif ($transfer->status === 'cancelled')
                                    <span class="badge badge-red">{{ __('Cancelled') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('transfers.show', $transfer) }}"
                                       class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">{{ __('No warehouse transfers found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transfers->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $transfers->firstItem() }}–{{ $transfers->lastItem() }}
                    {{ __('of') }} {{ $transfers->total() }} {{ __('transfers') }}
                </span>
                <div class="pagination-controls">
                    @if ($transfers->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $transfers->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($transfers->getUrlRange(1, $transfers->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $transfers->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($transfers->hasMorePages())
                        <a href="{{ $transfers->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
