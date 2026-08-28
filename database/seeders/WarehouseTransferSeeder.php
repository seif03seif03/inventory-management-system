<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehouseTransferSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = Warehouse::orderBy('id')->get()->values();
        $manager = User::where('email', 'manager@inventory.test')->first();
        $products = Product::orderBy('sku')->limit(30)->get()->values();

        DB::transaction(function () use ($warehouses, $manager, $products) {
            for ($i = 0; $i < 10; $i++) {
                $from = $warehouses[$i % $warehouses->count()];
                $to = $warehouses[($i + 1) % $warehouses->count()];
                $date = Carbon::today()->subMonths(2)->addDays($i * 6);

                $transfer = WarehouseTransfer::create([
                    'from_warehouse_id' => $from->id,
                    'to_warehouse_id' => $to->id,
                    'reference_number' => sprintf('TR-DEMO-%04d', $i + 1),
                    'transfer_date' => $date->toDateString(),
                    'notes' => 'Demo stock balancing transfer between warehouses.',
                    'status' => WarehouseTransfer::STATUS_COMPLETED,
                    'created_by' => $manager?->id,
                ]);

                $this->stamp($transfer, $date);

                $lines = 0;
                foreach ($products as $product) {
                    if ($lines >= 2) {
                        break;
                    }

                    $quantity = 3 + (($i + $product->id) % 4);
                    if (StockMovement::currentStock($product->id, $from->id) < $quantity + 6) {
                        continue;
                    }

                    $transfer->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                    ]);

                    foreach ([[StockMovement::TYPE_OUT, $from->id], [StockMovement::TYPE_IN, $to->id]] as [$type, $warehouseId]) {
                        $movement = StockMovement::create([
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouseId,
                            'type' => $type,
                            'quantity' => $quantity,
                            'reference_type' => StockMovement::REFERENCE_TRANSFER,
                            'reference_id' => $transfer->id,
                        ]);

                        $this->stamp($movement, $date);
                    }

                    $lines++;
                }

                if ($lines === 0) {
                    $transfer->delete();
                }
            }
        });
    }

    private function stamp($model, Carbon $date): void
    {
        $model->forceFill([
            'created_at' => $date->copy()->setTime(12, 0),
            'updated_at' => $date->copy()->setTime(12, 0),
        ])->saveQuietly();
    }
}
