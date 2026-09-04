<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Support\InventoryStockLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseTransferController extends Controller
{
    /**
     * ROUTE: GET /transfers  ->  transfers.index
     *
     * Lists all warehouse transfers, newest first, with optional filters.
     */
    public function index(Request $request)
    {
        $query = WarehouseTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'creator'])
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->latest();

        // Filter by source warehouse
        if ($fromId = $request->input('from_warehouse_id')) {
            $query->where('from_warehouse_id', $fromId);
        }

        // Filter by destination warehouse
        if ($toId = $request->input('to_warehouse_id')) {
            $query->where('to_warehouse_id', $toId);
        }

        // Filter by reference number or notes
        if ($search = $request->input('search')) {
            $query->where('reference_number', 'like', "%{$search}%");
        }

        // Filter by date
        if ($date = $request->input('date')) {
            $query->whereDate('transfer_date', $date);
        }

        // Date range — the filter bar offers from/to; the exact-date filter
        // above is kept so any bookmarked ?date=... URL keeps working.
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('transfer_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('transfer_date', '<=', $dateTo);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $transfers  = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();

        return view('transfers.index', compact('transfers', 'warehouses'));
    }

    /**
     * ROUTE: GET /transfers/create  ->  transfers.create
     *
     * Shows the transfer form. Only active warehouses and products are offered.
     */
    public function create()
    {
        // Only the columns the <option> tags render. Products need sku and
        // barcode as well: the label reads "name (sku)" and the barcode feeds
        // the scanner lookup.
        $warehouses = Warehouse::select('id', 'name')->where('active', true)->orderBy('name')->get();
        $products   = Product::select('id', 'name', 'sku', 'barcode')->where('active', true)->orderBy('name')->get();

        return view('transfers.create', compact('warehouses', 'products'));
    }

    /**
     * ROUTE: POST /transfers  ->  transfers.store
     *
     * Validates, checks stock, then executes the transfer inside a single DB transaction.
     * Two stock movements are written per item: OUT from source, IN to destination.
     */
    public function store(Request $request)
    {
        // ---------------------------------------------------------------
        // 1. VALIDATE
        // ---------------------------------------------------------------
        $validated = $request->validate([
            'from_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('active', true)],
            'to_warehouse_id'   => ['required', Rule::exists('warehouses', 'id')->where('active', true), 'different:from_warehouse_id'],
            'reference_number'  => 'required|string|max:100',
            'transfer_date'     => 'required|date',
            'notes'             => 'nullable|string',
            'products'          => 'required|array|min:1',
            'products.*'        => ['required', Rule::exists('products', 'id')->where('active', true)],
            'quantities'        => 'required|array|min:1',
            'quantities.*'      => 'required|integer|min:1',
        ], [
            'to_warehouse_id.different'  => __('The source and destination warehouses must be different.'),
            'products.required'          => __('Add at least one product to transfer.'),
            'products.*.exists'          => __('One of the selected products does not exist.'),
            'quantities.*.min'           => __('Quantity must be at least 1.'),
        ]);

        if (count($validated['products']) !== count($validated['quantities'])) {
            return back()->withInput()->with('error', __('Some item rows were incomplete.'));
        }

        // ---------------------------------------------------------------
        // 2. STOCK CHECK (before entering the transaction)
        //    For each product, verify the source warehouse has enough stock.
        //    We group by product_id to handle duplicate rows.
        // ---------------------------------------------------------------
        $fromWarehouseId = (int) $validated['from_warehouse_id'];
        $requestedQty    = []; // [product_id => total_qty_requested]

        foreach ($validated['products'] as $index => $productId) {
            $productId = (int) $productId;
            $qty       = (int) $validated['quantities'][$index];
            $requestedQty[$productId] = ($requestedQty[$productId] ?? 0) + $qty;
        }

        foreach ($requestedQty as $productId => $totalQty) {
            $available = StockMovement::currentStock($productId, $fromWarehouseId);
            if ($totalQty > $available) {
                $product = Product::find($productId);
                return back()
                    ->withInput()
                    ->with('error', __('Insufficient stock for ":name". Available: :available. Requested: :requested.', [
                        'name'      => $product->name,
                        'available' => $available,
                        'requested' => $totalQty,
                    ]));
            }
        }

        // ---------------------------------------------------------------
        // 3. WRITE — inside a DB transaction (all or nothing)
        //    For every item row we create:
        //      (a) A WarehouseTransferItem child record.
        //      (b) An OUT movement from the source warehouse.
        //      (c) An IN  movement to the destination warehouse.
        //
        //    The StockMovement ledger is the single source of truth, so
        //    the transfer does NOT touch any separate "stock" counter —
        //    it just writes IN/OUT rows, exactly like StockIn/StockOut do.
        // ---------------------------------------------------------------
        $transfer = DB::transaction(function () use ($validated, $fromWarehouseId, $requestedQty) {
            InventoryStockLock::lock(
                array_keys($requestedQty),
                [$fromWarehouseId, (int) $validated['to_warehouse_id']]
            );

            foreach ($requestedQty as $productId => $totalQty) {
                $available = StockMovement::currentStock($productId, $fromWarehouseId);

                if ($totalQty > $available) {
                    $product = Product::find($productId);

                    return back()
                        ->withInput()
                        ->with('error', __('Insufficient stock for ":name". Available: :available. Requested: :requested.', [
                            'name'      => $product->name,
                            'available' => $available,
                            'requested' => $totalQty,
                        ]));
                }
            }

            $transfer = WarehouseTransfer::create([
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id'   => (int) $validated['to_warehouse_id'],
                'reference_number'  => $validated['reference_number'],
                'transfer_date'     => $validated['transfer_date'],
                'notes'             => $validated['notes'] ?? null,
                'status'            => WarehouseTransfer::STATUS_COMPLETED,
                'created_by'        => Auth::id(),
            ]);

            foreach ($validated['products'] as $index => $productId) {
                $productId = (int) $productId;
                $quantity  = (int) $validated['quantities'][$index];

                // (a) Item row
                $transfer->items()->create([
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                ]);

                // (b) OUT movement — reduces stock in source warehouse
                StockMovement::create([
                    'product_id'     => $productId,
                    'warehouse_id'   => $fromWarehouseId,
                    'type'           => StockMovement::TYPE_OUT,
                    'quantity'       => $quantity,
                    'reference_type' => 'warehouse_transfer',
                    'reference_id'   => $transfer->id,
                ]);

                // (c) IN movement — increases stock in destination warehouse
                StockMovement::create([
                    'product_id'     => $productId,
                    'warehouse_id'   => (int) $validated['to_warehouse_id'],
                    'type'           => StockMovement::TYPE_IN,
                    'quantity'       => $quantity,
                    'reference_type' => 'warehouse_transfer',
                    'reference_id'   => $transfer->id,
                ]);
            }

            return $transfer;
        });

        if ($transfer instanceof \Illuminate\Http\RedirectResponse) {
            return $transfer;
        }

        return redirect()
            ->route('transfers.show', $transfer)
            ->with('success', __('Transfer completed and inventory updated.'));
    }

    /**
     * ROUTE: GET /transfers/{transfer}  ->  transfers.show
     *
     * Shows a single transfer with all its items, warehouses, and creator.
     */
    public function show(WarehouseTransfer $transfer)
    {
        $transfer->load(['fromWarehouse', 'toWarehouse', 'creator', 'items.product']);

        return view('transfers.show', compact('transfer'));
    }
}
