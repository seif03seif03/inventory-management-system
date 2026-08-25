<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use LogsActivity;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'barcode',
        'description',
        'price',
        'minimum_stock',
        'active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockInItems()
    {
        return $this->hasMany(StockInItem::class);
    }

    public function stockOutItems()
    {
        return $this->hasMany(StockOutItem::class);
    }

    public function transferItems()
    {
        return $this->hasMany(WarehouseTransferItem::class);
    }

    /**
     * Has this product ever appeared on a stock document?
     *
     * All four tables above hold a RESTRICT foreign key on product_id, because
     * deleting a product would leave the ledger unable to explain current
     * stock. A receipt line can exist without a movement (a receipt that is
     * still 'pending'), so every one of them has to be checked — not just the
     * ledger. exists() stops at the first match instead of counting rows.
     */
    public function hasStockHistory(): bool
    {
        return $this->stockMovements()->exists()
            || $this->stockInItems()->exists()
            || $this->stockOutItems()->exists()
            || $this->transferItems()->exists();
    }

    protected $casts = [
        'active' => 'boolean',
        'price' => 'decimal:2',
        'minimum_stock' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function ($product) {
            $product->sku = strtoupper($product->sku);

            // An empty string is not the same as "no barcode" for a unique
            // column — two blank strings would collide, two NULLs won't.
            if ($product->barcode === '') {
                $product->barcode = null;
            }
        });
    }
}
