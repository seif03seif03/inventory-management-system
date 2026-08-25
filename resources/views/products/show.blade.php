@extends('layouts.app')

@section('title', 'Product Details')
@section('subtitle', $product->name . ' — ' . $product->sku)

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/products') }}">Products</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ $product->name }}</span>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="detail-header" style="margin-top: 12px;">
        <div class="cell-with-avatar">
            <div class="avatar-sq" style="width:48px;height:48px;font-size:15px;">{{ strtoupper(substr($product->name, 0, 2)) }}</div>
            <div>
                <h2 style="margin:0;font-size:17px;">{{ $product->name }}</h2>
                @if ($product->active)
                    <span class="badge badge-green">Active</span>
                @else
                    <span class="badge badge-gray">Inactive</span>
                @endif
                @if ($currentStock <= $product->minimum_stock && $product->minimum_stock > 0)
                    <span class="badge badge-red">Low Stock</span>
                @endif
            </div>
        </div>
        <div class="row-actions">
            <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Delete this product?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger-outline"><i class="fa-regular fa-trash-can"></i> Delete</button>
            </form>
        </div>
    </div>

    <div class="card section">
        <div class="card-header"><h2>Overview</h2></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-field"><div class="label">SKU</div><div class="value">{{ $product->sku }}</div></div>
                <div class="detail-field"><div class="label">Barcode</div><div class="value">{{ $product->barcode ?? '—' }}</div></div>
                <div class="detail-field"><div class="label">Category</div><div class="value">{{ $product->category->name }}</div></div>
                <div class="detail-field"><div class="label">Price</div><div class="value">${{ number_format($product->price, 2) }}</div></div>
                <div class="detail-field"><div class="label">Current Stock</div><div class="value">{{ $currentStock }} pcs</div></div>
                <div class="detail-field"><div class="label">Minimum Stock</div><div class="value">{{ $product->minimum_stock }} pcs</div></div>
            </div>
            @if ($product->description)
                <div class="detail-field" style="margin-top:16px;">
                    <div class="label">Description</div>
                    <div class="value" style="font-weight:400;">{{ $product->description }}</div>
                </div>
            @endif
        </div>
    </div>

    @if ($product->barcode)
        <div class="card section">
            <div class="card-header">
                <div>
                    <h2>QR Code</h2>
                    <p>Scan to identify this product (encodes ID, SKU, and barcode only)</p>
                </div>
            </div>
            <div class="card-body" style="display:flex;align-items:center;gap:20px;">
                <div id="productQr"></div>
                <div class="cell-muted" style="font-size:13px;line-height:1.6;">
                    Product ID: {{ $product->id }}<br>
                    SKU: {{ $product->sku }}<br>
                    Barcode: {{ $product->barcode }}
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Stock Movement History</h2>
                <p>Most recent movements for this product, across all warehouses</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Quantity</th><th>Warehouse</th><th>Reference</th></tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="cell-muted">{{ $movement->created_at->format('d M Y') }}</td>
                            <td>
                                @if ($movement->type === 'IN')
                                    <span class="badge badge-green">IN</span>
                                @else
                                    <span class="badge badge-red">OUT</span>
                                @endif
                            </td>
                            <td class="cell-mono">{{ $movement->type === 'IN' ? '+' : '-' }}{{ $movement->quantity }}</td>
                            <td class="cell-muted">{{ $movement->warehouse->name ?? '—' }}</td>
                            <td class="cell-mono">
                                @if ($movement->reference_type === 'stock_in')
                                    <a href="{{ route('stock-in.show', $movement->reference_id) }}" style="text-decoration:none;color:inherit;">Stock In #{{ $movement->reference_id }}</a>
                                @elseif ($movement->reference_type === 'stock_out')
                                    <a href="{{ route('stock-out.show', $movement->reference_id) }}" style="text-decoration:none;color:inherit;">Stock Out #{{ $movement->reference_id }}</a>
                                @elseif ($movement->reference_type === 'warehouse_transfer')
                                    <a href="{{ route('transfers.show', $movement->reference_id) }}" style="text-decoration:none;color:inherit;">Transfer #{{ $movement->reference_id }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;color:#888;">No stock movements recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@if ($product->barcode)
    @push('scripts')
    {{-- Lightweight client-side QR rendering — no PHP/Composer package needed.
         Served from public/js rather than a CDN: this is an internal system
         that may run on a LAN or offline, and an unreachable CDN would leave
         the QR panel silently blank. --}}
    <script src="{{ asset('js/qrcode.min.js') }}"></script>
    <script>
        new QRCode(document.getElementById("productQr"), {
            text: JSON.stringify({
                id: {{ $product->id }},
                sku: @json($product->sku),
                barcode: @json($product->barcode)
            }),
            width: 120,
            height: 120,
        });
    </script>
    @endpush
@endif
