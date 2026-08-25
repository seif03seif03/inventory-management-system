<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Models\StockOut;
use App\Models\StockMovement;
use App\Models\Distributor;
use App\Models\Warehouse;
use App\Models\Product;

class StockOutController extends Controller
{
    /**
     * ROUTE:  GET /stock-out   →   stock-out.index
     *
     * Lists every stock issue, newest first.
     * Same pattern as StockInController::index().
     */
    public function index(Request $request)
    {
        $query = StockOut::query()
            // Eager-load distributor + warehouse so Blade can call
            // $stockOut->distributor->name without hitting the DB N times.
            ->with(['distributor', 'warehouse'])
            // withCount('items')          → $stockOut->items_count
            // withSum('items','quantity') → $stockOut->items_sum_quantity
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->latest();

        // --- Optional filters (all from the URL query string) ---

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('items.product', function ($productQuery) use ($search) {
                      $productQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('sku',  'like', "%{$search}%");
                  });
            });
        }

        // Exact-date filter, kept so any bookmarked ?date=... URL keeps working.
        // The filter bar now offers a from/to range instead.
        if ($date = $request->input('date')) {
            $query->whereDate('issue_date', $date);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        if ($distributorId = $request->input('distributor_id')) {
            $query->where('distributor_id', $distributorId);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($productId = $request->input('product_id')) {
            $query->whereHas('items', function ($itemQuery) use ($productId) {
                $itemQuery->where('product_id', $productId);
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $stockOuts = $query->paginate(20)->withQueryString();

        // Populate the filter dropdowns from the database.
        $distributors = Distributor::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();
        $products = Product::orderBy('name')->get();

        return view('stock-out.index', compact('stockOuts', 'distributors', 'warehouses', 'products'));
    }

    /**
     * ROUTE:  GET /stock-out/create   →   stock-out.create
     *
     * Shows the blank issue form. All dropdowns come from the database.
     *
     * KEY DESIGN DECISION — showing available stock on the form:
     *
     * The Stock Out form has a "Available Stock" column next to each product row.
     * The available stock depends on BOTH the product AND the warehouse the user picks.
     *
     * We solve this without AJAX:
     *   1. We load every active product.
     *   2. We calculate the current stock for every product in every warehouse
     *      and package it as a PHP array: $stocks[warehouseId][productId] = quantity
     *   3. We JSON-encode that array and embed it in the page as a JS variable.
     *   4. When the user changes the Warehouse dropdown, a small JS function
     *      reads the correct stock numbers from that object and updates the cells.
     *
     * This means zero extra HTTP requests, and the logic stays easy to read.
     */
    public function create()
    {
        $distributors = Distributor::where('active', true)->orderBy('name')->get();
        $warehouses   = Warehouse::where('active', true)->orderBy('name')->get();
        $products     = Product::where('active', true)->orderBy('name')->get();

        // Build the stock lookup table from the same summary query used by
        // reports and dashboard low-stock checks.
        $stocks = []; // $stocks[$warehouseId][$productId] = currentStock

        foreach (StockMovement::currentStockRows()->get() as $row) {
            $stocks[$row->warehouse_id][$row->product_id] = max(0, (int) $row->current_stock);
        }

        return view('stock-out.create', compact('distributors', 'warehouses', 'products', 'stocks'));
    }

    /**
     * ROUTE:  POST /stock-out   →   stock-out.store
     *
     * Validates the form, checks that stock is sufficient for EVERY item,
     * then writes StockOut + StockOutItems + OUT stock movements — all in
     * ONE database transaction.
     *
     * THE STOCK CHECK (the most important part of Stock Out):
     *
     * We must ensure that for every product in the submitted form:
     *
     *     current stock in selected warehouse >= requested quantity
     *
     * If ANY product fails this check, we reject the ENTIRE transaction and
     * show an error. Partial issues are not allowed.
     *
     * This check happens server-side regardless of what the browser shows,
     * because a user could manually submit a request bypassing the form.
     */
    public function store(Request $request)
    {
        // ---------------------------------------------------------------
        // 1. VALIDATE — form fields and item arrays
        // ---------------------------------------------------------------
        $validated = $request->validate([
            'distributor_id'   => ['required', Rule::exists('distributors', 'id')->where('active', true)],
            'warehouse_id'     => ['required', Rule::exists('warehouses', 'id')->where('active', true)],
            'reference_number' => 'required|string|max:100',
            'issue_date'       => 'required|date',
            'notes'            => 'nullable|string',

            'products'         => 'required|array|min:1',
            'products.*'       => ['required', Rule::exists('products', 'id')->where('active', true)],
            'quantities'       => 'required|array|min:1',
            'quantities.*'     => 'required|integer|min:1',
        ], [
            'products.required'     => 'Add at least one product to the issue.',
            'products.*.required'   => 'Choose a product on every item row.',
            'products.*.exists'     => 'One of the selected products does not exist.',
            'quantities.*.required' => 'Enter a quantity on every item row.',
            'quantities.*.integer'  => 'Quantity must be a whole number.',
            'quantities.*.min'      => 'Quantity must be at least 1.',
        ]);

        // Guard against a hand-crafted request where arrays have different lengths.
        if (count($validated['products']) !== count($validated['quantities'])) {
            return back()
                ->withInput()
                ->with('error', 'Some item rows were incomplete. Please check every row.');
        }

        $warehouseId = (int) $validated['warehouse_id'];

        // ---------------------------------------------------------------
        // 2. STOCK CHECK — server-side, before touching the database
        //
        // For each product row, calculate the current stock in the chosen
        // warehouse and compare it against the requested quantity.
        //
        // We collect all errors at once ("iPhone 15" and "Samsung S24" both
        // fail) instead of stopping at the first one — more helpful to the user.
        // ---------------------------------------------------------------
        $requestedByProduct = [];

        foreach ($validated['products'] as $index => $productId) {
            $productId = (int) $productId;
            $requestedByProduct[$productId] = ($requestedByProduct[$productId] ?? 0)
                + (int) $validated['quantities'][$index];
        }

        $productNames = Product::whereIn('id', array_keys($requestedByProduct))
            ->pluck('name', 'id');

        $stockErrors = [];

        foreach ($requestedByProduct as $productId => $requested) {
            $available = StockMovement::currentStock($productId, $warehouseId);

            if ($requested > $available) {
                $name = $productNames[$productId] ?? "Product #{$productId}";

                $stockErrors[] = "Insufficient stock for \"{$name}\". "
                               . "Available: {$available}. Requested: {$requested}.";
            }
        }

        if (!empty($stockErrors)) {
            // Return ALL insufficient-stock errors at once.
            // We flash them as a single 'stockErrors' array to the session
            // so Blade can display them as a list.
            return back()
                ->withInput()
                ->with('stockErrors', $stockErrors);
        }

        // ---------------------------------------------------------------
        // 3. WRITE — inside a database transaction
        //
        // We write to THREE tables: stock_outs, stock_out_items, stock_movements.
        // If anything fails halfway through, DB::transaction() rolls everything
        // back so we never end up with an issue that has no items, or items
        // that never created a movement.
        //
        // All-or-nothing. That is the whole point of a transaction.
        // ---------------------------------------------------------------
        $stockOut = DB::transaction(function () use ($validated, $warehouseId) {

            // (a) Create the parent issue record.
            $stockOut = StockOut::create([
                'distributor_id'   => $validated['distributor_id'],
                'warehouse_id'     => $warehouseId,
                'reference_number' => $validated['reference_number'],
                'issue_date'       => $validated['issue_date'],
                'notes'            => $validated['notes'] ?? null,
                // Phase 3 keeps it simple: submitting an issue completes it
                // immediately, which is what creates the OUT movements.
                'status'           => 'completed',
            ]);

            foreach ($validated['products'] as $index => $productId) {
                $quantity = (int) $validated['quantities'][$index];

                // (b) Create the child item row.
                // Calling create() through the relationship fills in stock_out_id.
                $stockOut->items()->create([
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                ]);

                // (c) Create the ledger entry that REDUCES stock.
                // This OUT row is what makes currentStock() return a smaller number.
                StockMovement::create([
                    'product_id'     => $productId,
                    'warehouse_id'   => $warehouseId,
                    'type'           => StockMovement::TYPE_OUT,
                    'quantity'       => $quantity,
                    'reference_type' => 'stock_out',
                    'reference_id'   => $stockOut->id,
                ]);
            }

            return $stockOut;
        });

        return redirect()
            ->route('stock-out.show', $stockOut)
            ->with('success', 'Stock issue saved and inventory updated.');
    }

    /**
     * ROUTE:  GET /stock-out/{stockOut}   →   stock-out.show
     *
     * Route model binding: Laravel finds the StockOut by id for us.
     * Returns 404 automatically if it does not exist.
     */
    public function show(StockOut $stockOut)
    {
        $stockOut->load(['distributor', 'warehouse', 'items.product']);

        return view('stock-out.show', compact('stockOut'));
    }

    /*
    |--------------------------------------------------------------------------
    | Why there is no edit() / update() / destroy() here
    |--------------------------------------------------------------------------
    | A submitted issue is immediately 'completed', which means OUT movements
    | have already been written to the stock_movements ledger.
    |
    | Editing or deleting it would silently corrupt stock history — the ledger
    | would no longer explain the current stock numbers.
    |
    | Safe correction rule: issues are create-and-read only in Phase 3.
    | A cancellation workflow with reversing movements is the clean extension
    | for a future phase.
    */
}
