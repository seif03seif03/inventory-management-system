<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'location',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function outgoingTransfers()
    {
        return $this->hasMany(WarehouseTransfer::class, 'from_warehouse_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(WarehouseTransfer::class, 'to_warehouse_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockIns()
    {
        return $this->hasMany(StockIn::class);
    }

    public function stockOuts()
    {
        return $this->hasMany(StockOut::class);
    }

    /**
     * Has anything ever moved through this warehouse?
     *
     * The ledger, receipts, issues and BOTH transfer columns hold a RESTRICT
     * foreign key on this warehouse, so any one of these rows makes it
     * undeletable. exists() stops at the first match instead of counting rows.
     */
    public function hasStockHistory(): bool
    {
        return $this->stockMovements()->exists()
            || $this->stockIns()->exists()
            || $this->stockOuts()->exists()
            || $this->outgoingTransfers()->exists()
            || $this->incomingTransfers()->exists();
    }
}
