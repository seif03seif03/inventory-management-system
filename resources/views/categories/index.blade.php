@extends('layouts.app')

@section('title', __('Categories'))
@section('subtitle', __('Organize products into categories'))

@section('content')

    <div class="card">

        <div class="card-header">
            <div>
                <h2>{{ __('All Categories') }}</h2>
                <p>{{ $categories->total() }} {{ __('categories') }}</p>
            </div>
            <a href="{{ route('categories.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                {{ __('Add Category') }}
            </a>
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('categories.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by name or description...') }}">
                </div>

                <select name="active" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                </select>

                @if (request()->hasAny(['search', 'active']))
                    <a href="{{ route('categories.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Products') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Created At') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($categories as $category)

                        <tr>

                            <td class="cell-primary">
                                {{ $category->name }}
                            </td>

                            <td class="cell-muted">
                                {{ $category->description ?? '-' }}
                            </td>

                            <td class="cell-mono">
                                0
                            </td>

                            <td>

                                @if ($category->active)

                                    <span class="badge badge-green">
                                        {{ __('Active') }}
                                    </span>

                                @else

                                    <span class="badge badge-gray">
                                        {{ __('Inactive') }}
                                    </span>

                                @endif

                            </td>

                            <td class="cell-muted">
                                {{ $category->created_at->translatedFormat('d M Y') }}
                            </td>

                            <td>

                                <div class="row-actions">

                                    <a
                                        href="{{ route('categories.show', $category) }}"
                                        class="btn btn-secondary btn-sm btn-icon"
                                        title="{{ __('View') }}"
                                    >
                                        <i class="fa-regular fa-eye"></i>
                                    </a>

                                    <a
                                        href="{{ route('categories.edit', $category) }}"
                                        class="btn btn-secondary btn-sm btn-icon"
                                        title="{{ __('Edit') }}"
                                    >
                                        <i class="fa-regular fa-pen-to-square"></i>
                                    </a>

                                    <form action="{{ route('categories.destroy', $category) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-danger-outline btn-sm btn-icon"
                                            title="{{ __('Delete') }}"
                                            onclick="return confirm('{{ __('Delete this category?') }}')"
                                        >
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </form>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <x-empty-row
                            colspan="6"
                            icon="fa-tags"
                            :title="__('No categories yet')"
                            :message="__('Categories group your products so the catalogue and the reports stay navigable.')"
                            :create-url="route('categories.create')"
                            :create-label="__('Add Category')"
                        />

                    @endforelse

                </tbody>

            </table>

        </div>

        @if ($categories->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $categories->firstItem() }}-{{ $categories->lastItem() }}
                    {{ __('of') }} {{ $categories->total() }} {{ __('categories') }}
                </span>
                <div class="pagination-controls">
                    @if ($categories->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $categories->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($categories->getUrlRange(1, $categories->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $categories->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($categories->hasMorePages())
                        <a href="{{ $categories->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif

    </div>

@endsection
