<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_transfer_pages_load(): void
    {
        $this->createScenario(10);

        $this->get(route('transfers.index'))->assertOk();
        $this->get(route('transfers.create'))
            ->assertOk()
            ->assertSee('Main Warehouse')
            ->assertSee('Alexandria Warehouse');
    }

    public function test_completed_transfer_moves_stock_between_warehouses(): void
    {
        [$product, $fromWarehouse, $toWarehouse] = $this->createScenario(100);

        $response = $this->post(route('transfers.store'), [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'reference_number' => 'TR-1001',
            'transfer_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [30],
        ]);

        $transfer = WarehouseTransfer::firstOrFail();
        $response->assertRedirect(route('transfers.show', $transfer));

        $this->assertDatabaseHas('warehouse_transfers', [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'reference_number' => 'TR-1001',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('warehouse_transfer_items', [
            'warehouse_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 30,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $fromWarehouse->id,
            'type' => StockMovement::TYPE_OUT,
            'quantity' => 30,
            'reference_type' => StockMovement::REFERENCE_TRANSFER,
            'reference_id' => $transfer->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $toWarehouse->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => 30,
            'reference_type' => StockMovement::REFERENCE_TRANSFER,
            'reference_id' => $transfer->id,
        ]);

        $this->assertSame(70, StockMovement::currentStock($product->id, $fromWarehouse->id));
        $this->assertSame(30, StockMovement::currentStock($product->id, $toWarehouse->id));
        $this->assertSame(100, StockMovement::totalStockAllWarehouses($product->id));
    }

    public function test_transfer_rejects_insufficient_source_stock(): void
    {
        [$product, $fromWarehouse, $toWarehouse] = $this->createScenario(10);

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'reference_number' => 'TR-1002',
            'transfer_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [11],
        ])->assertSessionHas('stockErrors');

        $this->assertDatabaseMissing('warehouse_transfers', ['reference_number' => 'TR-1002']);
        $this->assertSame(10, StockMovement::currentStock($product->id, $fromWarehouse->id));
        $this->assertSame(0, StockMovement::currentStock($product->id, $toWarehouse->id));
    }

    public function test_transfer_rejects_same_source_and_destination(): void
    {
        [$product, $fromWarehouse] = $this->createScenario(10);

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $fromWarehouse->id,
            'reference_number' => 'TR-SAME',
            'transfer_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [1],
        ])->assertSessionHasErrors('to_warehouse_id');

        $this->assertDatabaseMissing('warehouse_transfers', ['reference_number' => 'TR-SAME']);
    }

    public function test_transfer_rejects_duplicate_rows_that_exceed_combined_available_stock(): void
    {
        [$product, $fromWarehouse, $toWarehouse] = $this->createScenario(5);

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'reference_number' => 'TR-1003',
            'transfer_date' => now()->toDateString(),
            'products' => [$product->id, $product->id],
            'quantities' => [3, 3],
        ])->assertSessionHas('stockErrors');

        $this->assertDatabaseMissing('warehouse_transfers', ['reference_number' => 'TR-1003']);
        $this->assertSame(5, StockMovement::currentStock($product->id, $fromWarehouse->id));
    }

    public function test_transfer_filters_narrow_the_index(): void
    {
        [$product, $fromWarehouse, $toWarehouse] = $this->createScenario(10);
        $otherWarehouse = Warehouse::create(['name' => 'Cairo Warehouse', 'active' => true]);

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'reference_number' => 'TR-MAIN',
            'transfer_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [1],
        ]);

        $this->get(route('transfers.index', ['from_warehouse_id' => $fromWarehouse->id]))
            ->assertOk()
            ->assertSee('TR-MAIN');

        $this->get(route('transfers.index', ['from_warehouse_id' => $otherWarehouse->id]))
            ->assertOk()
            ->assertDontSee('TR-MAIN');
    }

    public function test_transfer_show_page_loads(): void
    {
        [$product, $fromWarehouse, $toWarehouse] = $this->createScenario(10);

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'reference_number' => 'TR-SHOW',
            'transfer_date' => now()->toDateString(),
            'notes' => 'Move phones south',
            'products' => [$product->id],
            'quantities' => [2],
        ]);

        $transfer = WarehouseTransfer::firstOrFail();

        $this->get(route('transfers.show', $transfer))
            ->assertOk()
            ->assertSee('TR-SHOW')
            ->assertSee('Main Warehouse')
            ->assertSee('Alexandria Warehouse')
            ->assertSee('iPhone 15');
    }

    private function createScenario(int $availableStock): array
    {
        $category = Category::create(['name' => 'Electronics', 'active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'iPhone 15',
            'sku' => 'IPH15',
            'price' => 1200,
            'minimum_stock' => 2,
            'active' => true,
        ]);

        $fromWarehouse = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $toWarehouse = Warehouse::create(['name' => 'Alexandria Warehouse', 'active' => true]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $fromWarehouse->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => $availableStock,
            'reference_type' => 'test_setup',
            'reference_id' => 1,
        ]);

        return [$product, $fromWarehouse, $toWarehouse];
    }
}
