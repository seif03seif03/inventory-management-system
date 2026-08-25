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
                <p>{{ __('Select a report above to view live inventory data') }}</p>
            </div>
            <a href="{{ route('reports.stock') }}" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-boxes-stacked"></i> {{ __('Current Stock') }}
            </a>
        </div>
        <div class="card-body">
            <div class="chart-placeholder">
                <i class="fa-solid fa-table-list"></i>
                <span>{{ __('Open a report to view filtered tables') }}</span>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h2>{{ __('Stock In vs Stock Out') }}</h2></div>
            <div class="card-body">
                <div class="chart-placeholder" style="height:200px;">
                    <i class="fa-solid fa-right-left"></i>
                    <span>{{ __('Use the movement report for IN and OUT activity') }}</span>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>{{ __('Top Categories by Value') }}</h2></div>
            <div class="card-body">
                <div class="chart-placeholder" style="height:200px;">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>{{ __('Category analytics can be added in a later phase') }}</span>
                </div>
            </div>
        </div>
    </div>

@endsection
