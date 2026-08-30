<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Warehouse;

class InventoryStockLock
{
    public static function lock(array $productIds, array $warehouseIds): void
    {
        $productIds = self::normalizeIds($productIds);
        $warehouseIds = self::normalizeIds($warehouseIds);

        if ($productIds !== []) {
            Product::whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
        }

        if ($warehouseIds !== []) {
            Warehouse::whereIn('id', $warehouseIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
        }
    }

    private static function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
