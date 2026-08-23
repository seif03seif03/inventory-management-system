<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOutItem extends Model
{
    protected $fillable = [
        'stock_out_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * The inverse of StockOut::items().
     * Laravel converts "stockOut" → "stock_out_id" automatically.
     */
    public function stockOut()
    {
        return $this->belongsTo(StockOut::class);
    }

    /**
     * Which product this line item refers to.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
