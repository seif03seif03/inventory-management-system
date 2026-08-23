@extends('layouts.app')

@section('title', 'Add Category')
@section('subtitle', 'Create a new product category')

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('categories.index') }}">Categories</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>Add Category</span>
    </div>

    <form action="{{ route('categories.store') }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf

        <div class="card-header">
            <div>
                <h2>Category Details</h2>
                <p>Fields marked * are required</p>
            </div>
        </div>

        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Category Name *</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        value="{{ old('name') }}"
                        placeholder="e.g. Electronics"
                        required
                    >
                    @error('name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Short description">{{ old('description') }}</textarea>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <label class="form-check">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}>
                        Active
                    </label>
                    @error('active')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('categories.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Category</button>
            </div>
        </div>
    </form>

@endsection
