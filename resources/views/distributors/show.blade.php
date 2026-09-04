@extends('layouts.app')

@section('title', __('Distributor Details'))
@section('subtitle', $distributor->name)

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('distributors.index') }}">{{ __('Distributors') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>{{ $distributor->name }}</span>
    </div>

    <div class="detail-header" style="margin-top: 12px;">
        <div class="cell-with-avatar">
            <div class="avatar-sq" style="width:48px;height:48px;font-size:15px;">
                {{ mb_strtoupper(mb_substr($distributor->name, 0, 2)) }}
            </div>
            <div>
                <h2 style="margin:0;font-size:17px;">{{ $distributor->name }}</h2>
                @if ($distributor->active)
                    <span class="badge badge-green">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                @endif
            </div>
        </div>
        <div class="row-actions">
            <a href="{{ route('distributors.edit', $distributor) }}" class="btn btn-secondary">
                <i class="fa-regular fa-pen-to-square"></i> {{ __('Edit') }}
            </a>
            <form action="{{ route('distributors.destroy', $distributor) }}" method="POST" style="display:inline;"
                  onsubmit="return confirm('{{ __('Delete this distributor?') }}')">
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
                    <div class="value">{{ $distributor->phone ?: '—' }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Email') }}</div>
                    <div class="value">{{ $distributor->email ?: '—' }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Address') }}</div>
                    <div class="value">{{ $distributor->address ?: '—' }}</div>
                </div>
                <div class="detail-field">
                    <div class="label">{{ __('Total Orders') }}</div>
                    <div class="value">{{ $issues->total() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>{{ __('Recent Stock Issues') }}</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Issue #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Total Items') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($issues as $issue)
                        <tr>
                            <td class="cell-mono">
                                <a href="{{ route('stock-out.show', $issue) }}" style="color:inherit;">
                                    {{ $issue->reference_number }}
                                </a>
                            </td>
                            <td class="cell-muted">{{ $issue->issue_date->translatedFormat('d M Y') }}</td>
                            <td class="cell-muted">{{ $issue->warehouse->name ?? '—' }}</td>
                            <td class="cell-mono">{{ $issue->items_sum_quantity ?? 0 }}</td>
                            <td>
                                @if ($issue->isCompleted())
                                    <span class="badge badge-green">{{ __('Completed') }}</span>
                                @elseif ($issue->isCancelled())
                                    <span class="badge badge-red">{{ __('Cancelled') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Pending') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;">
                                {{ __('No stock issues for this distributor yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($issues->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $issues->firstItem() }}-{{ $issues->lastItem() }}
                    {{ __('of') }} {{ $issues->total() }} {{ __('issues') }}
                </span>
                <div class="pagination-controls">
                    @if ($issues->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $issues->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($issues->getUrlRange(1, $issues->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $issues->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($issues->hasMorePages())
                        <a href="{{ $issues->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
