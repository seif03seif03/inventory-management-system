@extends('layouts.app')

@section('title', __('Categories'))
@section('subtitle', __('Organize products into categories'))

@section('content')

    <div class="card">

        <div class="card-header">
            <div>
                <h2>{{ __('All Categories') }}</h2>
                <p>{{ $categories->count() }} {{ __('categories') }}</p>
            </div>
            <a href="{{ route('categories.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                {{ __('Add Category') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <div class="filters-bar">
                <form action="{{ route('categories.index') }}" method="GET" class="search-field" style="margin: 0;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="{{ __('Search categories...') }}"
                    >
                </form>
            </div>
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
                                {{ $category->created_at->format('d M Y') }}
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

                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                {{ __('No categories found.') }}
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

@endsection
