<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Distributor;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * ROUTE:  GET /   and   GET /dashboard   →   dashboard
     *
     * Replaces the two static Route::view() calls that were showing hardcoded
     * demo numbers. Everything passed to the view now comes from the database.
     */
    public function index()
    {
        // ---------------------------------------------------------------
        // PRIMARY STATS
        // ---------------------------------------------------------------

        $totalProducts   = Product::where('active', true)->count();
        $totalSuppliers  = Supplier::count();
        $totalDistributors = Distributor::count();

        // Total stock = SUM(all IN movements) - SUM(all OUT movements)
        // across every product and every warehouse.
        $totalIn  = StockMovement::where('type', StockMovement::TYPE_IN)->sum('quantity');
        $totalOut = StockMovement::where('type', StockMovement::TYPE_OUT)->sum('quantity');
        $totalStock = (int)$totalIn - (int)$totalOut;

        // ---------------------------------------------------------------
        // SECONDARY STATS
        // ---------------------------------------------------------------

        // Stock In today = sum of IN movements created today.
        // whereDate() matches any time on today's date.
        $stockInToday = StockMovement::where('type', StockMovement::TYPE_IN)
            ->whereDate('created_at', today())
            ->sum('quantity');

        $stockOutToday = StockMovement::where('type', StockMovement::TYPE_OUT)
            ->whereDate('created_at', today())
            ->sum('quantity');

        // Low stock is checked per Product + Warehouse. This reuses the same
        // IN-minus-OUT summary query from the StockMovement model.
        //
        // product_active filters to products still in use: a retired product
        // sitting below its threshold is not something to alert on. The summary
        // query itself no longer hides inactive rows, so callers say what they
        // mean — see StockMovement::currentStockRows().
        $stockRows = StockMovement::currentStockRows();

        $lowStockQuery = DB::query()
            ->fromSub($stockRows, 'stock_rows')
            ->where('product_active', true)
            ->where('minimum_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'minimum_stock');

        $lowStockCount = (clone $lowStockQuery)->count();

        $lowStockProducts = $lowStockQuery
            ->orderBy('product_name')
            ->orderBy('warehouse_name')
            ->limit(10)
            ->get();

        // ---------------------------------------------------------------
        // RECENT MOVEMENTS (last 10) for the dashboard activity table.
        // ---------------------------------------------------------------
        $recentMovements = StockMovement::with(['product', 'warehouse'])
            ->latest()
            ->limit(10)
            ->get();

        // ---------------------------------------------------------------
        // PASS TO VIEW
        // ---------------------------------------------------------------
        return view('dashboard', compact(
            'totalProducts',
            'totalSuppliers',
            'totalDistributors',
            'totalStock',
            'stockInToday',
            'stockOutToday',
            'lowStockCount',
            'lowStockProducts',
            'recentMovements'
        ));
    }
}
