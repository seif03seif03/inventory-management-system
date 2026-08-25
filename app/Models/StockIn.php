<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class StockIn extends Model
{
    use LogsActivity;

    /**
     * Laravel guesses the table name by pluralising the class name:
     * StockIn -> stock_ins. That matches our migration, so we don't
     * need to set $table manually.
     */
    protected $fillable = [
        'supplier_id',
        'warehouse_id',
        'reference_number',
        'receipt_date',
        'notes',
        'status',
    ];

    protected $casts = [
        // Gives us a Carbon date object, so in Blade we can call
        // $stockIn->receipt_date->format('d M Y')
        'receipt_date' => 'date',
    ];

    /**
     * belongsTo = "this receipt belongs to one supplier".
     * Laravel looks for the supplier_id column on this table.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * hasMany = "this receipt has many item lines".
     * Laravel looks for the stock_in_id column on the stock_in_items table.
     */
    public function items()
    {
        return $this->hasMany(StockInItem::class);
    }

    /**
     * Small helpers so Blade doesn't have to compare status strings itself.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
