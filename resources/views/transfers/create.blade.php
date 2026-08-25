@extends('layouts.app')

@section('title', __('New Warehouse Transfer'))
@section('subtitle', __('Move products from one warehouse to another'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Transfer Details') }}</h2>
                <p>{{ __('Select warehouses, then add products and quantities') }}</p>
            </div>
            <a href="{{ route('transfers.index') }}" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Transfers') }}
            </a>
        </div>

        <div class="card-body">

            @if (session('error'))
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ __('Please fix the errors below before submitting.') }}</div>
                </div>
            @endif

            <form action="{{ route('transfers.store') }}" method="POST" id="transferForm">
                @csrf

                {{-- ── Transfer header ─────────────────────────────────── --}}
                <div class="form-grid">

                    <div class="form-group">
                        <label for="from_warehouse_id">
                            {{ __('From Warehouse') }} <span style="color:var(--color-danger);">*</span>
                        </label>
                        <select id="from_warehouse_id" name="from_warehouse_id" class="form-control" required>
                            <option value="">{{ __('Select source...') }}</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('from_warehouse_id') == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('from_warehouse_id')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="to_warehouse_id">
                            {{ __('To Warehouse') }} <span style="color:var(--color-danger);">*</span>
                        </label>
                        <select id="to_warehouse_id" name="to_warehouse_id" class="form-control" required>
                            <option value="">{{ __('Select destination...') }}</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ old('to_warehouse_id') == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('to_warehouse_id')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="reference_number">
                            {{ __('Reference Number') }} <span style="color:var(--color-danger);">*</span>
                        </label>
                        <input type="text" id="reference_number" name="reference_number"
                               value="{{ old('reference_number', 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4))) }}"
                               class="form-control" required>
                        @error('reference_number')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="transfer_date">
                            {{ __('Transfer Date') }} <span style="color:var(--color-danger);">*</span>
                        </label>
                        <input type="date" id="transfer_date" name="transfer_date"
                               value="{{ old('transfer_date', date('Y-m-d')) }}"
                               class="form-control" required>
                        @error('transfer_date')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label for="notes">{{ __('Notes') }}</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"
                                  placeholder="{{ __('Optional notes about this transfer...') }}">{{ old('notes') }}</textarea>
                    </div>

                </div>

                {{-- ── Item rows ─────────────────────────────────────────── --}}
                <div style="border-top:1px solid var(--color-border); padding-top:20px; margin-top:8px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <h3 style="font-size:14px;font-weight:700;margin:0;">{{ __('Products to Transfer') }}</h3>
                        <button type="button" id="addRow" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-plus"></i> {{ __('Add Product') }}
                        </button>
                    </div>

                    <div class="form-group" style="max-width:320px;margin-bottom:14px;">
                        <label for="barcodeScanInput"><i class="fa-solid fa-barcode"></i> {{ __('Scan Barcode') }}</label>
                        <input type="text" id="barcodeScanInput" class="form-control"
                               placeholder="{{ __('Scan or type a barcode, then press Enter') }}" autocomplete="off">
                        <small id="barcodeScanFeedback" role="status" aria-live="polite"
                               style="display:block;margin-top:6px;font-size:12px;min-height:16px;"></small>
                    </div>

                    <div class="table-wrap" style="border-radius:var(--radius-md);overflow:visible;">
                        <table class="data-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width:60%">{{ __('Product') }}</th>
                                    <th style="width:30%">{{ __('Quantity') }}</th>
                                    <th style="width:10%"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                {{-- Pre-fill old input on validation failure --}}
                                @if (old('products'))
                                    @foreach (old('products') as $i => $pid)
                                        <tr class="item-row">
                                            <td>
                                                <select name="products[]" class="form-control" required>
                                                    <option value="">{{ __('Select product...') }}</option>
                                                    @foreach ($products as $p)
                                                        <option value="{{ $p->id }}" data-barcode="{{ $p->barcode }}" {{ $pid == $p->id ? 'selected' : '' }}>
                                                            {{ $p->name }} ({{ $p->sku }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="quantities[]"
                                                       value="{{ old('quantities')[$i] ?? 1 }}"
                                                       class="form-control" min="1" required>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger-outline btn-sm btn-icon remove-row">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    {{-- Default first empty row --}}
                                    <tr class="item-row">
                                        <td>
                                            <select name="products[]" class="form-control" required>
                                                <option value="">{{ __('Select product...') }}</option>
                                                @foreach ($products as $p)
                                                    <option value="{{ $p->id }}" data-barcode="{{ $p->barcode }}">{{ $p->name }} ({{ $p->sku }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="quantities[]" value="1"
                                                   class="form-control" min="1" required>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger-outline btn-sm btn-icon remove-row">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                </div>

                <div class="form-actions">
                    <a href="{{ route('transfers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-right-left"></i> {{ __('Complete Transfer') }}
                    </button>
                </div>

            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    // Snapshot the first row's HTML to use as template for new rows
    const itemsBody = document.getElementById('itemsBody');
    const rowTemplate = itemsBody.querySelector('.item-row').cloneNode(true);

    // Reset the template's values
    rowTemplate.querySelector('select').value = '';
    rowTemplate.querySelector('input[type="number"]').value = 1;

    // Add a new product row
    document.getElementById('addRow').addEventListener('click', () => {
        const newRow = rowTemplate.cloneNode(true);
        itemsBody.appendChild(newRow);
        bindRemoveButton(newRow);
    });

    // Remove a product row (must always keep at least 1 row)
    function bindRemoveButton(row) {
        row.querySelector('.remove-row').addEventListener('click', () => {
            if (itemsBody.querySelectorAll('.item-row').length > 1) {
                row.remove();
            }
        });
    }

    // Bind remove buttons on existing rows
    itemsBody.querySelectorAll('.item-row').forEach(bindRemoveButton);

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

            const referenceSelect = itemsBody.querySelector('select[name="products[]"]');
            const match = referenceSelect ? findOptionByBarcode(referenceSelect, code) : null;

            if (!match) {
                barcodeInput.style.borderColor = 'var(--color-danger, #ef4444)';
                setTimeout(() => { barcodeInput.style.borderColor = ''; }, 800);
                setBarcodeFeedback('No active product has the barcode "' + code + '".', true);
                return;
            }

            let targetSelect = Array.from(itemsBody.querySelectorAll('select[name="products[]"]')).find(s => !s.value);
            if (!targetSelect) {
                const newRow = rowTemplate.cloneNode(true);
                itemsBody.appendChild(newRow);
                bindRemoveButton(newRow);
                targetSelect = newRow.querySelector('select[name="products[]"]');
            }

            targetSelect.value = match.value;
            setBarcodeFeedback('Added ' + match.textContent.trim() + '.', false);
            targetSelect.closest('tr').querySelector('input[name="quantities[]"]')?.focus();
        });
    }
</script>
@endpush
