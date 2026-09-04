@extends('layouts.app')

@section('title', __('New Stock Receipt'))
@section('subtitle', __('Record products received from a supplier'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('stock-in.index') }}">{{ __('Stock In') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ __('New Receipt') }}</span>
    </div>

    {{-- ONE form wraps BOTH cards (receipt details + items), so the header
         fields and every item row are submitted together in a single request. --}}
    <form action="{{ route('stock-in.store') }}" method="POST">
        @csrf

        <div class="card section" style="margin-top: 12px;">
            <div class="card-header"><h2>{{ __('Receipt Details') }}</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group"><label>{{ __('Supplier *') }}</label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">{{ __('Select supplier') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>{{ __('Warehouse *') }}</label>
                        <select name="warehouse_id" class="form-control" required>
                            <option value="">{{ __('Select warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>{{ __('Receipt Date *') }}</label>
                        <input type="date" name="receipt_date" class="form-control"
                               value="{{ old('receipt_date', now()->format('Y-m-d')) }}" required>
                        @error('receipt_date')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group"><label>{{ __('Reference Number *') }}</label>
                        <input type="text" name="reference_number" class="form-control"
                               value="{{ old('reference_number') }}" placeholder="e.g. PO-4521" required>
                        @error('reference_number')
                            <span class="cell-muted">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full"><label>{{ __('Notes') }}</label>
                        <textarea name="notes" class="form-control" placeholder="{{ __('Optional notes about this receipt') }}">{{ old('notes') }}</textarea>
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
                    <p>{{ __('Products included in this receipt') }}</p>
                </div>
            </div>
            <div class="card-body">

                @error('products')
                    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <span>{{ $message }}</span></div>
                @enderror

                <div class="form-group" style="max-width:320px;margin-bottom:14px;">
                    <label for="barcodeScanInput"><i class="fa-solid fa-barcode"></i> {{ __('Scan Barcode') }}</label>
                    <input type="text" id="barcodeScanInput" class="form-control"
                           placeholder="{{ __('Scan or type a barcode, then press Enter') }}" autocomplete="off">
                    <small id="barcodeScanFeedback" role="status" aria-live="polite"
                           style="display:block;margin-top:6px;font-size:12px;min-height:16px;"></small>
                </div>

                <div class="table-wrap line-items-table">
                    <table class="data-table">
                        <thead>
                            <tr><th style="width:40%">{{ __('Product') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit Cost') }} ({{ App\Support\Money::symbol() }})</th><th>{{ __('Total') }}</th><th></th></tr>
                        </thead>
                        {{-- On a validation failure we rebuild exactly the rows the user
                             submitted, using old(). A fresh form starts with one row. --}}
                        <tbody id="itemRows">
                            @foreach (old('products', [null]) as $i => $oldProductId)
                                <tr>
                                    <td>
                                        <select name="products[]" class="form-control" required>
                                            <option value="">{{ __('Select product') }}</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}" {{ $oldProductId == $product->id ? 'selected' : '' }}>
                                                    {{ $product->name }} ({{ $product->sku }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('products.' . $i)
                                            <span class="cell-muted">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td>
                                        <input type="number" name="quantities[]" class="form-control row-qty"
                                               value="{{ old('quantities.' . $i) }}" min="1" step="1" placeholder="0" required>
                                        @error('quantities.' . $i)
                                            <span class="cell-muted">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td>
                                        <input type="number" name="unit_costs[]" class="form-control row-cost"
                                               value="{{ old('unit_costs.' . $i) }}" min="0" step="0.01" placeholder="0.00" required>
                                        @error('unit_costs.' . $i)
                                            <span class="cell-muted">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td class="cell-mono row-total">@money(0)</td>
                                    <td><button type="button" class="btn btn-danger-outline btn-sm btn-icon remove-row-btn"><i class="fa-regular fa-trash-can"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Blueprint for a new row, cloned by the "Add Product" button. --}}
                <template id="itemRowTemplate">
                    <tr>
                        <td>
                            <select name="products[]" class="form-control" required>
                                <option value="">{{ __('Select product') }}</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}">{{ $product->name }} ({{ $product->sku }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" name="quantities[]" class="form-control row-qty" min="1" step="1" placeholder="0" required></td>
                        <td><input type="number" name="unit_costs[]" class="form-control row-cost" min="0" step="0.01" placeholder="0.00" required></td>
                        <td class="cell-mono row-total">@money(0)</td>
                        <td><button type="button" class="btn btn-danger-outline btn-sm btn-icon remove-row-btn"><i class="fa-regular fa-trash-can"></i></button></td>
                    </tr>
                </template>

                <button type="button" class="add-row-btn" id="addRowBtn"><i class="fa-solid fa-plus"></i> {{ __('Add Product') }}</button>
            </div>

            <div class="card-body" style="padding-top:0;">
                <div class="form-actions">
                    <a href="{{ route('stock-in.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> {{ __('Save Receipt') }}</button>
                </div>
            </div>
        </div>

    </form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows     = document.getElementById('itemRows');
    const template = document.getElementById('itemRowTemplate');
    const addBtn   = document.getElementById('addRowBtn');

    // Mirrors App\Support\Money::format(), so a row total on this form and the
    // one on the saved receipt read identically. 'en-US' is not the UI locale —
    // it is the separator pair PHP's number_format() produces (1,234.56), which
    // is what has to be matched.
    const CURRENCY = @json(App\Support\Money::symbol());
    const money = n => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + CURRENCY;

    function recalcRow(row) {
        const qty  = parseFloat(row.querySelector('.row-qty').value)  || 0;
        const cost = parseFloat(row.querySelector('.row-cost').value) || 0;
        row.querySelector('.row-total').textContent = money(qty * cost);
    }

    addBtn.addEventListener('click', () => rows.appendChild(template.content.cloneNode(true)));

    // Two delegated listeners on the <tbody> handle existing AND future rows,
    // so cloned rows work without attaching anything to them.
    rows.addEventListener('input', e => {
        if (e.target.matches('.row-qty, .row-cost')) recalcRow(e.target.closest('tr'));
    });

    rows.addEventListener('click', e => {
        if (!e.target.closest('.remove-row-btn')) return;
        if (rows.querySelectorAll('tr').length === 1) {
            alert('A receipt needs at least one item row.');
            return;
        }
        e.target.closest('tr').remove();
    });

    // Recalculate on load so restored old() values show their totals.
    rows.querySelectorAll('tr').forEach(recalcRow);

    // -------------------------------------------------------------------
    // Barcode scanning: a barcode scanner behaves like a keyboard, typing
    // the code then Enter. We match it against each option's data-barcode
    // and drop it into the first empty product row (adding one if needed).
    // -------------------------------------------------------------------
    const barcodeInput = document.getElementById('barcodeScanInput');
    const barcodeFeedback = document.getElementById('barcodeScanFeedback');

    // A scan that matches nothing needs to say so in words — a colour change
    // alone leaves the user guessing whether the scanner even fired.
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

            const referenceSelect = rows.querySelector('select[name="products[]"]');
            const match = referenceSelect ? findOptionByBarcode(referenceSelect, code) : null;

            if (!match) {
                barcodeInput.style.borderColor = 'var(--color-danger, #ef4444)';
                setTimeout(() => { barcodeInput.style.borderColor = ''; }, 800);
                setBarcodeFeedback('No active product has the barcode "' + code + '".', true);
                return;
            }

            let targetSelect = Array.from(rows.querySelectorAll('select[name="products[]"]')).find(s => !s.value);
            if (!targetSelect) {
                rows.appendChild(template.content.cloneNode(true));
                const newRow = rows.querySelector('tr:last-child');
                targetSelect = newRow.querySelector('select[name="products[]"]');
            }

            targetSelect.value = match.value;
            const targetRow = targetSelect.closest('tr');
            recalcRow(targetRow);
            setBarcodeFeedback('Added ' + match.textContent.trim() + '.', false);
            targetRow.querySelector('.row-qty')?.focus();
        });
    }
});
</script>
@endpush
