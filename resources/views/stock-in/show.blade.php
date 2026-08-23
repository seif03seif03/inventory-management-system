@extends('layouts.app')

@section('title', 'Receipt ' . $stockIn->reference_number)
@section('subtitle', 'Stock In details')

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('stock-in.index') }}">Stock In</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ $stockIn->reference_number }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="detail-header" style="margin-top: 12px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:17px;">Receipt {{ $stockIn->reference_number }}</h2>
            @if ($stockIn->status === 'completed')
                <span class="badge badge-green">Completed</span>
            @elseif ($stockIn->status === 'cancelled')
                <span class="badge badge-red">Cancelled</span>
            @else
                <span class="badge badge-amber">Pending</span>
            @endif
        </div>
        <div class="row-actions">
            <button class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
        </div>
    </div>

    <div class="card section">
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field"><div class="label">Supplier</div><div class="value">{{ $stockIn->supplier->name }}</div></div>
                <div class="detail-field"><div class="label">Warehouse</div><div class="value">{{ $stockIn->warehouse->name }}</div></div>
                <div class="detail-field"><div class="label">Receipt Date</div><div class="value">{{ $stockIn->receipt_date->format('d M Y') }}</div></div>
                <div class="detail-field"><div class="label">Reference #</div><div class="value">{{ $stockIn->reference_number }}</div></div>
                <div class="detail-field"><div class="label">Total Items</div><div class="value">{{ $stockIn->items->count() }}</div></div>
                <div class="detail-field"><div class="label">Total Quantity</div><div class="value">+{{ $stockIn->items->sum('quantity') }}</div></div>
                @if ($stockIn->notes)
                    <div class="detail-field"><div class="label">Notes</div><div class="value">{{ $stockIn->notes }}</div></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Received Items</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Unit Cost</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse ($stockIn->items as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">{{ $item->product->sku }}</td>
                            <td class="cell-mono">+{{ $item->quantity }}</td>
                            <td class="cell-mono">${{ number_format($item->unit_cost, 2) }}</td>
                            <td class="cell-mono">${{ number_format($item->lineTotal(), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">No items on this receipt.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
