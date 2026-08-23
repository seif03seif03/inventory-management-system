<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\StockInItem;
use App\Models\StockMovement;
use App\Models\StockOutItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function stock(Request $request)
    {
        $query = DB::query()->fromSub(StockMovement::currentStockRows(), 'stock_rows');

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($status = $request->input('status')) {
            if ($status === 'out') {
                $query->where('current_stock', '<=', 0);
            } elseif ($status === 'low') {
                $query->where('current_stock', '>', 0)
                    ->whereColumn('current_stock', '<=', 'minimum_stock');
            } elseif ($status === 'in') {
                $query->whereColumn('current_stock', '>', 'minimum_stock');
            }
        }

        $stockRows = $query
            ->orderBy('product_name')
            ->orderBy('warehouse_name')
            ->paginate(20)
            ->withQueryString();

        return view('reports.stock', array_merge(
            $this->filterLists(),
            compact('stockRows')
        ));
    }

    public function movements(Request $request)
    {
        $query = StockMovement::query()
            ->with(['product', 'warehouse'])
            ->latest();

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($type = $request->input('type')) {
            $query->where('type', strtoupper($type));
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $movements = $query->paginate(20)->withQueryString();

        return view('reports.movements', array_merge(
            $this->filterLists(),
            compact('movements')
        ));
    }

    public function lowStock(Request $request)
    {
        $query = DB::query()
            ->fromSub(StockMovement::currentStockRows(), 'stock_rows')
            ->where('minimum_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'minimum_stock');

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $lowStockRows = $query
            ->orderBy('product_name')
            ->orderBy('warehouse_name')
            ->paginate(20)
            ->withQueryString();

        return view('reports.low-stock', array_merge(
            $this->filterLists(),
            compact('lowStockRows')
        ));
    }

    public function stockIn(Request $request)
    {
        $query = StockInItem::query()
            ->with(['product', 'stockIn.supplier', 'stockIn.warehouse'])
            ->whereHas('stockIn', function ($stockInQuery) use ($request) {
                $stockInQuery->where('status', 'completed');

                if ($supplierId = $request->input('supplier_id')) {
                    $stockInQuery->where('supplier_id', $supplierId);
                }

                if ($warehouseId = $request->input('warehouse_id')) {
                    $stockInQuery->where('warehouse_id', $warehouseId);
                }

                if ($dateFrom = $request->input('date_from')) {
                    $stockInQuery->whereDate('receipt_date', '>=', $dateFrom);
                }

                if ($dateTo = $request->input('date_to')) {
                    $stockInQuery->whereDate('receipt_date', '<=', $dateTo);
                }
            })
            ->when($request->input('product_id'), function ($q, $productId) {
                $q->where('product_id', $productId);
            })
            ->latest();

        $stockInItems = $query->paginate(20)->withQueryString();

        return view('reports.stock-in', array_merge(
            $this->filterLists(),
            compact('stockInItems')
        ));
    }

    public function stockOut(Request $request)
    {
        $query = StockOutItem::query()
            ->with(['product', 'stockOut.distributor', 'stockOut.warehouse'])
            ->whereHas('stockOut', function ($stockOutQuery) use ($request) {
                $stockOutQuery->where('status', 'completed');

                if ($distributorId = $request->input('distributor_id')) {
                    $stockOutQuery->where('distributor_id', $distributorId);
                }

                if ($warehouseId = $request->input('warehouse_id')) {
                    $stockOutQuery->where('warehouse_id', $warehouseId);
                }

                if ($dateFrom = $request->input('date_from')) {
                    $stockOutQuery->whereDate('issue_date', '>=', $dateFrom);
                }

                if ($dateTo = $request->input('date_to')) {
                    $stockOutQuery->whereDate('issue_date', '<=', $dateTo);
                }
            })
            ->when($request->input('product_id'), function ($q, $productId) {
                $q->where('product_id', $productId);
            })
            ->latest();

        $stockOutItems = $query->paginate(20)->withQueryString();

        return view('reports.stock-out', array_merge(
            $this->filterLists(),
            compact('stockOutItems')
        ));
    }

    private function filterLists(): array
    {
        return [
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'distributors' => Distributor::orderBy('name')->get(),
        ];
    }
}
