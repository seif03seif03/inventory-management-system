@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Overview of your warehouse inventory')

@section('content')

    {{-- Primary stat cards — all values from DashboardController --}}
    <div class="stat-grid section">

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon blue"><i class="fa-solid fa-box"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalProducts) }}</div>
            <div class="stat-label">Total Products</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon green"><i class="fa-solid fa-boxes-stacked"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalStock) }}</div>
            <div class="stat-label">Total Stock</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon amber"><i class="fa-solid fa-truck-field"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalSuppliers) }}</div>
            <div class="stat-label">Suppliers</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon gray"><i class="fa-solid fa-truck-fast"></i></div>
            </div>
            <div class="stat-value">{{ number_format($totalDistributors) }}</div>
            <div class="stat-label">Distributors</div>
        </div>

    </div>

    {{-- Secondary stat cards --}}
    <div class="stat-grid section">

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon green"><i class="fa-solid fa-inbox"></i></div>
            </div>
            <div class="stat-value">{{ number_format($stockInToday) }}</div>
            <div class="stat-label">Stock In Today</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon blue"><i class="fa-solid fa-arrow-up-from-bracket"></i></div>
            </div>
            <div class="stat-value">{{ number_format($stockOutToday) }}</div>
            <div class="stat-label">Stock Out Today</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="stat-value">{{ number_format($lowStockCount) }}</div>
            <div class="stat-label">Low Stock Items</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <div class="stat-icon amber"><i class="fa-solid fa-chart-column"></i></div>
            </div>
            {{-- Chart placeholder — Phase 4 will render real chart data here --}}
            <div class="stat-value">—</div>
            <div class="stat-label">Stock Overview</div>
        </div>

    </div>

    {{-- Stock overview chart placeholder --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>Stock Overview</h2>
                <p>Stock in vs. stock out over the last 30 days</p>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-placeholder">
                <i class="fa-solid fa-chart-column"></i>
                <span>Chart will render here once connected to data</span>
            </div>
        </div>
    </div>

    {{-- Recent stock movements from the database (Full Width) --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Recent Stock Movements') }}</h2>
                <p>{{ __('Latest activity across all warehouses') }}</p>
            </div>
            <a href="{{ route('stock-movements.index') }}" class="btn btn-secondary btn-sm">{{ __('View all') }}</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentMovements as $m)
                        <tr>
                            <td class="cell-muted">{{ $m->created_at->format('d M Y') }}</td>
                            <td class="cell-primary">{{ $m->product->name }}</td>
                            <td class="cell-muted">{{ $m->warehouse->name }}</td>
                            <td>
                                @if ($m->type === 'IN')
                                    <span class="badge badge-green">{{ __('IN') }}</span>
                                @else
                                    <span class="badge badge-red">{{ __('OUT') }}</span>
                                @endif
                            </td>
                            <td class="cell-mono">
                                @if ($m->type === 'IN')
                                    +{{ $m->quantity }}
                                @else
                                    -{{ $m->quantity }}
                                @endif
                            </td>
                            <td class="cell-mono">
                                @if ($m->reference_type && $m->reference_id)
                                    {{ Str::of($m->reference_type)->replace('_', ' ')->title() }} #{{ $m->reference_id }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#888;">
                                {{ __('No movements yet. Create a Stock In to get started.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Low stock products from the database (Full Width) --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Low Stock Products') }}</h2>
                <p>{{ __('Items at or below their minimum threshold') }}</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Current Stock') }}</th>
                        <th>{{ __('Minimum Stock') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lowStockProducts as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product_name }}</td>
                            <td class="cell-mono">{{ $item->product_sku }}</td>
                            <td class="cell-muted">{{ $item->warehouse_name }}</td>
                            <td class="cell-mono">{{ $item->current_stock }}</td>
                            <td class="cell-mono">{{ $item->minimum_stock }}</td>
                            <td><span class="badge badge-red">{{ __('Low Stock') }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#888;">
                                {{ __('All products are adequately stocked.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
