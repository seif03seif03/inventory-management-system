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
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index()
    {
        $stockRows = DB::query()->fromSub(StockMovement::currentStockRows(), 'stock_rows')->get();
        $recentMovements = StockMovement::query()
            ->with(['product', 'warehouse'])
            ->whereDate('created_at', '>=', today()->subDays(29))
            ->orderBy('created_at')
            ->get();
        $movementSeries = $this->movementSeries($recentMovements);
        $categoryStock = $this->topGroupedRows($stockRows, 'category_name', 'current_stock');

        return view('reports.index', [
            'overviewAnalytics' => $this->stockAnalytics($stockRows),
            'movementChart' => $this->chart('Stock In vs Stock Out', $movementSeries['labels'], [
                ['label' => 'IN', 'color' => 'green', 'values' => $movementSeries['in']],
                ['label' => 'OUT', 'color' => 'red', 'values' => $movementSeries['out']],
            ]),
            'categoryChart' => $this->chart('Top Categories by Stock', $categoryStock->pluck('label')->all(), [
                ['label' => 'Stock', 'color' => 'blue', 'values' => $categoryStock->pluck('value')->all()],
            ]),
        ]);
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
        $this->validateReportFilters($request, ['product_id', 'category_id', 'warehouse_id', 'status']);

        $query = $this->stockQuery($request);
        $stockAnalytics = $this->stockAnalytics((clone $query)->get());
        $stockRows = $query->paginate(20)->withQueryString();

        return view('reports.stock', array_merge($this->filterLists(), compact('stockRows', 'stockAnalytics')));
    }

    public function movements(Request $request)
    {
        $this->validateReportFilters($request, ['product_id', 'warehouse_id', 'type', 'date_from', 'date_to']);

        $query = $this->movementsQuery($request);
        $movementAnalytics = $this->movementAnalytics((clone $query)->get());
        $movements = $query->paginate(20)->withQueryString();

        return view('reports.movements', array_merge($this->filterLists(), compact('movements', 'movementAnalytics')));
    }

    public function lowStock(Request $request)
    {
        $this->validateReportFilters($request, ['product_id', 'category_id', 'warehouse_id']);

        $query = $this->lowStockQuery($request);
        $lowStockAnalytics = $this->lowStockAnalytics((clone $query)->get());
        $lowStockRows = $query->paginate(20)->withQueryString();

        return view('reports.low-stock', array_merge($this->filterLists(), compact('lowStockRows', 'lowStockAnalytics')));
    }

    public function stockIn(Request $request)
    {
        $this->validateReportFilters($request, ['product_id', 'warehouse_id', 'supplier_id', 'date_from', 'date_to']);

        $query = $this->stockInQuery($request);
        $stockInAnalytics = $this->stockInAnalytics((clone $query)->get());
        $stockInItems = $query->paginate(20)->withQueryString();

        return view('reports.stock-in', array_merge($this->filterLists(), compact('stockInItems', 'stockInAnalytics')));
    }

    public function stockOut(Request $request)
    {
        $this->validateReportFilters($request, ['product_id', 'warehouse_id', 'distributor_id', 'date_from', 'date_to']);

        $query = $this->stockOutQuery($request);
        $stockOutAnalytics = $this->stockOutAnalytics((clone $query)->get());
        $stockOutItems = $query->paginate(20)->withQueryString();

        return view('reports.stock-out', array_merge($this->filterLists(), compact('stockOutItems', 'stockOutAnalytics')));
    }

    /*
    |--------------------------------------------------------------------------
    | On-screen analysis
    |--------------------------------------------------------------------------
    */

    private function stockAnalytics($rows): array
    {
        $totalStock = (int) $rows->sum('current_stock');
        $minimumStock = (int) $rows->sum('minimum_stock');
        $outCount = $rows->filter(fn ($row) => (int) $row->current_stock <= 0)->count();
        $lowCount = $rows->filter(fn ($row) => (int) $row->current_stock > 0 && (int) $row->current_stock <= (int) $row->minimum_stock)->count();
        $healthyCount = max(0, $rows->count() - $outCount - $lowCount);

        $topWarehouse = $this->topGroupedRows($rows, 'warehouse_name', 'current_stock')->first();
        $inactiveCount = $rows->where('product_active', false)->count();

        return [
            'metrics' => [
                $this->metric('Total Stock', number_format($totalStock), 'fa-boxes-stacked', 'green'),
                $this->metric('Minimum Required', number_format($minimumStock), 'fa-layer-group', 'blue'),
                $this->metric('Low Stock Rows', number_format($lowCount), 'fa-triangle-exclamation', 'amber'),
                $this->metric('Out of Stock Rows', number_format($outCount), 'fa-circle-exclamation', 'red'),
            ],
            'insights' => array_values(array_filter([
                $outCount > 0 ? $this->insight(number_format($outCount) . ' stock rows are out and need replenishment first.', 'red') : null,
                $lowCount > 0 ? $this->insight(number_format($lowCount) . ' rows are at or below their minimum level.', 'amber') : $this->insight('All filtered stock rows are above minimum where stock exists.', 'green'),
                $topWarehouse ? $this->insight($topWarehouse['label'] . ' holds the most filtered stock with ' . number_format($topWarehouse['value']) . ' units.', 'blue') : null,
                $inactiveCount > 0 ? $this->insight(number_format($inactiveCount) . ' inactive product rows are still carrying stock.', 'gray') : null,
            ])),
            'chart' => $this->chart('Stock Status Mix', ['In Stock', 'Low Stock', 'Out of Stock'], [
                ['label' => 'Rows', 'color' => 'blue', 'values' => [$healthyCount, $lowCount, $outCount]],
            ]),
        ];
    }

    private function lowStockAnalytics($rows): array
    {
        $shortfalls = $rows->map(function ($row) {
            $row->shortfall = max(0, (int) $row->minimum_stock - (int) $row->current_stock);
            return $row;
        });

        $totalShortfall = (int) $shortfalls->sum('shortfall');
        $outCount = $shortfalls->filter(fn ($row) => (int) $row->current_stock <= 0)->count();
        $worst = $shortfalls->sortByDesc('shortfall')->first();
        $averageCoverage = $shortfalls->count()
            ? round($shortfalls->avg(fn ($row) => (int) $row->minimum_stock > 0 ? ((int) $row->current_stock / (int) $row->minimum_stock) * 100 : 0))
            : 0;
        $topShortfalls = $shortfalls->sortByDesc('shortfall')->take(8)->values();

        return [
            'metrics' => [
                $this->metric('Low Stock Rows', number_format($rows->count()), 'fa-triangle-exclamation', 'red'),
                $this->metric('Total Shortfall', number_format($totalShortfall), 'fa-arrow-trend-down', 'amber'),
                $this->metric('Out of Stock', number_format($outCount), 'fa-circle-exclamation', 'red'),
                $this->metric('Average Coverage', $averageCoverage . '%', 'fa-gauge-high', 'blue'),
            ],
            'insights' => array_values(array_filter([
                $worst ? $this->insight($worst->product_name . ' has the largest shortfall at ' . number_format($worst->shortfall) . ' units.', 'red') : $this->insight('No filtered products are below their minimum stock.', 'green'),
                $totalShortfall > 0 ? $this->insight(number_format($totalShortfall) . ' units are needed to bring this view back to minimum levels.', 'amber') : null,
                $outCount > 0 ? $this->insight(number_format($outCount) . ' low-stock rows are completely out.', 'red') : null,
            ])),
            'chart' => $this->chart('Largest Shortfalls', $topShortfalls->pluck('product_name')->all(), [
                ['label' => 'Shortfall', 'color' => 'red', 'values' => $topShortfalls->pluck('shortfall')->map(fn ($value) => (int) $value)->all()],
            ]),
        ];
    }

    private function movementAnalytics($movements): array
    {
        $stockIn = (int) $movements->where('type', StockMovement::TYPE_IN)->sum('quantity');
        $stockOut = (int) $movements->where('type', StockMovement::TYPE_OUT)->sum('quantity');
        $net = $stockIn - $stockOut;
        $topProduct = $this->topGroupedRows($movements, fn ($movement) => $movement->product->name ?? 'Unknown', 'quantity')->first();
        $byDate = $this->movementSeries($movements);

        return [
            'metrics' => [
                $this->metric('Stock In', number_format($stockIn), 'fa-inbox', 'green'),
                $this->metric('Stock Out', number_format($stockOut), 'fa-arrow-up-from-bracket', 'red'),
                $this->metric('Net Movement', number_format($net), 'fa-right-left', $net >= 0 ? 'blue' : 'amber'),
                $this->metric('Movements', number_format($movements->count()), 'fa-list-check', 'gray'),
            ],
            'insights' => array_values(array_filter([
                $this->insight('Filtered activity produced a net ' . number_format($net) . ' unit movement.', $net >= 0 ? 'green' : 'amber'),
                $topProduct ? $this->insight($topProduct['label'] . ' had the highest movement volume at ' . number_format($topProduct['value']) . ' units.', 'blue') : null,
                $stockOut > $stockIn ? $this->insight('Outbound volume is higher than inbound volume for these filters.', 'red') : null,
            ])),
            'chart' => $this->chart('Movement by Date', $byDate['labels'], [
                ['label' => 'IN', 'color' => 'green', 'values' => $byDate['in']],
                ['label' => 'OUT', 'color' => 'red', 'values' => $byDate['out']],
            ]),
        ];
    }

    private function stockInAnalytics($items): array
    {
        $totalQuantity = (int) $items->sum('quantity');
        $totalCost = (float) $items->sum(fn ($item) => $item->lineTotal());
        $topProduct = $this->topGroupedRows($items, fn ($item) => $item->product->name ?? 'Unknown', 'quantity')->first();
        $topSupplier = $this->topGroupedRows($items, fn ($item) => $item->stockIn->supplier->name ?? 'Unknown', 'quantity')->first();
        $series = $this->lineItemSeries($items, 'stockIn', 'receipt_date');

        return [
            'metrics' => [
                $this->metric('Received Units', number_format($totalQuantity), 'fa-inbox', 'green'),
                $this->metric('Total Cost', Money::format($totalCost), 'fa-money-bill-wave', 'blue'),
                $this->metric('Receipt Lines', number_format($items->count()), 'fa-list-check', 'gray'),
                $this->metric('Documents', number_format($items->pluck('stock_in_id')->unique()->count()), 'fa-file-lines', 'amber'),
            ],
            'insights' => array_values(array_filter([
                $topProduct ? $this->insight($topProduct['label'] . ' is the top received product at ' . number_format($topProduct['value']) . ' units.', 'green') : null,
                $topSupplier ? $this->insight($topSupplier['label'] . ' supplied the most filtered units.', 'blue') : null,
                $totalCost > 0 ? $this->insight('The filtered receipts total ' . Money::format($totalCost) . ' in stock value.', 'gray') : null,
            ])),
            'chart' => $this->chart('Received Units by Date', $series['labels'], [
                ['label' => 'Received', 'color' => 'green', 'values' => $series['values']],
            ]),
        ];
    }

    private function stockOutAnalytics($items): array
    {
        $totalQuantity = (int) $items->sum('quantity');
        $topProduct = $this->topGroupedRows($items, fn ($item) => $item->product->name ?? 'Unknown', 'quantity')->first();
        $topDistributor = $this->topGroupedRows($items, fn ($item) => $item->stockOut->distributor->name ?? 'Unknown', 'quantity')->first();
        $series = $this->lineItemSeries($items, 'stockOut', 'issue_date');

        return [
            'metrics' => [
                $this->metric('Issued Units', number_format($totalQuantity), 'fa-arrow-up-from-bracket', 'red'),
                $this->metric('Issue Lines', number_format($items->count()), 'fa-list-check', 'gray'),
                $this->metric('Documents', number_format($items->pluck('stock_out_id')->unique()->count()), 'fa-file-lines', 'amber'),
                $this->metric('Products Issued', number_format($items->pluck('product_id')->unique()->count()), 'fa-box', 'blue'),
            ],
            'insights' => array_values(array_filter([
                $topProduct ? $this->insight($topProduct['label'] . ' is the top issued product at ' . number_format($topProduct['value']) . ' units.', 'red') : null,
                $topDistributor ? $this->insight($topDistributor['label'] . ' received the most filtered units.', 'blue') : null,
                $totalQuantity > 0 ? $this->insight(number_format($totalQuantity) . ' units left inventory in this filtered view.', 'gray') : null,
            ])),
            'chart' => $this->chart('Issued Units by Date', $series['labels'], [
                ['label' => 'Issued', 'color' => 'red', 'values' => $series['values']],
            ]),
        ];
    }

    private function metric(string $label, string $value, string $icon, string $tone): array
    {
        return compact('label', 'value', 'icon', 'tone');
    }

    private function insight(string $text, string $tone): array
    {
        return compact('text', 'tone');
    }

    private function chart(string $title, array $labels, array $datasets): array
    {
        return [
            'title' => $title,
            'labels' => array_values($labels),
            'datasets' => $datasets,
        ];
    }

    private function topGroupedRows($rows, $labelBy, string $sumField)
    {
        return $rows->groupBy($labelBy)
            ->map(fn ($group, $label) => ['label' => (string) $label, 'value' => (int) $group->sum($sumField)])
            ->sortByDesc('value')
            ->values()
            ->take(8);
    }

    private function lineItemSeries($items, string $relation, string $dateField): array
    {
        $byDate = $items->groupBy(function ($item) use ($relation, $dateField) {
            $date = optional($item->{$relation})->{$dateField};

            return $date ? $date->format('Y-m-d') : 'No date';
        })
            ->map(fn ($group) => (int) $group->sum('quantity'))
            ->sortKeys();

        return [
            'labels' => $byDate->keys()->all(),
            'values' => $byDate->values()->all(),
        ];
    }

    private function movementSeries($movements): array
    {
        $byDate = $movements->groupBy(fn ($movement) => $movement->created_at->format('Y-m-d'))->sortKeys();

        return [
            'labels' => $byDate->keys()->all(),
            'in' => $byDate->map(fn ($group) => (int) $group->where('type', StockMovement::TYPE_IN)->sum('quantity'))->values()->all(),
            'out' => $byDate->map(fn ($group) => (int) $group->where('type', StockMovement::TYPE_OUT)->sum('quantity'))->values()->all(),
        ];
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
        $this->validateReportFilters($request, ['product_id', 'category_id', 'warehouse_id', 'status']);

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
        $this->validateReportFilters($request, ['product_id', 'category_id', 'warehouse_id']);

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
        $this->validateReportFilters($request, ['product_id', 'warehouse_id', 'type', 'date_from', 'date_to']);

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
        $this->validateReportFilters($request, ['product_id', 'warehouse_id', 'supplier_id', 'date_from', 'date_to']);

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

        // The amounts stay bare numbers so a spreadsheet reads the cells as
        // numbers; the currency is named in the column heading instead.
        return $this->render($format, 'stock-in-report', 'Stock In Report',
            ['Reference', 'Date', 'Supplier', 'Warehouse', 'Product', 'SKU', 'Quantity',
                'Unit Cost (' . Money::symbol() . ')', 'Line Total (' . Money::symbol() . ')'],
            $rows
        );
    }

    public function exportStockOut(Request $request, string $format)
    {
        $this->validateReportFilters($request, ['product_id', 'warehouse_id', 'distributor_id', 'date_from', 'date_to']);

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

    private function validateReportFilters(Request $request, array $fields): void
    {
        $rules = [
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'distributor_id' => ['nullable', 'integer', Rule::exists('distributors', 'id')],
            'type' => ['nullable', Rule::in([StockMovement::TYPE_IN, StockMovement::TYPE_OUT, 'in', 'out'])],
            'status' => ['nullable', Rule::in(['in', 'low', 'out'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];

        $request->validate(array_intersect_key($rules, array_flip($fields)));
    }

    private function filterLists(): array
    {
        return [
            // Every report page carries this whole filter bar, so it is the
            // hottest of the dropdown queries. Each select() lists exactly the
            // columns the <option> tags render; products need sku as well
            // because their label reads "name (sku)".
            'products' => Product::select('id', 'name', 'sku')->where('active', true)->orderBy('name')->get(),
            'categories' => Category::select('id', 'name')->orderBy('name')->get(),
            'warehouses' => Warehouse::select('id', 'name')->orderBy('name')->get(),
            'suppliers' => Supplier::select('id', 'name')->orderBy('name')->get(),
            'distributors' => Distributor::select('id', 'name')->orderBy('name')->get(),
        ];
    }
}
