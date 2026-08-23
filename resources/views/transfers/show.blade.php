@extends('layouts.app')

@section('title', __('Transfer') . ' — ' . $transfer->reference_number)
@section('subtitle', __('Warehouse transfer details'))

@section('content')

    <div class="detail-header">
        <div>
            <div class="breadcrumb">
                <a href="{{ route('transfers.index') }}">{{ __('Transfers') }}</a>
                <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                <span>{{ $transfer->reference_number }}</span>
            </div>
        </div>
        <a href="{{ route('transfers.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Transfers') }}
        </a>
    </div>

    {{-- ── Header card ────────────────────────────────────────── --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ $transfer->reference_number }}</h2>
                <p>{{ $transfer->transfer_date->format('d M Y') }}</p>
            </div>
            <span class="badge badge-green">{{ __('Completed') }}</span>
        </div>

        <div class="card-body">
            <div class="detail-grid">

                <div class="detail-field">
                    <div class="label">{{ __('From Warehouse') }}</div>
                    <div class="value">
                        <i class="fa-solid fa-warehouse" style="margin-right:6px;margin-left:6px;opacity:.6;"></i>
                        {{ $transfer->fromWarehouse->name }}
                    </div>
                </div>

                <div class="detail-field">
                    <div class="label">{{ __('To Warehouse') }}</div>
                    <div class="value">
                        <i class="fa-solid fa-warehouse" style="margin-right:6px;margin-left:6px;opacity:.6;"></i>
                        {{ $transfer->toWarehouse->name }}
                    </div>
                </div>

                <div class="detail-field">
                    <div class="label">{{ __('Transfer Date') }}</div>
                    <div class="value">{{ $transfer->transfer_date->format('d M Y') }}</div>
                </div>

                <div class="detail-field">
                    <div class="label">{{ __('Created By') }}</div>
                    <div class="value">{{ $transfer->creator?->name ?? '-' }}</div>
                </div>

                <div class="detail-field">
                    <div class="label">{{ __('Total Items') }}</div>
                    <div class="value">{{ $transfer->items->count() }}</div>
                </div>

                <div class="detail-field">
                    <div class="label">{{ __('Total Quantity') }}</div>
                    <div class="value">{{ $transfer->items->sum('quantity') }}</div>
                </div>

                @if ($transfer->notes)
                    <div class="detail-field full" style="grid-column: 1 / -1;">
                        <div class="label">{{ __('Notes') }}</div>
                        <div class="value" style="font-weight:400;">{{ $transfer->notes }}</div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ── Items table ─────────────────────────────────────────── --}}
    <div class="card section">
        <div class="card-header">
            <div>
                <h2>{{ __('Transferred Products') }}</h2>
                <p>{{ __('Stock moved OUT from :from and IN to :to', ['from' => $transfer->fromWarehouse->name, 'to' => $transfer->toWarehouse->name]) }}</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Quantity Transferred') }}</th>
                        <th>{{ __('From (OUT)') }}</th>
                        <th>{{ __('To (IN)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transfer->items as $item)
                        <tr>
                            <td class="cell-primary">{{ $item->product->name }}</td>
                            <td class="cell-mono">{{ $item->product->sku }}</td>
                            <td class="cell-mono">{{ $item->quantity }}</td>
                            <td class="cell-muted">
                                <span class="badge badge-red">-{{ $item->quantity }}</span>
                                {{ $transfer->fromWarehouse->name }}
                            </td>
                            <td class="cell-muted">
                                <span class="badge badge-green">+{{ $item->quantity }}</span>
                                {{ $transfer->toWarehouse->name }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
