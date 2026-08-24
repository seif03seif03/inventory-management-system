@extends('layouts.app')

@section('title', __('Stock Out'))
@section('subtitle', __('Products issued out of the warehouse to distributors'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Stock Issues') }}</h2>
                <p>{{ $stockOuts->total() }} {{ __('issues recorded') }}</p>
            </div>
            <a href="{{ route('stock-out.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('New Stock Issue') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('stock-out.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by issue # or product...') }}">
                </div>

                <input type="date" name="date" value="{{ request('date') }}"
                       class="select-field" onchange="this.form.submit()">

                <select name="distributor_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Distributors') }}</option>
                    @foreach ($distributors as $distributor)
                        <option value="{{ $distributor->id }}"
                            {{ request('distributor_id') == $distributor->id ? 'selected' : '' }}>
                            {{ $distributor->name }}
                        </option>
                    @endforeach
                </select>

                <select name="warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}"
                            {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>

                <select name="product_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                            {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                    <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>{{ __('Pending') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                </select>

                @if (request()->hasAny(['search', 'date', 'distributor_id', 'warehouse_id', 'product_id', 'status']))
                    <a href="{{ route('stock-out.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Issue #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Distributor') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Total Items') }}</th>
                        <th>{{ __('Total Quantity') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOuts as $stockOut)
                        <tr>
                            <td class="cell-mono cell-primary">{{ $stockOut->reference_number }}</td>
                            <td class="cell-muted">{{ $stockOut->issue_date->format('d M Y') }}</td>
                            <td class="cell-muted">{{ $stockOut->distributor->name }}</td>
                            <td class="cell-muted">{{ $stockOut->warehouse->name }}</td>
                            <td class="cell-mono">{{ $stockOut->items_count }}</td>
                            <td class="cell-mono">-{{ $stockOut->items_sum_quantity ?? 0 }}</td>
                            <td>
                                @if ($stockOut->status === 'completed')
                                    <span class="badge badge-green">{{ __('Completed') }}</span>
                                @elseif ($stockOut->status === 'cancelled')
                                    <span class="badge badge-red">{{ __('Cancelled') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('stock-out.show', $stockOut) }}"
                                       class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">{{ __('No stock issues found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stockOuts->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $stockOuts->firstItem() }}-{{ $stockOuts->lastItem() }}
                    {{ __('of') }} {{ $stockOuts->total() }} {{ __('issues') }}
                </span>
                <div class="pagination-controls">
                    @if ($stockOuts->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $stockOuts->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($stockOuts->getUrlRange(1, $stockOuts->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $stockOuts->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($stockOuts->hasMorePages())
                        <a href="{{ $stockOuts->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
