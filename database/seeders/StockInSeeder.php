<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockInSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::orderBy('id')->get();
        $warehouses = Warehouse::orderBy('id')->get();
        $products = Product::orderBy('sku')->get()->values();

        DB::transaction(function () use ($suppliers, $warehouses, $products) {
            for ($i = 0; $i < 30; $i++) {
                $date = Carbon::today()->subMonths(5)->addDays($i * 5);

                $stockIn = StockIn::create([
                    'supplier_id' => $suppliers[$i % $suppliers->count()]->id,
                    'warehouse_id' => $warehouses[$i % $warehouses->count()]->id,
                    'reference_number' => sprintf('SI-DEMO-%04d', $i + 1),
                    'receipt_date' => $date->toDateString(),
                    'notes' => 'Demo opening and replenishment receipt.',
                    'status' => 'completed',
                ]);

                $this->stamp($stockIn, $date);

                foreach ([0, 30] as $offset) {
                    if (! isset($products[$i + $offset])) {
                        continue;
                    }

                    $product = $products[$i + $offset];
                    $quantity = 28 + (($i + $offset) % 6) * 6;

                    $stockIn->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_cost' => round((float) $product->price * 0.72, 2),
                    ]);

                    $movement = StockMovement::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $stockIn->warehouse_id,
                        'type' => StockMovement::TYPE_IN,
                        'quantity' => $quantity,
                        'reference_type' => StockMovement::REFERENCE_STOCK_IN,
                        'reference_id' => $stockIn->id,
                    ]);

                    $this->stamp($movement, $date);
                }
            }
        });
    }

    private function stamp($model, Carbon $date): void
    {
        $model->forceFill([
            'created_at' => $date->copy()->setTime(10, 0),
            'updated_at' => $date->copy()->setTime(10, 0),
        ])->saveQuietly();
    }
}
