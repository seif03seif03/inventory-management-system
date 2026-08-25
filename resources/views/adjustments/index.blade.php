@extends('layouts.app')

@section('title', __('Inventory Adjustments'))
@section('subtitle', __('Corrections to stock that no receipt or issue explains'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Inventory Adjustments') }}</h2>
                <p>{{ $adjustments->total() }} {{ __('adjustments recorded') }}</p>
            </div>
            @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                <a href="{{ route('adjustments.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-sliders"></i> {{ __('New Adjustment') }}
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom:0;">
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>{{ session('success') }}</div>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="card-body" style="padding-bottom:0;">
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ session('error') }}</div>
                </div>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('adjustments.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by reference # or product...') }}">
                </div>

                <select name="warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Warehouses') }}</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>

                <select name="reason" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Reasons') }}</option>
                    @foreach (App\Models\InventoryAdjustment::REASONS as $reason)
                        <option value="{{ $reason }}" {{ request('reason') === $reason ? 'selected' : '' }}>
                            {{ __(ucfirst($reason)) }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="select-field" title="{{ __('Date From') }}" onchange="this.form.submit()">

                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="select-field" title="{{ __('Date To') }}" onchange="this.form.submit()">

                @if (request()->hasAny(['search', 'warehouse_id', 'reason', 'date_from', 'date_to']))
                    <a href="{{ route('adjustments.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Items') }}</th>
                        <th>{{ __('Created By') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td class="cell-mono cell-primary">{{ $adjustment->reference_number }}</td>
                            <td class="cell-muted">{{ $adjustment->adjustment_date->translatedFormat('d M Y') }}</td>
                            <td class="cell-muted">{{ $adjustment->warehouse->name }}</td>
                            <td><span class="badge badge-gray">{{ __(ucfirst($adjustment->reason)) }}</span></td>
                            <td class="cell-mono">{{ $adjustment->items_count }}</td>
                            <td class="cell-muted">{{ $adjustment->creator->name ?? __('System') }}</td>
                            <td>
                                <div class="row-actions" style="justify-content: flex-start;">
                                    <a href="{{ route('adjustments.show', $adjustment) }}"
                                       class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">{{ __('No adjustments found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($adjustments->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $adjustments->firstItem() }}-{{ $adjustments->lastItem() }}
                    {{ __('of') }} {{ $adjustments->total() }} {{ __('adjustments') }}
                </span>
                <div class="pagination-controls">
                    @if ($adjustments->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $adjustments->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($adjustments->getUrlRange(1, $adjustments->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $adjustments->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($adjustments->hasMorePages())
                        <a href="{{ $adjustments->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
