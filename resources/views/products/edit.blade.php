@extends('layouts.app')

@section('title', 'Edit Product')
@section('subtitle', 'Update product information')

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/products') }}">Products</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>Edit Product</span>
    </div>

    {{-- Same form layout as Create, pre-filled with real data --}}
    <form action="{{ route('products.update', $product) }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        @method('PUT')
        <div class="card-header">
            <div>
                <h2>Product Details</h2>
                <p>Editing "{{ $product->name }}"</p>
            </div>
        </div>

        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Product Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                    @error('name')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>SKU *</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}" required>
                    @error('sku')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Barcode <span class="hint">(optional)</span></label>
                    <input type="text" name="barcode" class="form-control" value="{{ old('barcode', $product->barcode) }}" placeholder="e.g. 8901234567890">
                    @error('barcode')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
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
                    <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $product->price) }}" required>
                    @error('price')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Minimum Stock <span class="hint">(for low-stock alerts)</span></label>
                    <input type="number" name="minimum_stock" class="form-control" value="{{ old('minimum_stock', $product->minimum_stock) }}" required>
                    @error('minimum_stock')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <label style="display:flex; align-items:center; gap:8px; font-weight:500; height: 36px;">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" {{ old('active', $product->active) ? 'checked' : '' }}>
                        Active
                    </label>
                    @error('active')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card-body" style="padding-top:0;">
            <div class="form-actions">
                <a href="{{ route('products.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Update Product</button>
            </div>
        </div>
    </form>

@endsection
