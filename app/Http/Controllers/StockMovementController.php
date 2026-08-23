<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Warehouse;

class StockMovementController extends Controller
{
    /**
     * ROUTE:  GET /stock-movements   →   stock-movements.index
     *
     * Shows the full movement ledger with optional filters.
     * Filters are passed as query string parameters, for example:
     *
     *   /stock-movements?product_id=1&type=IN&warehouse_id=2
     *
     * The view reads request() helpers to restore filter values.
     */
    public function index(Request $request)
    {
        $query = StockMovement::query()
            // Eager-load product + warehouse so Blade doesn't run N queries.
            ->with(['product', 'warehouse'])
            // Newest movements first.
            ->latest();

        // --- Filters (all optional) ---

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($type = $request->input('type')) {
            // type is 'IN' or 'OUT' — stored in uppercase in the DB.
            $query->where('type', strtoupper($type));
        }

        if ($dateFrom = $request->input('date_from')) {
            // whereDate() compares only the date part of created_at.
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Paginate so the page doesn't time out if there are thousands of rows.
        // paginate() automatically reads ?page=N from the URL.
        $movements = $query->paginate(50)->withQueryString();
        // withQueryString() preserves filter parameters in the pagination links,
        // so clicking "Page 2" keeps ?product_id=1 in the URL.

        // Populate the filter dropdowns.
        $products   = Product::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock-movements.index', compact('movements', 'products', 'warehouses'));
    }
}
