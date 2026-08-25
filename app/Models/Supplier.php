<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use LogsActivity;

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

    public function stockIns()
    {
        return $this->hasMany(StockIn::class);
    }

    /**
     * stock_ins holds a RESTRICT foreign key on supplier_id, so a supplier
     * named on any receipt cannot be deleted without orphaning that receipt.
     */
    public function hasStockHistory(): bool
    {
        return $this->stockIns()->exists();
    }
}
