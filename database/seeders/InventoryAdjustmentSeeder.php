<?php

namespace Database\Seeders;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::where('email', 'manager@inventory.test')->first();

        $targets = [
            ['KIN-NV2-1TB', 3, InventoryAdjustment::REASON_DAMAGE],
            ['SAM-980-1TB', 5, InventoryAdjustment::REASON_RECOUNT],
            ['BEL-USBC-1M', 8, InventoryAdjustment::REASON_LOSS],
            ['UGR-HDMI21-2M', 9, InventoryAdjustment::REASON_RECOUNT],
            ['PPR-A4-BOX', 12, InventoryAdjustment::REASON_CORRECTION],
            ['LBL-10050-ROLL', 10, InventoryAdjustment::REASON_DAMAGE],
        ];

        DB::transaction(function () use ($targets, $manager) {
            foreach ($targets as $index => [$sku, $targetStock, $reason]) {
                $product = Product::where('sku', $sku)->firstOrFail();
                $row = StockMovement::currentStockRows()
                    ->where('products.id', $product->id)
                    ->orderByDesc('current_stock')
                    ->first();

                if (! $row) {
                    continue;
                }

                $currentStock = (int) $row->current_stock;
                $difference = $currentStock - $targetStock;

                if ($difference <= 0) {
                    continue;
                }

                $date = Carbon::today()->subDays(24 - ($index * 3));
                $adjustment = InventoryAdjustment::create([
                    'warehouse_id' => $row->warehouse_id,
                    'reference_number' => sprintf('ADJ-DEMO-%04d', $index + 1),
                    'adjustment_date' => $date->toDateString(),
                    'reason' => $reason,
                    'notes' => 'Demo low-stock scenario generated from a counted quantity.',
                    'status' => InventoryAdjustment::STATUS_COMPLETED,
                    'created_by' => $manager?->id,
                ]);

                $this->stamp($adjustment, $date);

                $item = $adjustment->items()->create([
                    'product_id' => $product->id,
                    'direction' => InventoryAdjustmentItem::DIRECTION_DECREASE,
                    'quantity' => $difference,
                ]);

                $movement = StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $row->warehouse_id,
                    'type' => $item->movementType(),
                    'quantity' => $difference,
                    'reference_type' => StockMovement::REFERENCE_ADJUSTMENT,
                    'reference_id' => $adjustment->id,
                ]);

                $this->stamp($movement, $date);
            }
        });
    }

    private function stamp($model, Carbon $date): void
    {
        $model->forceFill([
            'created_at' => $date->copy()->setTime(16, 0),
            'updated_at' => $date->copy()->setTime(16, 0),
        ])->saveQuietly();
    }
}
