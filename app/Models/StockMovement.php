<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Movement types. Using constants means a typo like 'In' becomes a
     * PHP error instead of a silently wrong stock calculation.
     */
    public const TYPE_IN = 'IN';
    public const TYPE_OUT = 'OUT';

    public const REFERENCE_STOCK_IN = 'stock_in';
    public const REFERENCE_STOCK_OUT = 'stock_out';
    public const REFERENCE_TRANSFER = 'warehouse_transfer';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockIn()
    {
        return $this->belongsTo(StockIn::class, 'reference_id');
    }

    public function stockOut()
    {
        return $this->belongsTo(StockOut::class, 'reference_id');
    }

    public function warehouseTransfer()
    {
        return $this->belongsTo(WarehouseTransfer::class, 'reference_id');
    }

    /**
     * THE stock calculation for the whole application.
     *
     *     current stock = total IN - total OUT
     *
     * ...for one product in one warehouse. Every other part of the app
     * (Stock Out validation, the Products page, low-stock checks) calls
     * this one method, so the rule lives in exactly one place.
     *
     * We deliberately run two simple SUM queries instead of one clever
     * raw-SQL query: it reads exactly like the business rule, and at this
     * data size the speed difference does not matter.
     */
    public static function currentStock(int $productId, int $warehouseId): int
    {
        $totalIn = self::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', self::TYPE_IN)
            ->sum('quantity');

        $totalOut = self::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', self::TYPE_OUT)
            ->sum('quantity');

        return (int) $totalIn - (int) $totalOut;
    }

    /**
     * Total stock for ONE product across ALL warehouses combined.
     *
     * Used on the Products index page to show a single "total stock" number.
     * Because we have no specific warehouse context there, summing across all
     * warehouses gives the most useful overview number.
     *
     * Two simple SUM queries (no GROUP BY needed) — always readable.
     */
    public static function totalStockAllWarehouses(int $productId): int
    {
        $totalIn  = self::where('product_id', $productId)->where('type', self::TYPE_IN)->sum('quantity');
        $totalOut = self::where('product_id', $productId)->where('type', self::TYPE_OUT)->sum('quantity');

        return max(0, (int) $totalIn - (int) $totalOut);
    }

    /**
     * Current stock rows for every Product + Warehouse pair that has movement.
     *
     * This is the reusable summary query used by the dashboard now, and by the
     * Phase 4 reports next. It keeps the same rule as currentStock():
     *
     *     current stock = SUM(IN) - SUM(OUT)
     */
    public static function currentStockRows()
    {
        return self::query()
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->where('products.active', true)
            ->select([
                'stock_movements.product_id',
                'stock_movements.warehouse_id',
                'products.category_id',
                'products.name as product_name',
                'products.sku as product_sku',
                'categories.name as category_name',
                'products.minimum_stock',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw(
                'SUM(CASE WHEN stock_movements.type = ? THEN stock_movements.quantity ELSE 0 END) -
                 SUM(CASE WHEN stock_movements.type = ? THEN stock_movements.quantity ELSE 0 END) as current_stock',
                [self::TYPE_IN, self::TYPE_OUT]
            )
            ->groupBy(
                'stock_movements.product_id',
                'stock_movements.warehouse_id',
                'products.category_id',
                'products.name',
                'products.sku',
                'categories.name',
                'products.minimum_stock',
                'warehouses.name'
            );
    }
}
