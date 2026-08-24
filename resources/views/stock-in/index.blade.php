@extends('layouts.app')

@section('title', __('Stock In'))
@section('subtitle', __('Receipts of products coming into the warehouse'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Stock Receipts') }}</h2>
                <p>{{ $stockIns->count() }} {{ __('receipts recorded') }}</p>
            </div>
            <a href="{{ route('stock-in.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('New Stock Receipt') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('stock-in.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by receipt # or product...') }}">
                </div>

                <input type="date" name="date" value="{{ request('date') }}"
                       class="select-field" onchange="this.form.submit()">

                <select name="supplier_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Suppliers') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
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

                <select name="status" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                </select>

                @if (request()->hasAny(['search', 'date', 'supplier_id', 'warehouse_id', 'product_id', 'status']))
                    <a href="{{ route('stock-in.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Receipt #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Total Items') }}</th>
                        <th>{{ __('Total Quantity') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockIns as $stockIn)
                        <tr>
                            <td class="cell-mono cell-primary">{{ $stockIn->reference_number }}</td>
                            <td class="cell-muted">{{ $stockIn->receipt_date->format('d M Y') }}</td>
                            <td class="cell-muted">{{ $stockIn->supplier->name }}</td>
                            <td class="cell-muted">{{ $stockIn->warehouse->name }}</td>
                            <td class="cell-mono">{{ $stockIn->items_count }}</td>
                            <td class="cell-mono">+{{ $stockIn->items_sum_quantity ?? 0 }}</td>
                            <td>
                                @if ($stockIn->status === 'completed')
                                    <span class="badge badge-green">{{ __('Completed') }}</span>
                                @elseif ($stockIn->status === 'cancelled')
                                    <span class="badge badge-red">{{ __('Cancelled') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('stock-in.show', $stockIn) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}"><i class="fa-regular fa-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">{{ __('No stock receipts found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
