@extends('layouts.app')

@section('title', __('Edit Category'))
@section('subtitle', __('Update category information'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('categories.index') }}">{{ __('Categories') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ __('Edit Category') }}</span>
    </div>

    <form action="{{ route('categories.update', $category) }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        @method('PUT')

        <div class="card-header">
            <div>
                <h2>{{ __('Category Details') }}</h2>
                <p>{{ __('Fields marked * are required') }}</p>
            </div>
        </div>

        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>{{ __('Category Name *') }}</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        value="{{ old('name', $category->name) }}"
                        required
                    >
                    @error('name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label>{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="{{ __('Short description') }}">{{ old('description', $category->description) }}</textarea>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    <label class="form-check">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" {{ old('active', $category->active) ? 'checked' : '' }}>
                        Active
                    </label>
                    @error('active')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('categories.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> {{ __('Update Category') }}</button>
            </div>
        </div>
    </form>

@endsection
