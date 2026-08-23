@extends('layouts.app')

@section('title', 'Supplier Details')
@section('subtitle', 'TechSource Egypt')

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/suppliers') }}">Suppliers</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>TechSource Egypt</span>
    </div>

    <div class="detail-header" style="margin-top: 12px;">
        <div class="cell-with-avatar">
            <div class="avatar-sq" style="width:48px;height:48px;font-size:15px;">AH</div>
            <div>
                <h2 style="margin:0;font-size:17px;">Ahmed Hassan — TechSource Egypt</h2>
                <span class="badge badge-green">Active</span>
            </div>
        </div>
        <div class="row-actions">
            <a href="{{ url('/suppliers/1/edit') }}" class="btn btn-secondary"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
            <button class="btn btn-danger-outline"><i class="fa-regular fa-trash-can"></i> Delete</button>
        </div>
    </div>

    <div class="card section">
        <div class="card-header"><h2>Contact Information</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field"><div class="label">Phone</div><div class="value">+20 100 123 4567</div></div>
                <div class="detail-field"><div class="label">Email</div><div class="value">ahmed@techsource.eg</div></div>
                <div class="detail-field"><div class="label">Address</div><div class="value">12 Tahrir St, Cairo, Egypt</div></div>
                <div class="detail-field"><div class="label">Products Supplied</div><div class="value">42</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Recent Stock Receipts</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Receipt #</th><th>Date</th><th>Warehouse</th><th>Total Items</th><th>Status</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="cell-mono">RCPT-2201</td>
                        <td class="cell-muted">18 Aug 2026</td>
                        <td class="cell-muted">Main Warehouse</td>
                        <td class="cell-mono">100</td>
                        <td><span class="badge badge-green">Completed</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

@endsection
