<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockInItem extends Model
{
    protected $fillable = [
        'stock_in_id',
        'product_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    /**
     * The inverse of StockIn::items().
     * Note the camelCase method name stockIn() — Laravel converts that to the
     * stock_in_id foreign key automatically.
     */
    public function stockIn()
    {
        return $this->belongsTo(StockIn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Line total, calculated — never stored.
     * Used in the receipt views: 100 x 40000.00 = 4,000,000.00
     */
    public function lineTotal()
    {
        return $this->quantity * $this->unit_cost;
    }
}
