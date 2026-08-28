<?php

namespace Database\Seeders;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockOutSeeder extends Seeder
{
    public function run(): void
    {
        $distributors = Distributor::orderBy('id')->get();
        $warehouses = Warehouse::orderBy('id')->get();
        $products = Product::orderBy('sku')->limit(36)->get()->values();

        DB::transaction(function () use ($distributors, $warehouses, $products) {
            for ($i = 0; $i < 24; $i++) {
                $date = Carbon::today()->subMonths(4)->addDays($i * 5);
                $warehouse = $warehouses[$i % $warehouses->count()];

                $stockOut = StockOut::create([
                    'distributor_id' => $distributors[$i % $distributors->count()]->id,
                    'warehouse_id' => $warehouse->id,
                    'reference_number' => sprintf('SO-DEMO-%04d', $i + 1),
                    'issue_date' => $date->toDateString(),
                    'notes' => 'Demo distributor issue fulfilled from available stock.',
                    'status' => 'completed',
                ]);

                $this->stamp($stockOut, $date);

                $lines = 0;
                foreach ($products as $product) {
                    if ($lines >= 2) {
                        break;
                    }

                    $available = StockMovement::currentStock($product->id, $warehouse->id);
                    $quantity = 2 + (($i + $product->id) % 5);

                    if ($available < $quantity + 8) {
                        continue;
                    }

                    $stockOut->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                    ]);

                    $movement = StockMovement::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                        'type' => StockMovement::TYPE_OUT,
                        'quantity' => $quantity,
                        'reference_type' => StockMovement::REFERENCE_STOCK_OUT,
                        'reference_id' => $stockOut->id,
                    ]);

                    $this->stamp($movement, $date);
                    $lines++;
                }

                if ($lines === 0) {
                    $stockOut->delete();
                }
            }
        });
    }

    private function stamp($model, Carbon $date): void
    {
        $model->forceFill([
            'created_at' => $date->copy()->setTime(14, 0),
            'updated_at' => $date->copy()->setTime(14, 0),
        ])->saveQuietly();
    }
}
