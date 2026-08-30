@extends('layouts.app')

@section('title', __('Stock Report'))
@section('subtitle', __('Current stock by product and warehouse'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Stock Report') }}</h2>
                <p>{{ $stockRows->total() }} stock {{ Str::plural('row', $stockRows->total()) }} found</p>
            </div>

            @include('reports.partials.export-buttons', ['route' => 'reports.stock.export'])
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('reports.stock') }}" method="GET" class="filters-bar">
                <select name="product_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="category_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
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

                <select name="status" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="in" {{ request('status') === 'in' ? 'selected' : '' }}>{{ __('In Stock') }}</option>
                    <option value="low" {{ request('status') === 'low' ? 'selected' : '' }}>{{ __('Low Stock') }}</option>
                    <option value="out" {{ request('status') === 'out' ? 'selected' : '' }}>{{ __('Out of Stock') }}</option>
                </select>

                @if (request()->hasAny(['product_id', 'category_id', 'warehouse_id', 'status']))
                    <a href="{{ route('reports.stock') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        @include('reports.partials.analytics', ['analytics' => $stockAnalytics, 'chartId' => 'stockReportChart'])

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Current Stock') }}</th>
                        <th>{{ __('Minimum Stock') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockRows as $row)
                        @php
                            $currentStock = (int) $row->current_stock;
                        @endphp
                        <tr>
                            <td class="cell-primary">
                                {{ $row->product_name }}
                                @unless ($row->product_active)
                                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                                @endunless
                            </td>
                            <td class="cell-mono">{{ $row->product_sku }}</td>
                            <td class="cell-muted">{{ $row->category_name }}</td>
                            <td class="cell-muted">{{ $row->warehouse_name }}</td>
                            <td class="cell-mono">{{ $currentStock }}</td>
                            <td class="cell-mono">{{ $row->minimum_stock }}</td>
                            <td>
                                @if ($currentStock <= 0)
                                    <span class="badge badge-red">{{ __('Out of Stock') }}</span>
                                @elseif ($currentStock <= $row->minimum_stock)
                                    <span class="badge badge-amber">{{ __('Low Stock') }}</span>
                                @else
                                    <span class="badge badge-green">{{ __('In Stock') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">{{ __('No stock rows found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stockRows->hasPages())
            <div class="pagination-bar">
                <span>
                    Showing {{ $stockRows->firstItem() }}-{{ $stockRows->lastItem() }}
                    of {{ $stockRows->total() }} rows
                </span>
                <div class="pagination-controls">
                    @if ($stockRows->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $stockRows->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($stockRows->getUrlRange(1, $stockRows->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $stockRows->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($stockRows->hasMorePages())
                        <a href="{{ $stockRows->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
