@extends('layouts.app')

@section('title', 'Receipt ' . $stockIn->reference_number)
@section('subtitle', __('Stock In details'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('stock-in.index') }}">{{ __('Stock In') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ $stockIn->reference_number }}</span>
    </div>

    <div class="detail-header" style="margin-top: 12px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:17px;">Receipt {{ $stockIn->reference_number }}</h2>
            @if ($stockIn->status === 'completed')
                <span class="badge badge-green">{{ __('Completed') }}</span>
            @elseif ($stockIn->status === 'cancelled')
                <span class="badge badge-red">{{ __('Cancelled') }}</span>
            @else
                <span class="badge badge-amber">{{ __('Pending') }}</span>
            @endif
        </div>
        <div class="row-actions">
            <button class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> {{ __('Print') }}</button>
        </div>
    </div>

    <div class="card section">
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field"><div class="label">{{ __('Supplier') }}</div><div class="value">{{ $stockIn->supplier->name }}</div></div>
                <div class="detail-field"><div class="label">{{ __('Warehouse') }}</div><div class="value">{{ $stockIn->warehouse->name }}</div></div>
                <div class="detail-field"><div class="label">{{ __('Receipt Date') }}</div><div class="value">{{ $stockIn->receipt_date->translatedFormat('d M Y') }}</div></div>
                <div class="detail-field"><div class="label">{{ __('Reference #') }}</div><div class="value">{{ $stockIn->reference_number }}</div></div>
                <div class="detail-field"><div class="label">{{ __('Total Items') }}</div><div class="value">{{ $stockIn->items->count() }}</div></div>
                <div class="detail-field"><div class="label">{{ __('Total Quantity') }}</div><div class="value">+{{ $stockIn->items->sum('quantity') }}</div></div>
                @if ($stockIn->notes)
                    <div class="detail-field"><div class="label">{{ __('Notes') }}</div><div class="value">{{ $stockIn->notes }}</div></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>{{ __('Received Items') }}</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit Cost') }}</th><th>{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @forelse ($stockIn->items as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">{{ $item->product->sku }}</td>
                            <td class="cell-mono">+{{ $item->quantity }}</td>
                            <td class="cell-mono">@money($item->unit_cost)</td>
                            <td class="cell-mono">@money($item->lineTotal())</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">{{ __('No items on this receipt.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
