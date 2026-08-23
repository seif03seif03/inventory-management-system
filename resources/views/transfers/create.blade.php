@extends('layouts.app')

@section('title', __('New Transfer'))
@section('subtitle', __('Move products from one warehouse to another'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('transfers.index') }}">{{ __('Transfers') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>{{ __('New Transfer') }}</span>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if (session('stockErrors'))
        <div class="alert alert-danger" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <strong>{{ __('Could not save transfer — insufficient stock:') }}</strong>
                <ul style="margin: 6px 0 0 18px;">
                    @foreach (session('stockErrors') as $stockError)
                        <li>{{ $stockError }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($warehouses->count() < 2)
        <div class="alert alert-danger" style="margin-top: 12px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ __('You need at least two active warehouses to create a transfer.') }}</span>
        </div>
    @endif

    <script>
        const stockData = @json($stocks);
    </script>

    <form action="{{ route('transfers.store') }}" method="POST">
        @csrf

        <div class="card section" style="margin-top: 12px;">
            <div class="card-header"><h2>{{ __('Transfer Details') }}</h2></div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="form-group"><label>{{ __('From Warehouse') }} *</label>
                        <select name="from_warehouse_id" id="fromWarehouseSelect" class="form-control" required>
                            <option value="">{{ __('Select warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}"
                                    {{ old('from_warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('from_warehouse_id')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>{{ __('To Warehouse') }} *</label>
                        <select name="to_warehouse_id" id="toWarehouseSelect" class="form-control" required>
                            <option value="">{{ __('Select warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}"
                                    {{ old('to_warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('to_warehouse_id')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>{{ __('Transfer Date') }} *</label>
                        <input type="date" name="transfer_date" class="form-control"
                               value="{{ old('transfer_date', now()->format('Y-m-d')) }}" required>
                        @error('transfer_date')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>{{ __('Reference Number') }} *</label>
                        <input type="text" name="reference_number" class="form-control"
                               value="{{ old('reference_number') }}" placeholder="e.g. TR-1001" required>
                        @error('reference_number')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full"><label>{{ __('Notes') }}</label>
                        <textarea name="notes" class="form-control"
                                  placeholder="{{ __('Optional notes about this transfer') }}">{{ old('notes') }}</textarea>
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
                    <h2>{{ __('Items') }}</h2>
                    <p>{{ __('Products to move from the source warehouse') }}</p>
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
                                <th style="width:45%">{{ __('Product') }}</th>
                                <th>{{ __('Available Stock') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemRows">
                            @foreach (old('products', [null]) as $i => $oldProductId)
                                <tr>
                                    <td>
                                        <select name="products[]" class="form-control product-select" required>
                                            <option value="">{{ __('Select product') }}</option>
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

                <template id="itemRowTemplate">
                    <tr>
                        <td>
                            <select name="products[]" class="form-control product-select" required>
                                <option value="">{{ __('Select product') }}</option>
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
                    <i class="fa-solid fa-plus"></i> {{ __('Add Product') }}
                </button>
            </div>

            <div class="card-body" style="padding-top:0;">
                <div class="form-actions">
                    <a href="{{ route('transfers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary" {{ $warehouses->count() < 2 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-check"></i> {{ __('Save Transfer') }}
                    </button>
                </div>
            </div>
        </div>

    </form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('itemRows');
    const template = document.getElementById('itemRowTemplate');
    const addBtn = document.getElementById('addRowBtn');
    const fromWarehouseSel = document.getElementById('fromWarehouseSelect');
    const toWarehouseSel = document.getElementById('toWarehouseSelect');

    function updateAvailableStock() {
        const warehouseId = fromWarehouseSel.value;

        rows.querySelectorAll('tr').forEach(function (row) {
            const productSel = row.querySelector('.product-select');
            const stockCell = row.querySelector('.available-stock');
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
            stockCell.style.color = available <= 0 ? '#ef4444' : '';
        });
    }

    fromWarehouseSel.addEventListener('change', updateAvailableStock);

    rows.addEventListener('change', function (e) {
        if (e.target.matches('.product-select')) {
            updateAvailableStock();
        }
    });

    toWarehouseSel.addEventListener('change', function () {
        const options = fromWarehouseSel.querySelectorAll('option');
        options.forEach(function (option) {
            option.disabled = option.value !== '' && option.value === toWarehouseSel.value;
        });
    });

    fromWarehouseSel.addEventListener('change', function () {
        const options = toWarehouseSel.querySelectorAll('option');
        options.forEach(function (option) {
            option.disabled = option.value !== '' && option.value === fromWarehouseSel.value;
        });
    });

    addBtn.addEventListener('click', function () {
        rows.appendChild(template.content.cloneNode(true));
        updateAvailableStock();
    });

    rows.addEventListener('click', function (e) {
        if (!e.target.closest('.remove-row-btn')) return;
        if (rows.querySelectorAll('tr').length === 1) {
            alert(@json(__('A transfer needs at least one item row.')));
            return;
        }
        e.target.closest('tr').remove();
    });

    fromWarehouseSel.dispatchEvent(new Event('change'));
    toWarehouseSel.dispatchEvent(new Event('change'));
    updateAvailableStock();
});
</script>
@endpush
