@extends('layouts.app')

@section('title', __('Products'))
@section('subtitle', __('Manage all products in your catalog'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('All Products') }}</h2>
                <p>{{ $products->count() }} {{ __('products found') }}</p>
            </div>
            <a href="{{ route('products.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> {{ __('Add Product') }}
            </a>
        </div>

        @if (session('success'))
            <div class="card-body" style="padding-bottom: 0;">
                <span class="badge badge-green">{{ session('success') }}</span>
            </div>
        @endif

        <div class="card-body" style="padding-bottom: 0;">
            <div class="filters-bar">
                <form action="{{ route('products.index') }}" method="GET" class="search-field" style="margin: 0;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name, SKU, or barcode...') }}">
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Stock') }}</th>
                        <th>{{ __('Min. Stock') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <div class="cell-with-avatar">
                                    <div class="avatar-sq">{{ strtoupper(substr($product->name, 0, 2)) }}</div>
                                    <span class="cell-primary">{{ $product->name }}</span>
                                </div>
                            </td>
                            <td class="cell-mono">
                                {{ $product->sku }}
                                @if ($product->barcode)
                                    <br><span class="cell-muted" style="font-size:11px;">{{ $product->barcode }}</span>
                                @endif
                            </td>
                            <td class="cell-muted">{{ $product->category->name }}</td>
                            <td class="cell-primary">${{ number_format($product->price, 2) }}</td>
                            @php $stock = $productStocks[$product->id] ?? 0; @endphp
                            <td class="cell-mono">
                                {{ $stock }}
                                @if ($stock <= $product->minimum_stock && $product->minimum_stock > 0)
                                    <span class="badge badge-red" style="margin-left:4px; margin-right:4px; font-size:10px;">{{ __('Low') }}</span>
                                @endif
                            </td>
                            <td class="cell-muted">{{ $product->minimum_stock }}</td>
                            <td>
                                @if ($product->active)
                                    <span class="badge badge-green">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('products.show', $product) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('View') }}"><i class="fa-regular fa-eye"></i></a>
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary btn-sm btn-icon" title="{{ __('Edit') }}"><i class="fa-regular fa-pen-to-square"></i></a>
                                    <form action="{{ route('products.destroy', $product) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-outline btn-sm btn-icon" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Delete this product?') }}')"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">{{ __('No products found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
