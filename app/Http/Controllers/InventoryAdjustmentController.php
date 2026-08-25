<?php

namespace App\Http\Controllers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryAdjustmentController extends Controller
{
    /**
     * ROUTE: GET /adjustments -> adjustments.index
     */
    public function index(Request $request)
    {
        $query = InventoryAdjustment::query()
            ->with(['warehouse', 'creator'])
            ->withCount('items')
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('items.product', function ($productQuery) use ($search) {
                      $productQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($reason = $request->input('reason')) {
            $query->where('reason', $reason);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('adjustment_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('adjustment_date', '<=', $dateTo);
        }

        $adjustments = $query->paginate(20)->withQueryString();
        $warehouses  = Warehouse::orderBy('name')->get();

        return view('adjustments.index', compact('adjustments', 'warehouses'));
    }

    /**
     * ROUTE: GET /adjustments/create -> adjustments.create
     *
     * Only active warehouses and products are offered. The per-warehouse stock
     * map is embedded so the form can show what is currently on hand — the same
     * no-AJAX approach the Stock Out form uses.
     */
    public function create()
    {
        $warehouses = Warehouse::where('active', true)->orderBy('name')->get();
        $products   = Product::where('active', true)->orderBy('name')->get();

        $stocks = []; // $stocks[$warehouseId][$productId] = currentStock

        foreach (StockMovement::currentStockRows()->get() as $row) {
            $stocks[$row->warehouse_id][$row->product_id] = max(0, (int) $row->current_stock);
        }

        return view('adjustments.create', compact('warehouses', 'products', 'stocks'));
    }

    /**
     * ROUTE: POST /adjustments -> adjustments.store
     *
     * Validates, checks that no decrease drives stock negative, then writes the
     * document, its lines and the ledger movements in ONE transaction.
     */
    public function store(Request $request)
    {
        // ---------------------------------------------------------------
        // 1. VALIDATE
        // ---------------------------------------------------------------
        $validated = $request->validate([
            'warehouse_id'     => ['required', Rule::exists('warehouses', 'id')->where('active', true)],
            'reference_number' => 'required|string|max:100',
            'adjustment_date'  => 'required|date',
            'reason'           => ['required', Rule::in(InventoryAdjustment::REASONS)],
            'notes'            => 'nullable|string',

            'products'         => 'required|array|min:1',
            'products.*'       => ['required', Rule::exists('products', 'id')->where('active', true)],
            'directions'       => 'required|array|min:1',
            'directions.*'     => ['required', Rule::in(InventoryAdjustmentItem::DIRECTIONS)],
            'quantities'       => 'required|array|min:1',
            'quantities.*'     => 'required|integer|min:1',
        ], [
            'reason.required'        => 'Choose why the stock is being adjusted.',
            'reason.in'              => 'That is not a recognised adjustment reason.',
            'products.required'      => 'Add at least one product to adjust.',
            'products.*.required'    => 'Choose a product on every item row.',
            'products.*.exists'      => 'One of the selected products does not exist or is inactive.',
            'directions.*.in'        => 'Each row must either increase or decrease stock.',
            'quantities.*.required'  => 'Enter a quantity on every item row.',
            'quantities.*.integer'   => 'Quantity must be a whole number.',
            'quantities.*.min'       => 'Quantity must be at least 1.',
        ]);

        // The three arrays are read position-by-position, so a hand-crafted
        // request with mismatched lengths must be rejected rather than
        // silently pairing the wrong values.
        if (count($validated['products']) !== count($validated['quantities'])
            || count($validated['products']) !== count($validated['directions'])) {
            return back()
                ->withInput()
                ->with('error', 'Some item rows were incomplete. Please check every row and try again.');
        }

        $warehouseId = (int) $validated['warehouse_id'];

        // ---------------------------------------------------------------
        // 2. STOCK CHECK — decreases only
        //
        // An increase can always be applied. A decrease cannot take stock
        // below zero: negative stock is not a real state, and allowing it
        // would corrupt every report that reads the ledger.
        //
        // Net per product, so an increase and a decrease of the same product
        // on one document are judged on their combined effect rather than
        // rejecting a decrease that a same-document increase pays for.
        // ---------------------------------------------------------------
        $netByProduct = [];

        foreach ($validated['products'] as $index => $productId) {
            $productId = (int) $productId;
            $quantity  = (int) $validated['quantities'][$index];

            $signed = $validated['directions'][$index] === InventoryAdjustmentItem::DIRECTION_INCREASE
                ? $quantity
                : -$quantity;

            $netByProduct[$productId] = ($netByProduct[$productId] ?? 0) + $signed;
        }

        $productNames = Product::whereIn('id', array_keys($netByProduct))->pluck('name', 'id');
        $stockErrors  = [];

        foreach ($netByProduct as $productId => $net) {
            if ($net >= 0) {
                continue;
            }

            $available = StockMovement::currentStock($productId, $warehouseId);

            if (abs($net) > $available) {
                $name = $productNames[$productId] ?? "Product #{$productId}";

                $stockErrors[] = "Cannot reduce \"{$name}\" by " . abs($net)
                               . " — only {$available} in stock at this warehouse.";
            }
        }

        if (! empty($stockErrors)) {
            // All failures at once, so the user fixes the document in one pass.
            return back()->withInput()->with('stockErrors', $stockErrors);
        }

        // ---------------------------------------------------------------
        // 3. WRITE — inside a transaction
        //
        // Three tables. If any write fails, the whole document rolls back:
        // an adjustment whose lines never reached the ledger would silently
        // fail to correct the stock it claims to have corrected.
        // ---------------------------------------------------------------
        $adjustment = DB::transaction(function () use ($validated, $warehouseId) {
            $adjustment = InventoryAdjustment::create([
                'warehouse_id'     => $warehouseId,
                'reference_number' => $validated['reference_number'],
                'adjustment_date'  => $validated['adjustment_date'],
                'reason'           => $validated['reason'],
                'notes'            => $validated['notes'] ?? null,
                'status'           => InventoryAdjustment::STATUS_COMPLETED,
                'created_by'       => Auth::id(),
            ]);

            foreach ($validated['products'] as $index => $productId) {
                $productId = (int) $productId;
                $quantity  = (int) $validated['quantities'][$index];
                $direction = $validated['directions'][$index];

                $item = $adjustment->items()->create([
                    'product_id' => $productId,
                    'direction'  => $direction,
                    'quantity'   => $quantity,
                ]);

                // The ledger entry that actually moves the stock. Quantity stays
                // positive; the type carries the direction — same rule as every
                // other document in the system.
                StockMovement::create([
                    'product_id'     => $productId,
                    'warehouse_id'   => $warehouseId,
                    'type'           => $item->movementType(),
                    'quantity'       => $quantity,
                    'reference_type' => StockMovement::REFERENCE_ADJUSTMENT,
                    'reference_id'   => $adjustment->id,
                ]);
            }

            return $adjustment;
        });

        return redirect()
            ->route('adjustments.show', $adjustment)
            ->with('success', 'Stock adjustment saved and inventory updated.');
    }

    /**
     * ROUTE: GET /adjustments/{adjustment} -> adjustments.show
     */
    public function show(InventoryAdjustment $adjustment)
    {
        $adjustment->load(['warehouse', 'creator', 'items.product']);

        // The ledger rows this document produced, so the page can prove what it
        // actually did to stock rather than just restating its own lines.
        $movements = StockMovement::where('reference_type', StockMovement::REFERENCE_ADJUSTMENT)
            ->where('reference_id', $adjustment->id)
            ->with('product')
            ->get();

        return view('adjustments.show', compact('adjustment', 'movements'));
    }

    /*
    |--------------------------------------------------------------------------
    | No edit() / update() / destroy()
    |--------------------------------------------------------------------------
    | A saved adjustment has already written to the ledger. Editing or deleting
    | it would change stock history and leave the ledger unable to explain
    | current stock — the same rule Stock In, Stock Out and Transfers follow.
    | The correction for a bad adjustment is another adjustment.
    */
}
