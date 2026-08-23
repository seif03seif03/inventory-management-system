<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOut extends Model
{
    /**
     * Laravel converts "StockOut" → "stock_outs" automatically,
     * which matches our migration, so no $table property needed.
     */
    protected $fillable = [
        'distributor_id',
        'warehouse_id',
        'reference_number',
        'issue_date',
        'notes',
        'status',
    ];

    protected $casts = [
        // Carbon date object → $stockOut->issue_date->format('d M Y') in Blade
        'issue_date' => 'date',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * "This issue was sent to one distributor."
     * Laravel looks for the distributor_id column on this table.
     */
    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * "The goods left from this warehouse."
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * "This issue has many product line items."
     * Laravel looks for stock_out_id on the stock_out_items table.
     */
    public function items()
    {
        return $this->hasMany(StockOutItem::class);
    }

    // -----------------------------------------------------------------------
    // Helper methods
    // -----------------------------------------------------------------------

    /**
     * Blade helpers so views never compare raw status strings.
     *
     * Usage in Blade:
     *   @if ($stockOut->isCompleted()) ... @endif
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
