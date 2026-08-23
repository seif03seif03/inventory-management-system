@extends('layouts.app')

@section('title', 'Reports')
@section('subtitle', 'Inventory analytics and reporting')

@section('content')

    @php
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
                <h3>{{ $r['title'] }}</h3>
                <p>{{ $r['desc'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="card section">
        <div class="card-header">
            <div>
                <h2>Reports</h2>
                <p>Select a report above to view live inventory data</p>
            </div>
            <a href="{{ route('reports.stock') }}" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-boxes-stacked"></i> Current Stock
            </a>
        </div>
        <div class="card-body">
            <div class="chart-placeholder">
                <i class="fa-solid fa-table-list"></i>
                <span>Open a report to view filtered tables</span>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h2>Stock In vs Stock Out</h2></div>
            <div class="card-body">
                <div class="chart-placeholder" style="height:200px;">
                    <i class="fa-solid fa-right-left"></i>
                    <span>Use the movement report for IN and OUT activity</span>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Top Categories by Value</h2></div>
            <div class="card-body">
                <div class="chart-placeholder" style="height:200px;">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Category analytics can be added in a later phase</span>
                </div>
            </div>
        </div>
    </div>

@endsection
