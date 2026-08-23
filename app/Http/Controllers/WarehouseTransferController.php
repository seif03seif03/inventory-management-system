<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseTransferController extends Controller
{
    /**
     * ROUTE: GET /transfers → transfers.index
     */
    public function index(Request $request)
    {
        $query = WarehouseTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse'])
            ->withCount('items')
            ->withSum('items', 'quantity')
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

        if ($date = $request->input('date')) {
            $query->whereDate('transfer_date', $date);
        }

        if ($fromWarehouseId = $request->input('from_warehouse_id')) {
            $query->where('from_warehouse_id', $fromWarehouseId);
        }

        if ($toWarehouseId = $request->input('to_warehouse_id')) {
            $query->where('to_warehouse_id', $toWarehouseId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $transfers = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('transfers.index', compact('transfers', 'warehouses'));
    }

    /**
     * ROUTE: GET /transfers/create → transfers.create
     *
     * Available stock is keyed by source warehouse, same pattern as Stock Out.
     */
    public function create()
    {
        $warehouses = Warehouse::where('active', true)->orderBy('name')->get();
        $products = Product::where('active', true)->orderBy('name')->get();

        $stocks = [];

        foreach (StockMovement::currentStockRows()->get() as $row) {
            $stocks[$row->warehouse_id][$row->product_id] = max(0, (int) $row->current_stock);
        }

        return view('transfers.create', compact('warehouses', 'products', 'stocks'));
    }

    /**
     * ROUTE: POST /transfers → transfers.store
     *
     * A completed transfer writes:
     *   source warehouse      → OUT movement
     *   destination warehouse → IN movement
     *
     * Both rows use the existing IN/OUT types so currentStock() does not change.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('active', true)],
            'to_warehouse_id' => [
                'required',
                'different:from_warehouse_id',
                Rule::exists('warehouses', 'id')->where('active', true),
            ],
            'reference_number' => 'required|string|max:100',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*' => ['required', Rule::exists('products', 'id')->where('active', true)],
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|integer|min:1',
        ], [
            'from_warehouse_id.required' => 'Choose a source warehouse.',
            'to_warehouse_id.required' => 'Choose a destination warehouse.',
            'to_warehouse_id.different' => 'Source and destination warehouses must be different.',
            'products.required' => 'Add at least one product to the transfer.',
            'products.*.required' => 'Choose a product on every item row.',
            'products.*.exists' => 'One of the selected products does not exist.',
            'quantities.*.required' => 'Enter a quantity on every item row.',
            'quantities.*.integer' => 'Quantity must be a whole number.',
            'quantities.*.min' => 'Quantity must be at least 1.',
        ]);

        if (count($validated['products']) !== count($validated['quantities'])) {
            return back()
                ->withInput()
                ->with('error', 'Some item rows were incomplete. Please check every row.');
        }

        $fromWarehouseId = (int) $validated['from_warehouse_id'];
        $toWarehouseId = (int) $validated['to_warehouse_id'];

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
            $available = StockMovement::currentStock($productId, $fromWarehouseId);

            if ($requested > $available) {
                $name = $productNames[$productId] ?? "Product #{$productId}";

                $stockErrors[] = "Insufficient stock for \"{$name}\" in the source warehouse. "
                    . "Available: {$available}. Requested: {$requested}.";
            }
        }

        if (! empty($stockErrors)) {
            return back()
                ->withInput()
                ->with('stockErrors', $stockErrors);
        }

        $transfer = DB::transaction(function () use ($validated, $fromWarehouseId, $toWarehouseId) {
            $transfer = WarehouseTransfer::create([
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'reference_number' => $validated['reference_number'],
                'transfer_date' => $validated['transfer_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => WarehouseTransfer::STATUS_COMPLETED,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['products'] as $index => $productId) {
                $quantity = (int) $validated['quantities'][$index];

                $transfer->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);

                StockMovement::create([
                    'product_id' => $productId,
                    'warehouse_id' => $fromWarehouseId,
                    'type' => StockMovement::TYPE_OUT,
                    'quantity' => $quantity,
                    'reference_type' => StockMovement::REFERENCE_TRANSFER,
                    'reference_id' => $transfer->id,
                ]);

                StockMovement::create([
                    'product_id' => $productId,
                    'warehouse_id' => $toWarehouseId,
                    'type' => StockMovement::TYPE_IN,
                    'quantity' => $quantity,
                    'reference_type' => StockMovement::REFERENCE_TRANSFER,
                    'reference_id' => $transfer->id,
                ]);
            }

            return $transfer;
        });

        return redirect()
            ->route('transfers.show', $transfer)
            ->with('success', 'Warehouse transfer saved and inventory updated.');
    }

    /**
     * ROUTE: GET /transfers/{warehouseTransfer} → transfers.show
     */
    public function show(WarehouseTransfer $warehouseTransfer)
    {
        $warehouseTransfer->load(['fromWarehouse', 'toWarehouse', 'creator', 'items.product']);

        return view('transfers.show', compact('warehouseTransfer'));
    }
}
