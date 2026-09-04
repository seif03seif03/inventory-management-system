@extends('layouts.app')

@section('title', __('Supplier Details'))
@section('subtitle', $supplier->name)

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('suppliers.index') }}">{{ __('Suppliers') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>{{ $supplier->name }}</span>
    </div>

    <div class="detail-header" style="margin-top: 12px;">
        <div class="cell-with-avatar">
            <div class="avatar-sq" style="width:48px;height:48px;font-size:15px;">
                {{ mb_strtoupper(mb_substr($supplier->name, 0, 2)) }}
            </div>
            <div>
                <h2 style="margin:0;font-size:17px;">{{ $supplier->name }}</h2>
                @if ($supplier->active)
                    <span class="badge badge-green">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                @endif
            </div>
        </div>
        <div class="row-actions">
            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary">
                <i class="fa-regular fa-pen-to-square"></i> {{ __('Edit') }}
            </a>
            <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" style="display:inline;"
                  onsubmit="return confirm('{{ __('Delete this supplier?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger-outline">
                    <i class="fa-regular fa-trash-can"></i> {{ __('Delete') }}
                </button>
            </form>
        </div>
    </div>

    <div class="card section">
        <div class="card-header"><h2>{{ __('Contact Information') }}</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field">
                    <div class="label">{{ __('Phone') }}</div>
                    <div class="value">{{ $supplier->phone ?: '—' }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Email') }}</div>
                    <div class="value">{{ $supplier->email ?: '—' }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Address') }}</div>
                    <div class="value">{{ $supplier->address ?: '—' }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Receipts') }}</div>
                    <div class="value">{{ $receipts->total() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>{{ __('Recent Stock Receipts') }}</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Receipt #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Total Items') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td class="cell-mono">
                                <a href="{{ route('stock-in.show', $receipt) }}" style="color:inherit;">
                                    {{ $receipt->reference_number }}
                                </a>
                            </td>
                            <td class="cell-muted">{{ $receipt->receipt_date->translatedFormat('d M Y') }}</td>
                            <td class="cell-muted">{{ $receipt->warehouse->name ?? '—' }}</td>
                            <td class="cell-mono">{{ $receipt->items_sum_quantity ?? 0 }}</td>
                            <td>
                                @if ($receipt->isCompleted())
                                    <span class="badge badge-green">{{ __('Completed') }}</span>
                                @elseif ($receipt->isCancelled())
                                    <span class="badge badge-red">{{ __('Cancelled') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Pending') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;">
                                {{ __('No stock receipts for this supplier yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($receipts->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $receipts->firstItem() }}-{{ $receipts->lastItem() }}
                    {{ __('of') }} {{ $receipts->total() }} {{ __('receipts') }}
                </span>
                <div class="pagination-controls">
                    @if ($receipts->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $receipts->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($receipts->getUrlRange(1, $receipts->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $receipts->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($receipts->hasMorePages())
                        <a href="{{ $receipts->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
