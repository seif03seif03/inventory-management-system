@extends('layouts.app')

@section('title', __('Stock Movements'))
@section('subtitle', __('Complete history of every inventory movement'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Movements') }}</h2>
                <p>{{ __('Every stock change across all warehouses') }}</p>
            </div>
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('stock-movements.index') }}" method="GET" class="filters-bar">

                <select name="product_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                            {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="type" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Types') }}</option>
                    <option value="IN"  {{ request('type') === 'IN'  ? 'selected' : '' }}>{{ __('IN') }}</option>
                    <option value="OUT" {{ request('type') === 'OUT' ? 'selected' : '' }}>{{ __('OUT') }}</option>
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

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="select-field" title="{{ __('Date from') }}" onchange="this.form.submit()">

                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="select-field" title="{{ __('Date to') }}" onchange="this.form.submit()">

                @if (request()->hasAny(['product_id', 'type', 'warehouse_id', 'date_from', 'date_to']))
                    <a href="{{ route('stock-movements.index') }}" class="btn btn-secondary btn-sm">
                        {{ __('Clear') }}
                    </a>
                @endif

            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="cell-muted">{{ $movement->created_at->format('d M Y') }}</td>
                            <td class="cell-primary">{{ $movement->product->name }}</td>
                            <td class="cell-mono">{{ $movement->product->sku }}</td>
                            <td>
                                @if ($movement->type === 'IN')
                                    <span class="badge badge-green">{{ __('IN') }}</span>
                                @else
                                    <span class="badge badge-red">{{ __('OUT') }}</span>
                                @endif
                            </td>
                            <td class="cell-mono">
                                @if ($movement->type === 'IN')
                                    +{{ $movement->quantity }}
                                @else
                                    -{{ $movement->quantity }}
                                @endif
                            </td>
                            <td class="cell-muted">{{ $movement->warehouse->name }}</td>
                            <td class="cell-mono">
                                @if ($movement->reference_type === 'stock_in')
                                    <a href="{{ route('stock-in.show', $movement->reference_id) }}"
                                       style="text-decoration: none; color: inherit;">
                                        {{ __('Stock In') }} #{{ $movement->reference_id }}
                                    </a>
                                @elseif ($movement->reference_type === 'stock_out')
                                    <a href="{{ route('stock-out.show', $movement->reference_id) }}"
                                       style="text-decoration: none; color: inherit;">
                                        {{ __('Stock Out') }} #{{ $movement->reference_id }}
                                    </a>
                                @elseif ($movement->reference_type === 'warehouse_transfer')
                                    <a href="{{ route('transfers.show', $movement->reference_id) }}"
                                       style="text-decoration: none; color: inherit;">
                                        {{ __('Transfer') }} #{{ $movement->reference_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                {{ __('No stock movements found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $movements->firstItem() }}–{{ $movements->lastItem() }}
                    {{ __('of') }} {{ $movements->total() }} {{ __('movements') }}
                </span>
                <div class="pagination-controls">
                    @if ($movements->onFirstPage())
                        <button class="page-btn" disabled>
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                    @else
                        <a href="{{ $movements->previousPageUrl() }}" class="page-btn">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    @endif

                    @foreach ($movements->getUrlRange(1, $movements->lastPage()) as $page => $url)
                        <a href="{{ $url }}"
                           class="page-btn {{ $page === $movements->currentPage() ? 'active' : '' }}">
                            {{ $page }}
                        </a>
                    @endforeach

                    @if ($movements->hasMorePages())
                        <a href="{{ $movements->nextPageUrl() }}" class="page-btn">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    @else
                        <button class="page-btn" disabled>
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    @endif
                </div>
            </div>
        @endif

    </div>

@endsection
