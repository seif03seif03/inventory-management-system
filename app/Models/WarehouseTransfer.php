<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class WarehouseTransfer extends Model
{
    use LogsActivity;

    protected $fillable = [
        'from_warehouse_id',
        'to_warehouse_id',
        'reference_number',
        'transfer_date',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public const STATUS_COMPLETED = 'completed';

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(WarehouseTransferItem::class, 'warehouse_transfer_id');
    }
}
