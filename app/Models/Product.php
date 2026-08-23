<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'minimum_stock',
        'active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
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
        });
    }
}
