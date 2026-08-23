@extends('layouts.app')

@section('title', 'Issue ' . $stockOut->reference_number)
@section('subtitle', 'Stock Out details')

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('stock-out.index') }}">Stock Out</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>{{ $stockOut->reference_number }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="detail-header" style="margin-top: 12px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:17px;">Issue {{ $stockOut->reference_number }}</h2>
            @if ($stockOut->status === 'completed')
                <span class="badge badge-green">Completed</span>
            @elseif ($stockOut->status === 'cancelled')
                <span class="badge badge-red">Cancelled</span>
            @else
                <span class="badge badge-amber">Pending</span>
            @endif
        </div>
        <div class="row-actions">
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="card section">
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field">
                    <div class="label">Distributor</div>
                    <div class="value">{{ $stockOut->distributor->name }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">Warehouse</div>
                    <div class="value">{{ $stockOut->warehouse->name }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">Issue Date</div>
                    <div class="value">{{ $stockOut->issue_date->format('d M Y') }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">Reference #</div>
                    <div class="value">{{ $stockOut->reference_number }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">Total Items</div>
                    <div class="value">{{ $stockOut->items->count() }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">Total Quantity</div>
                    <div class="value">-{{ $stockOut->items->sum('quantity') }}</div>
                </div>
                @if ($stockOut->notes)
                    <div class="detail-field">
                        <div class="label">Notes</div>
                        <div class="value">{{ $stockOut->notes }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Issued Items</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockOut->items as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">{{ $item->product->sku }}</td>
                            <td class="cell-mono">-{{ $item->quantity }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 40px;">
                                No items on this issue.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
