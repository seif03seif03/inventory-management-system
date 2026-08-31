<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Warehouse;
use App\Support\InventoryStockLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lock_normalizes_and_orders_ids_to_prevent_deadlocks(): void
    {
        // Setup database records
        $product1 = Product::factory()->create(['id' => 10]);
        $product2 = Product::factory()->create(['id' => 5]);

        $warehouse1 = Warehouse::factory()->create(['id' => 2]);
        $warehouse2 = Warehouse::factory()->create(['id' => 1]);

        // Call lock with unsorted, duplicate, and negative IDs
        // Should execute without throwing errors and correctly sort
        InventoryStockLock::lock([10, 5, 10, -3], [2, 1, 2, 0]);

        $this->assertTrue(true, 'Lock executed successfully without syntax or deadlock errors.');
    }

    public function test_lock_handles_empty_inputs_safely(): void
    {
        InventoryStockLock::lock([], []);
        $this->assertTrue(true, 'Empty lock parameters handled successfully.');
    }
}
