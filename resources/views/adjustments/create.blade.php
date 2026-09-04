@extends('layouts.app')

@section('title', __('New Stock Adjustment'))
@section('subtitle', __('Correct stock that no receipt or issue explains'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('adjustments.index') }}">{{ __('Inventory Adjustments') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
        <span>{{ __('New Adjustment') }}</span>
    </div>

    @if ($warehouses->isEmpty() || $products->isEmpty())
        <div class="alert alert-warning" style="margin-top:12px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>{{ __('You need at least one active warehouse and one active product to adjust stock.') }}</span>
        </div>
    @else

    <form action="{{ route('adjustments.store') }}" method="POST" id="adjustmentForm">
        @csrf

        <div class="card section" style="margin-top:12px;">
            <div class="card-header"><h2>{{ __('Adjustment Details') }}</h2></div>
            <div class="card-body">
                <div class="form-grid">

                    <div class="form-group">
                        <label for="warehouse_id">{{ __('Warehouse') }} <span style="color:var(--color-danger);">*</span></label>
                        <select id="warehouse_id" name="warehouse_id" class="form-control" required>
                            <option value="">{{ __('Select warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="reason">{{ __('Reason') }} <span style="color:var(--color-danger);">*</span></label>
                        <select id="reason" name="reason" class="form-control" required>
                            <option value="">{{ __('Select reason') }}</option>
                            @foreach (App\Models\InventoryAdjustment::REASONS as $reason)
                                <option value="{{ $reason }}" {{ old('reason') === $reason ? 'selected' : '' }}>
                                    {{ __(ucfirst($reason)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('reason')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="reference_number">{{ __('Reference Number') }} <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="reference_number" name="reference_number"
                               value="{{ old('reference_number') }}" class="form-control"
                               placeholder="ADJ-1001" required>
                        @error('reference_number')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="adjustment_date">{{ __('Adjustment Date') }} <span style="color:var(--color-danger);">*</span></label>
                        <input type="date" id="adjustment_date" name="adjustment_date"
                               value="{{ old('adjustment_date', now()->toDateString()) }}" class="form-control" required>
                        @error('adjustment_date')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group full">
                        <label for="notes">{{ __('Notes') }}</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"
                                  placeholder="{{ __('Optional notes about this adjustment') }}">{{ old('notes') }}</textarea>
                        @error('notes')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                </div>
            </div>
        </div>

        <div class="card section">
            <div class="card-header">
                <div>
                    <h2>{{ __('Items') }}</h2>
                    <p>{{ __('Increase or decrease each product, with current stock shown for reference') }}</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" id="addRow">
                    <i class="fa-solid fa-plus"></i> {{ __('Add Row') }}
                </button>
            </div>

            <div class="card-body">
                <div class="form-group" style="max-width:320px;margin-bottom:14px;">
                    <label for="barcodeScanInput"><i class="fa-solid fa-barcode"></i> {{ __('Scan Barcode') }}</label>
                    <input type="text" id="barcodeScanInput" class="form-control"
                           placeholder="{{ __('Scan or type a barcode, then press Enter') }}" autocomplete="off">
                    <small id="barcodeScanFeedback" role="status" aria-live="polite"
                           style="display:block;margin-top:6px;font-size:12px;min-height:16px;"></small>
                </div>

                <div class="table-wrap">
                    <table class="data-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width:36%">{{ __('Product') }}</th>
                                <th>{{ __('Direction') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Current Stock') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td>
                                    <select name="products[]" class="form-control product-select" required>
                                        <option value="">{{ __('Select product') }}</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}">
                                                {{ $product->name }} ({{ $product->sku }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="directions[]" class="form-control" required>
                                        <option value="{{ App\Models\InventoryAdjustmentItem::DIRECTION_DECREASE }}">
                                            &minus; {{ __('Decrease') }}
                                        </option>
                                        <option value="{{ App\Models\InventoryAdjustmentItem::DIRECTION_INCREASE }}">
                                            + {{ __('Increase') }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="quantities[]" class="form-control"
                                           min="1" step="1" placeholder="0" required>
                                </td>
                                <td class="cell-mono stock-cell">&mdash;</td>
                                <td>
                                    <button type="button" class="btn btn-danger-outline btn-sm btn-icon removeRow"
                                            title="{{ __('Remove') }}">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @error('products')<span class="form-error">{{ $message }}</span>@enderror
                @error('quantities')<span class="form-error">{{ $message }}</span>@enderror
                @error('directions')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="card-body" style="border-top:1px solid var(--color-border);display:flex;gap:8px;justify-content:flex-end;">
                <a href="{{ route('adjustments.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-regular fa-floppy-disk"></i> {{ __('Save Adjustment') }}
                </button>
            </div>
        </div>
    </form>

    @endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Current stock per warehouse+product, embedded so the form can show what
    // is on hand without an extra request. Server-side validation is what
    // actually enforces the limit — this is guidance only.
    const stocks = @json($stocks);

    const itemsBody     = document.getElementById('itemsBody');
    const warehouse     = document.getElementById('warehouse_id');
    const templateRow   = itemsBody.querySelector('tr').cloneNode(true);

    function currentStockFor(productId) {
        const warehouseId = warehouse.value;
        if (!warehouseId || !productId) return null;
        const perWarehouse = stocks[warehouseId];
        if (!perWarehouse) return 0;
        return perWarehouse[productId] ?? 0;
    }

    function refreshRow(row) {
        const select = row.querySelector('.product-select');
        const cell   = row.querySelector('.stock-cell');
        if (!select || !cell) return;

        const stock = currentStockFor(select.value);
        cell.textContent = stock === null ? '—' : stock;
    }

    function refreshAll() {
        itemsBody.querySelectorAll('tr').forEach(refreshRow);
    }

    function bindRow(row) {
        row.querySelector('.product-select')?.addEventListener('change', () => refreshRow(row));
        row.querySelector('.removeRow')?.addEventListener('click', () => {
            // Always leave one row, otherwise the form cannot be filled in.
            if (itemsBody.querySelectorAll('tr').length > 1) {
                row.remove();
            }
        });
    }

    itemsBody.querySelectorAll('tr').forEach(bindRow);
    warehouse.addEventListener('change', refreshAll);

    document.getElementById('addRow').addEventListener('click', function () {
        const row = templateRow.cloneNode(true);
        row.querySelectorAll('input').forEach(input => input.value = '');
        row.querySelector('.product-select').value = '';
        itemsBody.appendChild(row);
        bindRow(row);
        refreshRow(row);
    });

    // -------------------------------------------------------------------
    // Barcode scanning: a scanner behaves like a keyboard, typing the code
    // then Enter. Matched against each option's data-barcode.
    // -------------------------------------------------------------------
    const barcodeInput    = document.getElementById('barcodeScanInput');
    const barcodeFeedback = document.getElementById('barcodeScanFeedback');

    function setBarcodeFeedback(message, isError) {
        if (!barcodeFeedback) return;
        barcodeFeedback.textContent = message;
        barcodeFeedback.style.color = isError ? 'var(--color-danger, #ef4444)' : 'var(--color-success, #16a34a)';
    }

    function findOptionByBarcode(select, code) {
        for (const opt of select.options) {
            if (opt.dataset.barcode && opt.dataset.barcode === code) return opt;
        }
        return null;
    }

    if (barcodeInput) {
        barcodeInput.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();

            const code = barcodeInput.value.trim();
            barcodeInput.value = '';
            if (!code) return;

            const reference = itemsBody.querySelector('.product-select');
            const match = reference ? findOptionByBarcode(reference, code) : null;

            if (!match) {
                barcodeInput.style.borderColor = 'var(--color-danger, #ef4444)';
                setTimeout(() => { barcodeInput.style.borderColor = ''; }, 800);
                setBarcodeFeedback('No active product has the barcode "' + code + '".', true);
                return;
            }

            let target = Array.from(itemsBody.querySelectorAll('.product-select')).find(s => !s.value);
            if (!target) {
                const row = templateRow.cloneNode(true);
                row.querySelectorAll('input').forEach(input => input.value = '');
                itemsBody.appendChild(row);
                bindRow(row);
                target = row.querySelector('.product-select');
            }

            target.value = match.value;
            refreshRow(target.closest('tr'));
            setBarcodeFeedback('Added ' + match.textContent.trim() + '.', false);
            target.closest('tr').querySelector('input[name="quantities[]"]')?.focus();
        });
    }
});
</script>
@endpush
