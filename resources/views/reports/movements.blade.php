@extends('layouts.app')

@section('title', __('Stock Movement Report'))
@section('subtitle', __('Filtered inventory movement audit trail'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Stock Movement Report') }}</h2>
                <p>{{ $movements->total() }} {{ Str::plural('movement', $movements->total()) }} found</p>
            </div>

            @include('reports.partials.export-buttons', ['route' => 'reports.movements.export'])
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('reports.movements') }}" method="GET" class="filters-bar">
                <select name="product_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>

                <select name="type" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Types') }}</option>
                    <option value="IN" {{ request('type') === 'IN' ? 'selected' : '' }}>{{ __('IN') }}</option>
                    <option value="OUT" {{ request('type') === 'OUT' ? 'selected' : '' }}>{{ __('OUT') }}</option>
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}" class="select-field" title="{{ __('Date from') }}" onchange="this.form.submit()">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="select-field" title="{{ __('Date to') }}" onchange="this.form.submit()">

                @if (request()->hasAny(['product_id', 'warehouse_id', 'type', 'date_from', 'date_to']))
                    <a href="{{ route('reports.movements') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
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
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="cell-muted">{{ $movement->created_at->translatedFormat('d M Y') }}</td>
                            <td class="cell-primary">{{ $movement->product->name }}</td>
                            <td class="cell-mono">{{ $movement->product->sku }}</td>
                            <td class="cell-muted">{{ $movement->warehouse->name }}</td>
                            <td>
                                @if ($movement->type === 'IN')
                                    <span class="badge badge-green">{{ __('IN') }}</span>
                                @else
                                    <span class="badge badge-red">{{ __('OUT') }}</span>
                                @endif
                            </td>
                            <td class="cell-mono">
                                {{ $movement->type === 'IN' ? '+' : '-' }}{{ $movement->quantity }}
                            </td>
                            <td class="cell-mono">
                                @if ($movement->reference_type && $movement->reference_id)
                                    {{ Str::of($movement->reference_type)->replace('_', ' ')->title() }} #{{ $movement->reference_id }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">{{ __('No stock movements found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
            <div class="pagination-bar">
                <span>
                    Showing {{ $movements->firstItem() }}-{{ $movements->lastItem() }}
                    of {{ $movements->total() }} movements
                </span>
                <div class="pagination-controls">
                    @if ($movements->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $movements->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($movements->getUrlRange(1, $movements->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $movements->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($movements->hasMorePages())
                        <a href="{{ $movements->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
