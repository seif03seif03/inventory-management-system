@extends('layouts.app')

@section('title', 'Stock In Report')
@section('subtitle', 'Completed stock receipt lines')

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Stock In Report</h2>
                <p>{{ $stockInItems->total() }} completed receipt {{ Str::plural('line', $stockInItems->total()) }} found</p>
            </div>

            @include('reports.partials.export-buttons', ['route' => 'reports.stock-in.export'])
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('reports.stock-in') }}" method="GET" class="filters-bar">
                <select name="supplier_id" class="select-field" onchange="this.form.submit()">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>

                <select name="warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>

                <select name="product_id" class="select-field" onchange="this.form.submit()">
                    <option value="">All Products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}" class="select-field" title="Date from" onchange="this.form.submit()">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="select-field" title="Date to" onchange="this.form.submit()">

                @if (request()->hasAny(['supplier_id', 'warehouse_id', 'product_id', 'date_from', 'date_to']))
                    <a href="{{ route('reports.stock-in') }}" class="btn btn-secondary btn-sm">Clear</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Warehouse</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockInItems as $item)
                        <tr>
                            <td class="cell-mono cell-primary">{{ $item->stockIn->reference_number }}</td>
                            <td class="cell-muted">{{ $item->stockIn->receipt_date->format('d M Y') }}</td>
                            <td class="cell-muted">{{ $item->stockIn->supplier->name }}</td>
                            <td class="cell-muted">{{ $item->stockIn->warehouse->name }}</td>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">+{{ $item->quantity }}</td>
                            <td class="cell-mono">{{ number_format($item->unit_cost, 2) }}</td>
                            <td class="cell-mono">{{ number_format($item->lineTotal(), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">No completed stock-in lines found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stockInItems->hasPages())
            <div class="pagination-bar">
                <span>
                    Showing {{ $stockInItems->firstItem() }}-{{ $stockInItems->lastItem() }}
                    of {{ $stockInItems->total() }} lines
                </span>
                <div class="pagination-controls">
                    @if ($stockInItems->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $stockInItems->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($stockInItems->getUrlRange(1, $stockInItems->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $stockInItems->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($stockInItems->hasMorePages())
                        <a href="{{ $stockInItems->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
