<?php

namespace Database\Seeders;

use App\Models\InventoryAdjustment;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\WarehouseTransfer;
use Illuminate\Database\Seeder;

class DemoInventoryResetSeeder extends Seeder
{
    public function run(): void
    {
        StockMovement::where('reference_type', StockMovement::REFERENCE_ADJUSTMENT)
            ->whereIn('reference_id', InventoryAdjustment::where('reference_number', 'like', 'ADJ-DEMO-%')->select('id'))
            ->delete();

        StockMovement::where('reference_type', StockMovement::REFERENCE_TRANSFER)
            ->whereIn('reference_id', WarehouseTransfer::where('reference_number', 'like', 'TR-DEMO-%')->select('id'))
            ->delete();

        StockMovement::where('reference_type', StockMovement::REFERENCE_STOCK_OUT)
            ->whereIn('reference_id', StockOut::where('reference_number', 'like', 'SO-DEMO-%')->select('id'))
            ->delete();

        StockMovement::where('reference_type', StockMovement::REFERENCE_STOCK_IN)
            ->whereIn('reference_id', StockIn::where('reference_number', 'like', 'SI-DEMO-%')->select('id'))
            ->delete();

        InventoryAdjustment::where('reference_number', 'like', 'ADJ-DEMO-%')->delete();
        WarehouseTransfer::where('reference_number', 'like', 'TR-DEMO-%')->delete();
        StockOut::where('reference_number', 'like', 'SO-DEMO-%')->delete();
        StockIn::where('reference_number', 'like', 'SI-DEMO-%')->delete();
    }
}
