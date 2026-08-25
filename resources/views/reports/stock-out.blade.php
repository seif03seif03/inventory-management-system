@extends('layouts.app')

@section('title', __('Stock Out Report'))
@section('subtitle', __('Completed stock issue lines'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Stock Out Report') }}</h2>
                <p>{{ $stockOutItems->total() }} completed issue {{ Str::plural('line', $stockOutItems->total()) }} found</p>
            </div>

            @include('reports.partials.export-buttons', ['route' => 'reports.stock-out.export'])
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('reports.stock-out') }}" method="GET" class="filters-bar">
                <select name="distributor_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Distributors') }}</option>
                    @foreach ($distributors as $distributor)
                        <option value="{{ $distributor->id }}" {{ request('distributor_id') == $distributor->id ? 'selected' : '' }}>
                            {{ $distributor->name }}
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

                <select name="product_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}" class="select-field" title="{{ __('Date from') }}" onchange="this.form.submit()">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="select-field" title="{{ __('Date to') }}" onchange="this.form.submit()">

                @if (request()->hasAny(['distributor_id', 'warehouse_id', 'product_id', 'date_from', 'date_to']))
                    <a href="{{ route('reports.stock-out') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Distributor') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Quantity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOutItems as $item)
                        <tr>
                            <td class="cell-mono cell-primary">{{ $item->stockOut->reference_number }}</td>
                            <td class="cell-muted">{{ $item->stockOut->issue_date->translatedFormat('d M Y') }}</td>
                            <td class="cell-muted">{{ $item->stockOut->distributor->name }}</td>
                            <td class="cell-muted">{{ $item->stockOut->warehouse->name }}</td>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">-{{ $item->quantity }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">{{ __('No completed stock-out lines found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stockOutItems->hasPages())
            <div class="pagination-bar">
                <span>
                    Showing {{ $stockOutItems->firstItem() }}-{{ $stockOutItems->lastItem() }}
                    of {{ $stockOutItems->total() }} lines
                </span>
                <div class="pagination-controls">
                    @if ($stockOutItems->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $stockOutItems->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($stockOutItems->getUrlRange(1, $stockOutItems->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $stockOutItems->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($stockOutItems->hasMorePages())
                        <a href="{{ $stockOutItems->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
