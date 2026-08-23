@extends('layouts.app')

@section('title', 'Distributor Details')
@section('subtitle', 'Cairo Retail Group')

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/distributors') }}">Distributors</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>Cairo Retail Group</span>
    </div>

    <div class="detail-header" style="margin-top: 12px;">
        <div class="cell-with-avatar">
            <div class="avatar-sq" style="width:48px;height:48px;font-size:15px;">MA</div>
            <div>
                <h2 style="margin:0;font-size:17px;">Mona Adel — Cairo Retail Group</h2>
                <span class="badge badge-green">Active</span>
            </div>
        </div>
        <div class="row-actions">
            <a href="{{ url('/distributors/1/edit') }}" class="btn btn-secondary"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
            <button class="btn btn-danger-outline"><i class="fa-regular fa-trash-can"></i> Delete</button>
        </div>
    </div>

    <div class="card section">
        <div class="card-header"><h2>Contact Information</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field"><div class="label">Phone</div><div class="value">+20 111 222 3344</div></div>
                <div class="detail-field"><div class="label">Email</div><div class="value">mona@cairoretail.com</div></div>
                <div class="detail-field"><div class="label">Address</div><div class="value">4 Zamalek St, Cairo, Egypt</div></div>
                <div class="detail-field"><div class="label">Total Orders</div><div class="value">76</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Recent Stock Issues</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Issue #</th><th>Date</th><th>Warehouse</th><th>Total Items</th><th>Status</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="cell-mono">ISS-1187</td>
                        <td class="cell-muted">10 Aug 2026</td>
                        <td class="cell-muted">Main Warehouse</td>
                        <td class="cell-mono">30</td>
                        <td><span class="badge badge-green">Completed</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

@endsection
