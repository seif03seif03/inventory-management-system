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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    /*
    |--------------------------------------------------------------------------
    | Query builders
    |--------------------------------------------------------------------------
    | Each report's filters live in one place so the on-screen page and its
    | PDF/CSV export can never drift apart — an export always reflects exactly
    | the filters the user is looking at.
    */

    private function stockQuery(Request $request)
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

        return $query->orderBy('product_name')->orderBy('warehouse_name');
    }

    private function movementsQuery(Request $request)
    {
        $query = StockMovement::query()->with(['product', 'warehouse'])->latest();

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

        return $query;
    }

    private function lowStockQuery(Request $request)
    {
        // product_active: retired products should not raise low-stock alerts.
        // currentStockRows() no longer filters them out itself, so this states
        // the intent here — the stock report deliberately shows them.
        $query = DB::query()
            ->fromSub(StockMovement::currentStockRows(), 'stock_rows')
            ->where('product_active', true)
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

        return $query->orderBy('product_name')->orderBy('warehouse_name');
    }

    private function stockInQuery(Request $request)
    {
        return StockInItem::query()
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
    }

    private function stockOutQuery(Request $request)
    {
        return StockOutItem::query()
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
    }

    /*
    |--------------------------------------------------------------------------
    | On-screen reports
    |--------------------------------------------------------------------------
    */

    public function stock(Request $request)
    {
        $stockRows = $this->stockQuery($request)->paginate(20)->withQueryString();

        return view('reports.stock', array_merge($this->filterLists(), compact('stockRows')));
    }

    public function movements(Request $request)
    {
        $movements = $this->movementsQuery($request)->paginate(20)->withQueryString();

        return view('reports.movements', array_merge($this->filterLists(), compact('movements')));
    }

    public function lowStock(Request $request)
    {
        $lowStockRows = $this->lowStockQuery($request)->paginate(20)->withQueryString();

        return view('reports.low-stock', array_merge($this->filterLists(), compact('lowStockRows')));
    }

    public function stockIn(Request $request)
    {
        $stockInItems = $this->stockInQuery($request)->paginate(20)->withQueryString();

        return view('reports.stock-in', array_merge($this->filterLists(), compact('stockInItems')));
    }

    public function stockOut(Request $request)
    {
        $stockOutItems = $this->stockOutQuery($request)->paginate(20)->withQueryString();

        return view('reports.stock-out', array_merge($this->filterLists(), compact('stockOutItems')));
    }

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    | One method per report. Each turns its filtered rows into a plain array of
    | headings + string cells, then hands that to the shared renderer — so PDF
    | and CSV always contain identical data and only the wrapper differs.
    |
    | Exports deliberately bypass pagination (the user asked for the whole
    | filtered set) but never bypass the filters themselves.
    */

    public function exportStock(Request $request, string $format)
    {
        $rows = $this->stockQuery($request)->get()->map(fn ($row) => [
            $row->product_name,
            $row->product_sku,
            $row->category_name,
            $row->warehouse_name,
            (int) $row->current_stock,
            (int) $row->minimum_stock,
            $row->product_active ? 'Active' : 'Inactive',
        ]);

        return $this->render($format, 'stock-report', 'Stock Report',
            ['Product', 'SKU', 'Category', 'Warehouse', 'Current Stock', 'Minimum Stock', 'Product Status'],
            $rows
        );
    }

    public function exportLowStock(Request $request, string $format)
    {
        $rows = $this->lowStockQuery($request)->get()->map(fn ($row) => [
            $row->product_name,
            $row->product_sku,
            $row->category_name,
            $row->warehouse_name,
            (int) $row->current_stock,
            (int) $row->minimum_stock,
            (int) $row->minimum_stock - (int) $row->current_stock,
        ]);

        return $this->render($format, 'low-stock-report', 'Low Stock Report',
            ['Product', 'SKU', 'Category', 'Warehouse', 'Current Stock', 'Minimum Stock', 'Shortfall'],
            $rows
        );
    }

    public function exportMovements(Request $request, string $format)
    {
        $rows = $this->movementsQuery($request)->get()->map(fn ($movement) => [
            $movement->created_at->format('Y-m-d H:i'),
            $movement->product->name ?? '—',
            $movement->product->sku ?? '—',
            $movement->warehouse->name ?? '—',
            $movement->type,
            $movement->quantity,
            $movement->reference_type
                ? str_replace('_', ' ', $movement->reference_type) . ' #' . $movement->reference_id
                : '—',
        ]);

        return $this->render($format, 'stock-movements', 'Stock Movement Report',
            ['Date', 'Product', 'SKU', 'Warehouse', 'Type', 'Quantity', 'Reference'],
            $rows
        );
    }

    public function exportStockIn(Request $request, string $format)
    {
        $rows = $this->stockInQuery($request)->get()->map(fn ($item) => [
            $item->stockIn->reference_number ?? '—',
            $item->stockIn->receipt_date?->format('Y-m-d') ?? '—',
            $item->stockIn->supplier->name ?? '—',
            $item->stockIn->warehouse->name ?? '—',
            $item->product->name ?? '—',
            $item->product->sku ?? '—',
            $item->quantity,
            number_format((float) $item->unit_cost, 2, '.', ''),
            number_format((float) $item->lineTotal(), 2, '.', ''),
        ]);

        return $this->render($format, 'stock-in-report', 'Stock In Report',
            ['Reference', 'Date', 'Supplier', 'Warehouse', 'Product', 'SKU', 'Quantity', 'Unit Cost', 'Line Total'],
            $rows
        );
    }

    public function exportStockOut(Request $request, string $format)
    {
        $rows = $this->stockOutQuery($request)->get()->map(fn ($item) => [
            $item->stockOut->reference_number ?? '—',
            $item->stockOut->issue_date?->format('Y-m-d') ?? '—',
            $item->stockOut->distributor->name ?? '—',
            $item->stockOut->warehouse->name ?? '—',
            $item->product->name ?? '—',
            $item->product->sku ?? '—',
            $item->quantity,
        ]);

        return $this->render($format, 'stock-out-report', 'Stock Out Report',
            ['Reference', 'Date', 'Distributor', 'Warehouse', 'Product', 'SKU', 'Quantity'],
            $rows
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Shared renderers
    |--------------------------------------------------------------------------
    */

    /**
     * The route constrains {format} to pdf|csv, so no other value can arrive.
     */
    private function render(string $format, string $slug, string $title, array $headings, $rows)
    {
        $filename = $slug . '-' . now()->format('Y-m-d');

        return $format === 'csv'
            ? $this->streamCsv($filename . '.csv', $headings, $rows)
            : $this->streamPdf($filename . '.pdf', $title, $headings, $rows);
    }

    /**
     * Written straight to the output stream rather than assembled in memory,
     * so a large report cannot exhaust PHP's memory limit.
     */
    private function streamCsv(string $filename, array $headings, $rows)
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 (Arabic product names) correctly instead
            // of mangling it to the system codepage.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function streamPdf(string $filename, string $title, array $headings, $rows)
    {
        return Pdf::loadView('exports.table', [
            'title'       => $title,
            'headings'    => $headings,
            'rows'        => $rows,
            'generatedAt' => now()->format('d M Y H:i'),
            'filters'     => $this->activeFilterSummary(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    /**
     * A printed report is worthless if the reader cannot tell what was filtered,
     * so the applied filters are stamped onto the PDF itself.
     */
    private function activeFilterSummary(): array
    {
        $labels = [
            'product_id'     => fn ($v) => 'Product: ' . (Product::find($v)->name ?? $v),
            'category_id'    => fn ($v) => 'Category: ' . (Category::find($v)->name ?? $v),
            'warehouse_id'   => fn ($v) => 'Warehouse: ' . (Warehouse::find($v)->name ?? $v),
            'supplier_id'    => fn ($v) => 'Supplier: ' . (Supplier::find($v)->name ?? $v),
            'distributor_id' => fn ($v) => 'Distributor: ' . (Distributor::find($v)->name ?? $v),
            'type'           => fn ($v) => 'Type: ' . strtoupper($v),
            'status'         => fn ($v) => 'Status: ' . ucfirst($v),
            'date_from'      => fn ($v) => 'From: ' . $v,
            'date_to'        => fn ($v) => 'To: ' . $v,
        ];

        $summary = [];

        foreach ($labels as $key => $describe) {
            if (request()->filled($key)) {
                $summary[] = $describe(request()->input($key));
            }
        }

        return $summary;
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
