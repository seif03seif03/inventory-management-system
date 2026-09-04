@extends('layouts.app')

@section('title', __('Warehouse Transfers'))
@section('subtitle', __('Move stock between warehouses'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Transfers') }}</h2>
                <p>{{ $transfers->total() }} {{ __('transfers recorded') }}</p>
            </div>
            @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                <a href="{{ route('transfers.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-right-left"></i> {{ __('New Transfer') }}
                </a>
            @endif
        </div>

        <div class="card-body" style="padding-bottom:0;">
            <form action="{{ route('transfers.index') }}" method="GET" class="filters-bar">

                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by reference #...') }}">
                </div>

                <select name="from_warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('From: All Warehouses') }}</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('from_warehouse_id') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>

                <select name="to_warehouse_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('To: All Warehouses') }}</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('to_warehouse_id') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="select-field" title="{{ __('Date From') }}" onchange="this.form.submit()">

                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="select-field" title="{{ __('Date To') }}" onchange="this.form.submit()">

                <select name="status" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="{{ \App\Models\WarehouseTransfer::STATUS_COMPLETED }}"
                        {{ request('status') === \App\Models\WarehouseTransfer::STATUS_COMPLETED ? 'selected' : '' }}>
                        {{ __('Completed') }}
                    </option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                </select>

                @if (request()->hasAny(['search', 'from_warehouse_id', 'to_warehouse_id', 'date', 'date_from', 'date_to', 'status']))
                    <a href="{{ route('transfers.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Reference #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('From') }}</th>
                        <th>{{ __('To') }}</th>
                        <th>{{ __('Items') }}</th>
                        <th>{{ __('Total Qty') }}</th>
                        <th>{{ __('Created By') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr>
                            <td class="cell-mono cell-primary">{{ $transfer->reference_number }}</td>
                            <td class="cell-muted">{{ $transfer->transfer_date->translatedFormat('d M Y') }}</td>
                            <td class="cell-muted">
                                <i class="fa-solid fa-warehouse" style="margin-right:4px;margin-left:4px;opacity:.5;"></i>
                                {{ $transfer->fromWarehouse->name }}
                            </td>
                            <td class="cell-muted">
                                <i class="fa-solid fa-warehouse" style="margin-right:4px;margin-left:4px;opacity:.5;"></i>
                                {{ $transfer->toWarehouse->name }}
                            </td>
                            <td class="cell-mono">{{ $transfer->items_count }}</td>
                            <td class="cell-mono">{{ $transfer->items_sum_quantity ?? 0 }}</td>
                            <td class="cell-muted">{{ $transfer->creator?->name ?? '-' }}</td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('transfers.show', $transfer) }}"
                                       class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        {{-- No create link: the header button is role-gated, and
                             an empty state must not offer what the page does not. --}}
                        <x-empty-row
                            colspan="8"
                            icon="fa-truck-ramp-box"
                            :title="__('No transfers yet')"
                            :message="__('A transfer moves stock between two warehouses without changing the total.')"
                        />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transfers->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $transfers->firstItem() }}–{{ $transfers->lastItem() }}
                    {{ __('of') }} {{ $transfers->total() }}
                </span>
                <div class="pagination-controls">
                    @if ($transfers->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $transfers->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($transfers->getUrlRange(1, $transfers->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $transfers->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($transfers->hasMorePages())
                        <a href="{{ $transfers->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
