@extends('layouts.app')

@section('title', 'Add Product')
@section('subtitle', 'Create a new product in the catalog')

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/products') }}">Products</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>Add Product</span>
    </div>

    <form action="{{ route('products.store') }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        <div class="card-header">
            <div>
                <h2>Product Details</h2>
                <p>Fields marked * are required</p>
            </div>
        </div>

        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Product Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. iPhone 15" required>
                    @error('name')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>SKU *</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku') }}" placeholder="e.g. PRD-1007" required>
                    @error('sku')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select category</option>
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
                    <label>Price *</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', '0.00') }}" placeholder="0.00" required>
                    @error('price')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Minimum Stock <span class="hint">(for low-stock alerts)</span></label>
                    <input type="number" name="minimum_stock" class="form-control" value="{{ old('minimum_stock', '0') }}" placeholder="0" required>
                    @error('minimum_stock')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Status</label>
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
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Short description of the product">{{ old('description') }}</textarea>
                    @error('description')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card-body" style="padding-top:0;">
            <div class="form-actions">
                <a href="{{ route('products.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Product</button>
            </div>
        </div>
    </form>

@endsection
