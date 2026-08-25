@extends('layouts.app')

@section('title', __('Add Product'))
@section('subtitle', __('Create a new product in the catalog'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/products') }}">{{ __('Products') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ __('Add Product') }}</span>
    </div>

    <form action="{{ route('products.store') }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        <div class="card-header">
            <div>
                <h2>{{ __('Product Details') }}</h2>
                <p>{{ __('Fields marked * are required') }}</p>
            </div>
        </div>

        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>{{ __('Product Name *') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. iPhone 15" required>
                    @error('name')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('SKU *') }}</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku') }}" placeholder="e.g. PRD-1007" required>
                    @error('sku')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('Barcode') }} <span class="hint">(optional)</span></label>
                    <input type="text" name="barcode" class="form-control" value="{{ old('barcode') }}" placeholder="e.g. 8901234567890">
                    @error('barcode')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('Category *') }}</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">{{ __('Select category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('Price *') }}</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', '0.00') }}" placeholder="0.00" required>
                    @error('price')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('Minimum Stock') }} <span class="hint">(for low-stock alerts)</span></label>
                    <input type="number" name="minimum_stock" class="form-control" value="{{ old('minimum_stock', '0') }}" placeholder="0" required>
                    @error('minimum_stock')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    <label style="display:flex; align-items:center; gap:8px; font-weight:500; height: 36px;">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}>
                        Active
                    </label>
                    @error('active')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label>{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" placeholder="{{ __('Short description of the product') }}">{{ old('description') }}</textarea>
                    @error('description')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card-body" style="padding-top:0;">
            <div class="form-actions">
                <a href="{{ route('products.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> {{ __('Save Product') }}</button>
            </div>
        </div>
    </form>

@endsection
