<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAdjustmentItem extends Model
{
    protected $fillable = [
        'inventory_adjustment_id',
        'product_id',
        'direction',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Direction of the correction. quantity is always positive; this decides
     * whether it is added or subtracted — mirroring how stock_movements uses
     * its type column, so the codebase has exactly one way to express this.
     */
    public const DIRECTION_INCREASE = 'increase';
    public const DIRECTION_DECREASE = 'decrease';

    public const DIRECTIONS = [
        self::DIRECTION_INCREASE,
        self::DIRECTION_DECREASE,
    ];

    public function adjustment()
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isIncrease(): bool
    {
        return $this->direction === self::DIRECTION_INCREASE;
    }

    /**
     * The line's effect on stock: +n for an increase, -n for a decrease.
     * For display and totals only — the ledger still stores a positive
     * quantity alongside an IN or OUT type.
     */
    public function signedQuantity(): int
    {
        return $this->isIncrease() ? $this->quantity : -$this->quantity;
    }

    /**
     * The stock_movements type this line produces.
     */
    public function movementType(): string
    {
        return $this->isIncrease() ? StockMovement::TYPE_IN : StockMovement::TYPE_OUT;
    }
}
