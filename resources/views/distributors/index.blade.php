@extends('layouts.app')

@section('title', __('Distributors'))
@section('subtitle', __('Companies that receive products from your warehouse'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Distributors') }}</h2>
                <p>{{ $distributors->count() }} {{ __('distributors') }}</p>
            </div>
            <a href="{{ route('distributors.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('Add Distributor') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <div class="filters-bar">
                <form action="{{ route('distributors.index') }}" method="GET" class="search-field" style="margin: 0;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search distributors...') }}">
                </form>
            </div>
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
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">{{ __('No distributors found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
