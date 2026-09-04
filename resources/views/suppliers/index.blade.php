@extends('layouts.app')

@section('title', __('Suppliers'))
@section('subtitle', __('Companies that supply products to your warehouse'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Suppliers') }}</h2>
                <p>{{ $suppliers->total() }} {{ __('suppliers') }}</p>
            </div>
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('Add Supplier') }}
            </a>
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('suppliers.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by name, email, or phone...') }}">
                </div>

                <select name="active" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                </select>

                @if (request()->hasAny(['search', 'active']))
                    <a href="{{ route('suppliers.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $s)
                        <tr>
                            <td>
                                <div class="cell-with-avatar">
                                    <div class="avatar-sq">{{ strtoupper(substr($s->name, 0, 2)) }}</div>
                                    <span class="cell-primary">{{ $s->name }}</span>
                                </div>
                            </td>
                            <td class="cell-muted">{{ $s->phone ?? '-' }}</td>
                            <td class="cell-muted">{{ $s->email ?? '-' }}</td>
                            <td class="cell-muted" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $s->address ?? '-' }}</td>
                            <td>
                                @if ($s->active)
                                    <span class="badge badge-green">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('suppliers.show', $s) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}"><i class="fa-regular fa-eye"></i></a>
                                    <a href="{{ route('suppliers.edit', $s) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('Edit') }}"><i class="fa-regular fa-pen-to-square"></i></a>
                                    <form action="{{ route('suppliers.destroy', $s) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-outline btn-sm btn-icon" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Delete this supplier?') }}')"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row
                            colspan="6"
                            icon="fa-truck-field"
                            :title="__('No suppliers yet')"
                            :message="__('A stock receipt records who the goods came from, so add the companies you buy from.')"
                            :create-url="route('suppliers.create')"
                            :create-label="__('Add Supplier')"
                        />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $suppliers->firstItem() }}-{{ $suppliers->lastItem() }}
                    {{ __('of') }} {{ $suppliers->total() }} {{ __('suppliers') }}
                </span>
                <div class="pagination-controls">
                    @if ($suppliers->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $suppliers->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($suppliers->getUrlRange(1, $suppliers->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $suppliers->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($suppliers->hasMorePages())
                        <a href="{{ $suppliers->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
