@extends('layouts.app')

@section('title', __('Distributors'))
@section('subtitle', __('Companies that receive products from your warehouse'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Distributors') }}</h2>
                <p>{{ $distributors->total() }} {{ __('distributors') }}</p>
            </div>
            <a href="{{ route('distributors.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('Add Distributor') }}
            </a>
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('distributors.index') }}" method="GET" class="filters-bar">
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
                    <a href="{{ route('distributors.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Distributor') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th style="text-align: inherit;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($distributors as $d)
                        <tr>
                            <td>
                                <div class="cell-with-avatar">
                                    <div class="avatar-sq">{{ strtoupper(substr($d->name, 0, 2)) }}</div>
                                    <span class="cell-primary">{{ $d->name }}</span>
                                </div>
                            </td>
                            <td class="cell-muted">{{ $d->phone ?? '-' }}</td>
                            <td class="cell-muted">{{ $d->email ?? '-' }}</td>
                            <td class="cell-muted" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $d->address ?? '-' }}</td>
                            <td>
                                @if ($d->active)
                                    <span class="badge badge-green">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('distributors.show', $d) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}"><i class="fa-regular fa-eye"></i></a>
                                    <a href="{{ route('distributors.edit', $d) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('Edit') }}"><i class="fa-regular fa-pen-to-square"></i></a>
                                    <form action="{{ route('distributors.destroy', $d) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-outline btn-sm btn-icon" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Delete this distributor?') }}')"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row
                            colspan="6"
                            icon="fa-truck-fast"
                            :title="__('No distributors yet')"
                            :message="__('A stock issue records who the goods went to, so add the companies you supply.')"
                            :create-url="route('distributors.create')"
                            :create-label="__('Add Distributor')"
                        />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($distributors->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $distributors->firstItem() }}-{{ $distributors->lastItem() }}
                    {{ __('of') }} {{ $distributors->total() }} {{ __('distributors') }}
                </span>
                <div class="pagination-controls">
                    @if ($distributors->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $distributors->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($distributors->getUrlRange(1, $distributors->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $distributors->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($distributors->hasMorePages())
                        <a href="{{ $distributors->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
