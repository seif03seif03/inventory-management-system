@extends('layouts.app')

@section('title', 'Product Details')
@section('subtitle', 'iPhone 15 — PRD-1001')

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/products') }}">Products</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>iPhone 15</span>
    </div>

    <div class="detail-header" style="margin-top: 12px;">
        <div class="cell-with-avatar">
            <div class="avatar-sq" style="width:48px;height:48px;font-size:15px;">IP</div>
            <div>
                <h2 style="margin:0;font-size:17px;">iPhone 15</h2>
                <span class="badge badge-green">In Stock</span>
            </div>
        </div>
        <div class="row-actions">
            <a href="{{ url('/products/1/edit') }}" class="btn btn-secondary"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
            <button class="btn btn-danger-outline"><i class="fa-regular fa-trash-can"></i> Delete</button>
        </div>
    </div>

    <div class="card section">
        <div class="card-header"><h2>Overview</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field"><div class="label">SKU</div><div class="value">PRD-1001</div></div>
                <div class="detail-field"><div class="label">Category</div><div class="value">Electronics</div></div>
                <div class="detail-field"><div class="label">Price</div><div class="value">$799.00</div></div>
                <div class="detail-field"><div class="label">Current Stock</div><div class="value">120 pcs</div></div>
                <div class="detail-field"><div class="label">Minimum Stock</div><div class="value">20 pcs</div></div>
                <div class="detail-field"><div class="label">Unit</div><div class="value">Piece</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Stock Movement History</h2>
                <p>All recorded movements for this product</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Quantity</th><th>Warehouse</th><th>Reference</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="cell-muted">18 Aug 2026</td>
                        <td><span class="badge badge-green">IN</span></td>
                        <td class="cell-mono">+100</td>
                        <td class="cell-muted">Main Warehouse</td>
                        <td class="cell-mono">RCPT-2201</td>
                    </tr>
                    <tr>
                        <td class="cell-muted">10 Aug 2026</td>
                        <td><span class="badge badge-red">OUT</span></td>
                        <td class="cell-mono">-30</td>
                        <td class="cell-muted">Main Warehouse</td>
                        <td class="cell-mono">ISS-1187</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

@endsection
