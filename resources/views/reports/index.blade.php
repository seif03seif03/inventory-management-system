@extends('layouts.app')

@section('title', __('Reports'))
@section('subtitle', __('Inventory analytics and reporting'))

@section('content')

    @php
        // Titles and descriptions are translated at render time below, not
        // here, so the keys stay greppable as plain English source strings.
        $reportCards = [
            ['title' => 'Current Stock', 'desc' => 'Snapshot of stock on hand', 'icon' => 'fa-boxes-stacked', 'route' => route('reports.stock')],
            ['title' => 'Stock In', 'desc' => 'Receiving activity over time', 'icon' => 'fa-inbox', 'route' => route('reports.stock-in')],
            ['title' => 'Stock Out', 'desc' => 'Distribution activity over time', 'icon' => 'fa-arrow-up-from-bracket', 'route' => route('reports.stock-out')],
            ['title' => 'Low Stock', 'desc' => 'Items nearing minimum threshold', 'icon' => 'fa-triangle-exclamation', 'route' => route('reports.low-stock')],
            ['title' => 'Stock Movement', 'desc' => 'Full movement audit trail', 'icon' => 'fa-right-left', 'route' => route('reports.movements')],
        ];
    @endphp

    <div class="report-card-grid">
        @foreach ($reportCards as $r)
            <a href="{{ $r['route'] }}" class="report-pick-card">
                <i class="fa-solid {{ $r['icon'] }}"></i>
                <h3>{{ __($r['title']) }}</h3>
                <p>{{ __($r['desc']) }}</p>
            </a>
        @endforeach
    </div>

    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Reports') }}</h2>
                <p>{{ __('Inventory status summary across all warehouses') }}</p>
            </div>
            <a href="{{ route('reports.stock') }}" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-boxes-stacked"></i> {{ __('Current Stock') }}
            </a>
        </div>

        @include('reports.partials.analytics', ['analytics' => $overviewAnalytics, 'chartId' => 'reportsOverviewChart'])
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h2>{{ __('Stock In vs Stock Out') }}</h2>
                <a href="{{ route('reports.movements') }}" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-right-left"></i> {{ __('Open') }}
                </a>
            </div>
            <div class="card-body">
                <canvas id="reportsMovementChart" class="report-chart" height="240" role="img" aria-label="{{ __('Stock In vs Stock Out') }}"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2>{{ __('Top Categories by Stock') }}</h2>
                <a href="{{ route('reports.stock') }}" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-boxes-stacked"></i> {{ __('Open') }}
                </a>
            </div>
            <div class="card-body">
                <canvas id="reportsCategoryChart" class="report-chart" height="240" role="img" aria-label="{{ __('Top Categories by Stock') }}"></canvas>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.InventoryReportCharts.draw('reportsMovementChart', @json($movementChart));
            window.InventoryReportCharts.draw('reportsCategoryChart', @json($categoryChart));
        });
    </script>
@endpush
