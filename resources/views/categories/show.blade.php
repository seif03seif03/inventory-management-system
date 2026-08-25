@extends('layouts.app')

@section('title', __('Category Details'))
@section('subtitle', __('View category information'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/categories/index') }}">{{ __('Categories') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ $category->name }}</span>
    </div>

    <div class="card section" style="margin-top: 12px; max-width: 760px;">
        <div class="card-header">
            <div>
                <h2>{{ $category->name }}</h2>
                <p>Created {{ $category->created_at->translatedFormat('d M Y') }}</p>
            </div>

            <div class="row-actions">
                <a href="{{ url('/categories/' . $category->id . '/edit') }}" class="btn btn-secondary btn-sm">
                    <i class="fa-regular fa-pen-to-square"></i>
                    Edit
                </a>

                <form action="{{ url('/categories/' . $category->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger-outline btn-sm" onclick="return confirm('Delete this category?')">
                        <i class="fa-regular fa-trash-can"></i>
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    @if ($category->active)
                        <span class="badge badge-green">{{ __('Active') }}</span>
                    @else
                        <span class="badge badge-gray">{{ __('Inactive') }}</span>
                    @endif
                </div>

                <div class="form-group">
                    <label>{{ __('Products') }}</label>
                    <div class="cell-mono">0</div>
                </div>

                <div class="form-group full">
                    <label>{{ __('Description') }}</label>
                    <div class="cell-muted">{{ $category->description ?: 'No description provided.' }}</div>
                </div>
            </div>
        </div>
    </div>

@endsection
