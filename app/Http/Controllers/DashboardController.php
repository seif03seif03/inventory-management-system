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

        $chartStart = today()->subDays(29);
        $chartEnd = today();

        $movementTotals = StockMovement::query()
            ->selectRaw('DATE(created_at) as movement_date, type, reference_id, SUM(quantity) as total_quantity')
            ->whereDate('created_at', '>=', $chartStart)
            ->whereDate('created_at', '<=', $chartEnd)
            ->whereIn('type', [StockMovement::TYPE_IN, StockMovement::TYPE_OUT])
            ->whereIn('reference_type', [StockMovement::REFERENCE_STOCK_IN, StockMovement::REFERENCE_STOCK_OUT])
            ->whereNotNull('reference_id')
            ->groupBy('movement_date', 'type', 'reference_id')
            ->get();

        $movementTotalsByDateAndType = $movementTotals->groupBy(fn ($row) => $row->movement_date . ':' . $row->type);

        $chartLabels = [];
        $stockInSeries = [];
        $stockOutSeries = [];
        $stockInTransactions = [];
        $stockOutTransactions = [];

        for ($date = $chartStart->copy(); $date->lte($chartEnd); $date->addDay()) {
            $dateKey = $date->toDateString();
            $stockInRows = $movementTotalsByDateAndType->get($dateKey . ':' . StockMovement::TYPE_IN, collect());
            $stockOutRows = $movementTotalsByDateAndType->get($dateKey . ':' . StockMovement::TYPE_OUT, collect());

            $chartLabels[] = $date->format('M j');
            $stockInSeries[] = (int) $stockInRows->sum('total_quantity');
            $stockOutSeries[] = (int) $stockOutRows->sum('total_quantity');
            $stockInTransactions[] = $this->chartTransactions($stockInRows, StockMovement::TYPE_IN);
            $stockOutTransactions[] = $this->chartTransactions($stockOutRows, StockMovement::TYPE_OUT);
        }

        $stockInLast30 = array_sum($stockInSeries);
        $stockOutLast30 = array_sum($stockOutSeries);

        $stockOverviewChart = [
            'labels' => $chartLabels,
            'stockIn' => $stockInSeries,
            'stockOut' => $stockOutSeries,
            'transactions' => [
                StockMovement::TYPE_IN => $stockInTransactions,
                StockMovement::TYPE_OUT => $stockOutTransactions,
            ],
        ];

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
            'stockInLast30',
            'stockOutLast30',
            'stockOverviewChart',
            'lowStockCount',
            'lowStockProducts',
            'recentMovements'
        ));
    }

    private function chartTransactions($rows, string $type): array
    {
        return $rows
            ->sortBy('reference_id')
            ->map(function ($row) use ($type) {
                $routeName = $type === StockMovement::TYPE_IN ? 'stock-in.show' : 'stock-out.show';
                $label = $type === StockMovement::TYPE_IN ? 'Stock In' : 'Stock Out';

                return [
                    'label' => $label . ' #' . $row->reference_id,
                    'quantity' => (int) $row->total_quantity,
                    'url' => route($routeName, $row->reference_id),
                ];
            })
            ->values()
            ->all();
    }
}
