<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
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
}
