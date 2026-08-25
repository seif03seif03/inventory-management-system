@extends('layouts.app')

@section('title', 'Low Stock Report')
@section('subtitle', 'Products at or below their minimum stock')

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Low Stock Report</h2>
                <p>{{ $lowStockRows->total() }} low-stock {{ Str::plural('row', $lowStockRows->total()) }} found</p>
            </div>

            @include('reports.partials.export-buttons', ['route' => 'reports.low-stock.export'])
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('reports.low-stock') }}" method="GET" class="filters-bar">
                <select name="product_id" class="select-field" onchange="this.form.submit()">
                    <option value="">All Products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="category_id" class="select-field" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
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

                @if (request()->hasAny(['product_id', 'category_id', 'warehouse_id']))
                    <a href="{{ route('reports.low-stock') }}" class="btn btn-secondary btn-sm">Clear</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Warehouse</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lowStockRows as $row)
                        <tr>
                            <td class="cell-primary">{{ $row->product_name }}</td>
                            <td class="cell-mono">{{ $row->product_sku }}</td>
                            <td class="cell-muted">{{ $row->warehouse_name }}</td>
                            <td class="cell-mono">{{ (int) $row->current_stock }}</td>
                            <td class="cell-mono">{{ $row->minimum_stock }}</td>
                            <td class="cell-mono">{{ (int) $row->current_stock - (int) $row->minimum_stock }}</td>
                            <td><span class="badge badge-red">Low Stock</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">No low-stock products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($lowStockRows->hasPages())
            <div class="pagination-bar">
                <span>
                    Showing {{ $lowStockRows->firstItem() }}-{{ $lowStockRows->lastItem() }}
                    of {{ $lowStockRows->total() }} rows
                </span>
                <div class="pagination-controls">
                    @if ($lowStockRows->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $lowStockRows->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($lowStockRows->getUrlRange(1, $lowStockRows->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $lowStockRows->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($lowStockRows->hasMorePages())
                        <a href="{{ $lowStockRows->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
