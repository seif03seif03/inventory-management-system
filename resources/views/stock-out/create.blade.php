@extends('layouts.app')

@section('title', 'New Stock Issue')
@section('subtitle', 'Record products distributed out of the warehouse')

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('stock-out.index') }}">Stock Out</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>New Issue</span>
    </div>

    {{-- Session error (e.g. mismatched array lengths) --}}
    @if (session('error'))
        <div class="alert alert-danger" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Stock errors: one or more products had insufficient stock.
         The controller flashes an array of error strings. --}}
    @if (session('stockErrors'))
        <div class="alert alert-danger" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <strong>Could not save issue — insufficient stock:</strong>
                <ul style="margin: 6px 0 0 18px;">
                    @foreach (session('stockErrors') as $stockError)
                        <li>{{ $stockError }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{--
        ONE form wraps both cards (issue details + items), so all fields
        are submitted together in a single POST request.

        How available stock works:
        The controller passes $stocks as a nested PHP array:
            $stocks[warehouseId][productId] = currentStock

        We JSON-encode it into a hidden <script> variable.
        When the user picks a warehouse, a tiny JS function reads
        the correct stock numbers and updates the "Available" cells.
        No AJAX needed — everything is baked into the page.
    --}}
    <script>
        // Stock lookup table: stocks[warehouseId][productId] = available quantity
        const stockData = @json($stocks);
    </script>

    <form action="{{ route('stock-out.store') }}" method="POST">
        @csrf

        <div class="card section" style="margin-top: 12px;">
            <div class="card-header"><h2>Issue Details</h2></div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="form-group"><label>Distributor *</label>
                        <select name="distributor_id" class="form-control" required>
                            <option value="">Select distributor</option>
                            @foreach ($distributors as $distributor)
                                <option value="{{ $distributor->id }}"
                                    {{ old('distributor_id') == $distributor->id ? 'selected' : '' }}>
                                    {{ $distributor->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('distributor_id')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- When this changes, JS re-reads available stock for every row --}}
                    <div class="form-group"><label>Warehouse *</label>
                        <select name="warehouse_id" id="warehouseSelect" class="form-control" required>
                            <option value="">Select warehouse</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}"
                                    {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>Issue Date *</label>
                        <input type="date" name="issue_date" class="form-control"
                               value="{{ old('issue_date', now()->format('Y-m-d')) }}" required>
                        @error('issue_date')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>Reference Number *</label>
                        <input type="text" name="reference_number" class="form-control"
                               value="{{ old('reference_number') }}" placeholder="e.g. SO-3312" required>
                        @error('reference_number')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full"><label>Notes</label>
                        <textarea name="notes" class="form-control"
                                  placeholder="Optional notes about this issue">{{ old('notes') }}</textarea>
                        @error('notes')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                </div>
            </div>
        </div>

        <div class="card section">
            <div class="card-header">
                <div>
                    <h2>Items</h2>
                    <p>Products included in this issue</p>
                </div>
            </div>
            <div class="card-body">

                @error('products')
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <div class="table-wrap line-items-table">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:45%">Product</th>
                                <th>Available Stock</th>
                                <th>Quantity</th>
                                <th></th>
                            </tr>
                        </thead>
                        {{-- On a validation failure we rebuild the rows the user submitted,
                             using old(). A fresh form starts with one empty row. --}}
                        <tbody id="itemRows">
                            @foreach (old('products', [null]) as $i => $oldProductId)
                                <tr>
                                    <td>
                                        <select name="products[]" class="form-control product-select" required>
                                            <option value="">Select product</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}"
                                                    {{ $oldProductId == $product->id ? 'selected' : '' }}>
                                                    {{ $product->name }} ({{ $product->sku }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('products.' . $i)
                                            <span class="cell-muted">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    {{-- data-product-id lets JS update this cell when warehouse changes --}}
                                    <td class="cell-mono available-stock" data-product-id="{{ $oldProductId }}">—</td>
                                    <td>
                                        <input type="number" name="quantities[]" class="form-control"
                                               value="{{ old('quantities.' . $i) }}"
                                               min="1" step="1" placeholder="0" required>
                                        @error('quantities.' . $i)
                                            <span class="cell-muted">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger-outline btn-sm btn-icon remove-row-btn">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Template for a new row (cloned when "Add Product" is clicked) --}}
                <template id="itemRowTemplate">
                    <tr>
                        <td>
                            <select name="products[]" class="form-control product-select" required>
                                <option value="">Select product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->name }} ({{ $product->sku }})
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="cell-mono available-stock" data-product-id="">—</td>
                        <td><input type="number" name="quantities[]" class="form-control" min="1" step="1" placeholder="0" required></td>
                        <td>
                            <button type="button" class="btn btn-danger-outline btn-sm btn-icon remove-row-btn">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                </template>

                <button type="button" class="add-row-btn" id="addRowBtn">
                    <i class="fa-solid fa-plus"></i> Add Product
                </button>
            </div>

            <div class="card-body" style="padding-top:0;">
                <div class="form-actions">
                    <a href="{{ route('stock-out.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> Save Issue
                    </button>
                </div>
            </div>
        </div>

    </form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows         = document.getElementById('itemRows');
    const template     = document.getElementById('itemRowTemplate');
    const addBtn       = document.getElementById('addRowBtn');
    const warehouseSel = document.getElementById('warehouseSelect');

    // -------------------------------------------------------------------
    // updateAvailableStock()
    //
    // Reads the selected warehouse, then for each item row reads the
    // selected product and looks up the stock from stockData.
    //
    // stockData is the PHP $stocks array JSON-encoded in the page:
    //   stockData[warehouseId][productId] = available units
    //
    // If no warehouse is selected yet, all cells show "—".
    // -------------------------------------------------------------------
    function updateAvailableStock() {
        const warehouseId = warehouseSel.value;

        rows.querySelectorAll('tr').forEach(function (row) {
            const productSel  = row.querySelector('.product-select');
            const stockCell   = row.querySelector('.available-stock');
            if (!productSel || !stockCell) return;

            const productId = productSel.value;

            if (!warehouseId || !productId) {
                stockCell.textContent = '—';
                return;
            }

            const warehouseStock = stockData[warehouseId] || {};
            const available = warehouseStock[productId] !== undefined
                ? warehouseStock[productId]
                : 0;

            stockCell.textContent = available;
            stockCell.dataset.productId = productId;

            // Highlight if stock is zero (warn the user before they submit).
            stockCell.style.color = available <= 0 ? '#ef4444' : '';
        });
    }

    // Re-run when warehouse changes.
    warehouseSel.addEventListener('change', updateAvailableStock);

    // Re-run when a product is picked in any row (delegated listener on tbody).
    rows.addEventListener('change', function (e) {
        if (e.target.matches('.product-select')) {
            // Update just this row's stock cell.
            const row       = e.target.closest('tr');
            const stockCell = row.querySelector('.available-stock');
            const warehouseId = warehouseSel.value;
            const productId   = e.target.value;

            if (!warehouseId || !productId) {
                stockCell.textContent = '—';
                return;
            }
            const available = (stockData[warehouseId] || {})[productId] ?? 0;
            stockCell.textContent = available;
            stockCell.style.color = available <= 0 ? '#ef4444' : '';
        }
    });

    // Add a new row when "Add Product" is clicked.
    addBtn.addEventListener('click', function () {
        const clone = template.content.cloneNode(true);
        rows.appendChild(clone);
        // Immediately update the stock cell for the new row.
        updateAvailableStock();
    });

    // Remove a row (but never remove the last one).
    rows.addEventListener('click', function (e) {
        if (!e.target.closest('.remove-row-btn')) return;
        if (rows.querySelectorAll('tr').length === 1) {
            alert('An issue needs at least one item row.');
            return;
        }
        e.target.closest('tr').remove();
    });

    // On page load: if a warehouse was already selected (old() after failed
    // validation), populate the stock cells immediately.
    updateAvailableStock();
});
</script>
@endpush
