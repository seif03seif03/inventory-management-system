@extends('layouts.app')

@section('title', __('Adjustment Details'))
@section('subtitle', $adjustment->reference_number)

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('adjustments.index') }}">{{ __('Inventory Adjustments') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>{{ $adjustment->reference_number }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="card section" style="margin-top:12px;">
        <div class="card-header"><h2>{{ __('Overview') }}</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field">
                    <div class="label">{{ __('Reference Number') }}</div>
                    <div class="value">{{ $adjustment->reference_number }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Adjustment Date') }}</div>
                    <div class="value">{{ $adjustment->adjustment_date->format('d M Y') }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Warehouse') }}</div>
                    <div class="value">{{ $adjustment->warehouse->name }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Reason') }}</div>
                    <div class="value"><span class="badge badge-gray">{{ __(ucfirst($adjustment->reason)) }}</span></div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Status') }}</div>
                    <div class="value">
                        @if ($adjustment->isCompleted())
                            <span class="badge badge-green">{{ __('Completed') }}</span>
                        @else
                            <span class="badge badge-gray">{{ __(ucfirst($adjustment->status)) }}</span>
                        @endif
                    </div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Created By') }}</div>
                    <div class="value">{{ $adjustment->creator->name ?? __('System') }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Net Change') }}</div>
                    <div class="value cell-mono">
                        {{ $adjustment->netQuantity() > 0 ? '+' : '' }}{{ $adjustment->netQuantity() }}
                    </div>
                </div>
                @if ($adjustment->notes)
                    <div class="detail-field" style="grid-column:1/-1;">
                        <div class="label">{{ __('Notes') }}</div>
                        <div class="value">{{ $adjustment->notes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card section">
        <div class="card-header"><h2>{{ __('Adjusted Items') }}</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Direction') }}</th>
                        <th>{{ __('Quantity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustment->items as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">{{ $item->product->sku }}</td>
                            <td>
                                @if ($item->isIncrease())
                                    <span class="badge badge-green">+ {{ __('Increase') }}</span>
                                @else
                                    <span class="badge badge-red">&minus; {{ __('Decrease') }}</span>
                                @endif
                            </td>
                            <td class="cell-mono">
                                {{ $item->signedQuantity() > 0 ? '+' : '' }}{{ $item->signedQuantity() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;padding:40px;">{{ __('No items on this adjustment.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- The ledger rows this document actually produced, so the page proves its
         effect on stock rather than only restating its own lines. --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Stock Movements') }}</h2>
                <p>{{ __('Ledger entries created by this adjustment') }}</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Recorded') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="cell-primary">{{ $movement->product->name ?? '—' }}</td>
                            <td>
                                @if ($movement->type === App\Models\StockMovement::TYPE_IN)
                                    <span class="badge badge-green">{{ __('IN') }}</span>
                                @else
                                    <span class="badge badge-red">{{ __('OUT') }}</span>
                                @endif
                            </td>
                            <td class="cell-mono">
                                {{ $movement->type === App\Models\StockMovement::TYPE_IN ? '+' : '-' }}{{ $movement->quantity }}
                            </td>
                            <td class="cell-muted">{{ $movement->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;padding:40px;">{{ __('No stock movements recorded.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
