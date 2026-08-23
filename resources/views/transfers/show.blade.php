@extends('layouts.app')

@section('title', __('Transfer') . ' ' . $warehouseTransfer->reference_number)
@section('subtitle', __('Warehouse transfer details'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('transfers.index') }}">{{ __('Transfers') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>{{ $warehouseTransfer->reference_number }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="detail-header" style="margin-top: 12px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:17px;">{{ __('Transfer') }} {{ $warehouseTransfer->reference_number }}</h2>
            @if ($warehouseTransfer->status === 'completed')
                <span class="badge badge-green">{{ __('Completed') }}</span>
            @elseif ($warehouseTransfer->status === 'cancelled')
                <span class="badge badge-red">{{ __('Cancelled') }}</span>
            @else
                <span class="badge badge-amber">{{ __('Pending') }}</span>
            @endif
        </div>
        <div class="row-actions">
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fa-solid fa-print"></i> {{ __('Print') }}
            </button>
        </div>
    </div>

    <div class="card section">
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field">
                    <div class="label">{{ __('From Warehouse') }}</div>
                    <div class="value">{{ $warehouseTransfer->fromWarehouse->name }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('To Warehouse') }}</div>
                    <div class="value">{{ $warehouseTransfer->toWarehouse->name }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Transfer Date') }}</div>
                    <div class="value">{{ $warehouseTransfer->transfer_date->format('d M Y') }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Reference #') }}</div>
                    <div class="value">{{ $warehouseTransfer->reference_number }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Total Items') }}</div>
                    <div class="value">{{ $warehouseTransfer->items->count() }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Total Quantity') }}</div>
                    <div class="value">{{ $warehouseTransfer->items->sum('quantity') }}</div>
                </div>
                @if ($warehouseTransfer->creator)
                    <div class="detail-field">
                        <div class="label">{{ __('Created By') }}</div>
                        <div class="value">{{ $warehouseTransfer->creator->name }}</div>
                    </div>
                @endif
                @if ($warehouseTransfer->notes)
                    <div class="detail-field">
                        <div class="label">{{ __('Notes') }}</div>
                        <div class="value">{{ $warehouseTransfer->notes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>{{ __('Transferred Items') }}</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Quantity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($warehouseTransfer->items as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">{{ $item->product->sku }}</td>
                            <td class="cell-mono">{{ $item->quantity }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 40px;">
                                {{ __('No items on this transfer.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
