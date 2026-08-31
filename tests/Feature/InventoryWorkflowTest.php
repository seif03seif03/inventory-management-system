<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_inventory_lifecycle_workflow(): void
    {
        // 1. Setup Admin role and User
        $adminRole = Role::create(['name' => 'Admin']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($user);

        // 2. Setup master data
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $supplier = Supplier::factory()->create();
        $distributor = Distributor::factory()->create();
        $warehouseA = Warehouse::factory()->create(['name' => 'Warehouse A']);
        $warehouseB = Warehouse::factory()->create(['name' => 'Warehouse B']);

        // 3. Verify starting stocks are zero
        $this->assertEquals(0, StockMovement::currentStock($product->id, $warehouseA->id));
        $this->assertEquals(0, StockMovement::currentStock($product->id, $warehouseB->id));

        // 4. Perform Stock In: receive 100 units at Warehouse A
        $this->post(route('stock-in.store'), [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouseA->id,
            'reference_number' => 'SI-0001',
            'receipt_date' => today()->toDateString(),
            'products' => [$product->id],
            'quantities' => [100],
            'unit_costs' => [10.00],
        ])->assertRedirect();

        // 5. Verify Stock In effect
        $this->assertEquals(100, StockMovement::currentStock($product->id, $warehouseA->id));
        $this->assertEquals(0, StockMovement::currentStock($product->id, $warehouseB->id));

        // 6. Perform Warehouse Transfer: move 40 units from Warehouse A to Warehouse B
        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $warehouseA->id,
            'to_warehouse_id' => $warehouseB->id,
            'reference_number' => 'TR-0001',
            'transfer_date' => today()->toDateString(),
            'products' => [$product->id],
            'quantities' => [40],
        ])->assertRedirect();

        // 7. Verify Transfer effect
        $this->assertEquals(60, StockMovement::currentStock($product->id, $warehouseA->id));
        $this->assertEquals(40, StockMovement::currentStock($product->id, $warehouseB->id));

        // 8. Perform Inventory Adjustment: decrease stock in Warehouse B by 10 units
        $this->post(route('adjustments.store'), [
            'warehouse_id' => $warehouseB->id,
            'reference_number' => 'ADJ-0001',
            'adjustment_date' => today()->toDateString(),
            'reason' => 'damage',
            'products' => [$product->id],
            'directions' => ['decrease'],
            'quantities' => [10],
        ])->assertRedirect();

        // 9. Verify Adjustment effect
        $this->assertEquals(60, StockMovement::currentStock($product->id, $warehouseA->id));
        $this->assertEquals(30, StockMovement::currentStock($product->id, $warehouseB->id));

        // 10. Perform Stock Out: issue 50 units from Warehouse A to Distributor
        $this->post(route('stock-out.store'), [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouseA->id,
            'reference_number' => 'SO-0001',
            'issue_date' => today()->toDateString(),
            'products' => [$product->id],
            'quantities' => [50],
        ])->assertRedirect();

        // 11. Verify final stocks
        $this->assertEquals(10, StockMovement::currentStock($product->id, $warehouseA->id));
        $this->assertEquals(30, StockMovement::currentStock($product->id, $warehouseB->id));

        // 12. Verify overall stock across all warehouses
        $this->assertEquals(40, StockMovement::totalStockAllWarehouses($product->id));
    }
}
