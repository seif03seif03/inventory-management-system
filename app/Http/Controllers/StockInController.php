<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Product;

class StockInController extends Controller
{
    /**
     * ROUTE:  GET /stock-in       ->  stock-in.index
     *
     * Lists every stock receipt, newest first, with its supplier, warehouse,
     * item count and total quantity.
     */
    public function index(Request $request)
    {
        $query = StockIn::query()
            // with() eager-loads the related supplier + warehouse in 2 extra
            // queries instead of 2 queries PER ROW (the classic "N+1 problem").
            ->with(['supplier', 'warehouse'])
            // withCount('items')          -> $stockIn->items_count
            // withSum('items','quantity') -> $stockIn->items_sum_quantity
            // Both are calculated by the database, so we never load the item
            // rows just to count them.
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->latest();

        // --- Filters (all optional, all read from the query string) ---

        if ($search = $request->input('search')) {
            // The closure GROUPS these OR conditions together, producing
            //   AND (reference_number LIKE ... OR product matches ...)
            // Without the closure the OR would escape and cancel out the
            // other filters below.
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('items.product', function ($productQuery) use ($search) {
                      $productQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        if ($date = $request->input('date')) {
            $query->whereDate('receipt_date', $date);
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
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

        $stockIns = $query->get();

        // The supplier/warehouse/product dropdowns in the filter bar are
        // built from the database, never hardcoded.
        $suppliers  = Supplier::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();
        $products   = Product::orderBy('name')->get();

        return view('stock-in.index', compact('stockIns', 'suppliers', 'warehouses', 'products'));
    }

    /**
     * ROUTE:  GET /stock-in/create  ->  stock-in.create
     *
     * Shows the empty receipt form. Every dropdown is filled from the database.
     * Only ACTIVE records are offered, because receiving goods into a disabled
     * warehouse (or from a disabled supplier) is a business mistake.
     */
    public function create()
    {
        $suppliers  = Supplier::where('active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('active', true)->orderBy('name')->get();
        $products   = Product::where('active', true)->orderBy('name')->get();

        return view('stock-in.create', compact('suppliers', 'warehouses', 'products'));
    }

    /**
     * ROUTE:  POST /stock-in       ->  stock-in.store
     *
     * Saves the receipt, its item rows, and the IN stock movements —
     * all inside ONE database transaction.
     */
    public function store(Request $request)
    {
        // ---------------------------------------------------------------
        // 1. VALIDATE
        //    The parent fields, then every item row.
        //    'products.*' means "apply this rule to every element of the
        //    products array", so products[0], products[1], products[2]...
        //    If validation fails Laravel automatically redirects back with
        //    the errors AND the old input — nothing is written to the DB.
        // ---------------------------------------------------------------
        $validated = $request->validate([
            'supplier_id'      => 'required|exists:suppliers,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'reference_number' => 'required|string|max:100',
            'receipt_date'     => 'required|date',
            'notes'            => 'nullable|string',

            'products'      => 'required|array|min:1',
            'products.*'    => 'required|exists:products,id',
            'quantities'    => 'required|array|min:1',
            'quantities.*'  => 'required|integer|min:1',
            'unit_costs'    => 'required|array|min:1',
            'unit_costs.*'  => 'required|numeric|min:0',
        ], [
            // Default messages for array rules read like "The products.0 field
            // is required", so we give friendlier ones.
            'products.required'     => 'Add at least one product to the receipt.',
            'products.*.required'   => 'Choose a product on every item row (or remove the empty row).',
            'products.*.exists'     => 'One of the selected products does not exist.',
            'quantities.*.required' => 'Enter a quantity on every item row.',
            'quantities.*.integer'  => 'Quantity must be a whole number.',
            'quantities.*.min'      => 'Quantity must be at least 1.',
            'unit_costs.*.required' => 'Enter a unit cost on every item row.',
            'unit_costs.*.numeric'  => 'Unit cost must be a number.',
            'unit_costs.*.min'      => 'Unit cost cannot be negative.',
        ]);

        // The three item arrays are read position-by-position (row 0, row 1, ...),
        // so they must be the same length. A normal form submit always sends
        // them aligned; this guard protects against a hand-crafted request.
        if (count($validated['products']) !== count($validated['quantities'])
            || count($validated['products']) !== count($validated['unit_costs'])) {
            return back()
                ->withInput()
                ->with('error', 'Some item rows were incomplete. Please check every row and try again.');
        }

        // ---------------------------------------------------------------
        // 2. WRITE — inside a database transaction
        //
        //    A receipt is meaningless without its items, and its items are
        //    meaningless without their stock movements. This block writes to
        //    THREE tables. If the 3rd write fails halfway through we must not
        //    be left with a receipt that has no items, or items that never
        //    changed the stock ledger.
        //
        //    DB::transaction() gives us "all or nothing":
        //      - closure finishes normally  -> COMMIT (everything is saved)
        //      - closure throws any error   -> ROLLBACK (nothing is saved)
        //
        //    It also returns whatever the closure returns, which is how we get
        //    the new $stockIn back out for the redirect.
        // ---------------------------------------------------------------
        $stockIn = DB::transaction(function () use ($validated) {

            // (a) the parent receipt
            $stockIn = StockIn::create([
                'supplier_id'      => $validated['supplier_id'],
                'warehouse_id'     => $validated['warehouse_id'],
                'reference_number' => $validated['reference_number'],
                'receipt_date'     => $validated['receipt_date'],
                'notes'            => $validated['notes'] ?? null,
                // Phase 3 keeps the workflow simple: a saved receipt is
                // immediately completed, which is what makes it affect stock.
                // The column still defaults to 'pending' so an approval step
                // can be added later without a migration.
                'status'           => 'completed',
            ]);

            foreach ($validated['products'] as $index => $productId) {
                $quantity = (int) $validated['quantities'][$index];
                $unitCost = $validated['unit_costs'][$index];

                // (b) the child item row.
                // Calling create() through the items() relationship fills in
                // stock_in_id for us.
                $stockIn->items()->create([
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                    'unit_cost'  => $unitCost,
                ]);

                // (c) the ledger entry that actually moves the stock.
                // The receipt says "we received 100"; THIS row is what makes
                // current stock go up by 100 in this warehouse.
                StockMovement::create([
                    'product_id'     => $productId,
                    'warehouse_id'   => $stockIn->warehouse_id,
                    'type'           => StockMovement::TYPE_IN,
                    'quantity'       => $quantity,
                    'reference_type' => 'stock_in',
                    'reference_id'   => $stockIn->id,
                ]);
            }

            return $stockIn;
        });

        return redirect()
            ->route('stock-in.show', $stockIn)
            ->with('success', 'Stock receipt saved and inventory updated.');
    }

    /**
     * ROUTE:  GET /stock-in/{stockIn}  ->  stock-in.show
     *
     * Because the route parameter is named {stockIn} and this method type-hints
     * StockIn, Laravel looks the record up by id for us and returns 404 if it
     * does not exist. This is called "route model binding" — the same trick
     * ProductController uses.
     */
    public function show(StockIn $stockIn)
    {
        // items.product = load the items, and each item's product, in one go.
        $stockIn->load(['supplier', 'warehouse', 'items.product']);

        return view('stock-in.show', compact('stockIn'));
    }

    /*
    |--------------------------------------------------------------------------
    | Why there is no edit() / update() / destroy() here
    |--------------------------------------------------------------------------
    | A saved receipt is immediately 'completed', which means it has already
    | written IN rows into the stock_movements ledger.
    |
    | Editing or deleting it would silently change stock history — the ledger
    | would no longer explain the current stock. Real inventory systems never
    | edit a posted document; they post a correcting one.
    |
    | So Phase 3 uses the simplest safe rule: receipts are create-and-read only.
    | (A 'cancelled' status and a reversing movement is the clean way to add
    | corrections later.)
    */
}
