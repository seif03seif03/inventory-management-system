<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function stockOuts()
    {
        return $this->hasMany(StockOut::class);
    }

    /**
     * stock_outs holds a RESTRICT foreign key on distributor_id, so a
     * distributor named on any issue cannot be deleted without orphaning it.
     */
    public function hasStockHistory(): bool
    {
        return $this->stockOuts()->exists();
    }
}
