<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $whA;
    private Warehouse $whB;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $category = \App\Models\Category::create(['name' => 'Test Category', 'active' => true]);

        $this->whA = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $this->whB = Warehouse::create(['name' => 'Branch Warehouse', 'active' => true]);

        $this->product = Product::create([
            'name'          => 'iPhone 15',
            'sku'           => 'IPH-15',
            'category_id'   => $category->id,
            'price'         => 999,
            'minimum_stock' => 5,
            'active'        => true,
        ]);

        // Seed 100 units into Main Warehouse via a StockMovement
        StockMovement::create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->whA->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 100,
        ]);
    }

    public function test_transfer_index_page_loads(): void
    {
        $this->actingAs($this->admin)->get(route('transfers.index'))->assertOk();
    }

    public function test_transfer_create_form_loads(): void
    {
        $this->actingAs($this->admin)->get(route('transfers.create'))->assertOk();
    }

    public function test_successful_transfer_creates_movements_and_redirects(): void
    {
        $response = $this->actingAs($this->admin)->post(route('transfers.store'), [
            'from_warehouse_id' => $this->whA->id,
            'to_warehouse_id'   => $this->whB->id,
            'reference_number'  => 'TRF-001',
            'transfer_date'     => now()->toDateString(),
            'notes'             => null,
            'products'          => [$this->product->id],
            'quantities'        => [30],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // OUT movement in source warehouse
        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $this->product->id,
            'warehouse_id'   => $this->whA->id,
            'type'           => 'OUT',
            'quantity'       => 30,
            'reference_type' => 'warehouse_transfer',
        ]);

        // IN movement in destination warehouse
        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $this->product->id,
            'warehouse_id'   => $this->whB->id,
            'type'           => 'IN',
            'quantity'       => 30,
            'reference_type' => 'warehouse_transfer',
        ]);

        // Stock levels updated correctly via the ledger
        $this->assertEquals(70, StockMovement::currentStock($this->product->id, $this->whA->id));
        $this->assertEquals(30, StockMovement::currentStock($this->product->id, $this->whB->id));
    }

    public function test_transfer_fails_when_source_and_destination_are_same(): void
    {
        $response = $this->actingAs($this->admin)->post(route('transfers.store'), [
            'from_warehouse_id' => $this->whA->id,
            'to_warehouse_id'   => $this->whA->id,
            'reference_number'  => 'TRF-002',
            'transfer_date'     => now()->toDateString(),
            'products'          => [$this->product->id],
            'quantities'        => [10],
        ]);

        $response->assertSessionHasErrors('to_warehouse_id');
        // No movements should have been written
        $this->assertEquals(100, StockMovement::currentStock($this->product->id, $this->whA->id));
    }

    public function test_transfer_fails_when_quantity_exceeds_available_stock(): void
    {
        $response = $this->actingAs($this->admin)->post(route('transfers.store'), [
            'from_warehouse_id' => $this->whA->id,
            'to_warehouse_id'   => $this->whB->id,
            'reference_number'  => 'TRF-003',
            'transfer_date'     => now()->toDateString(),
            'products'          => [$this->product->id],
            'quantities'        => [999],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        // No movements should have been written
        $this->assertEquals(100, StockMovement::currentStock($this->product->id, $this->whA->id));
        $this->assertEquals(0, StockMovement::currentStock($this->product->id, $this->whB->id));
    }

    public function test_transfer_show_page_loads(): void
    {
        // Create a minimal transfer first
        $this->actingAs($this->admin)->post(route('transfers.store'), [
            'from_warehouse_id' => $this->whA->id,
            'to_warehouse_id'   => $this->whB->id,
            'reference_number'  => 'TRF-SHOW',
            'transfer_date'     => now()->toDateString(),
            'products'          => [$this->product->id],
            'quantities'        => [5],
        ]);

        $transfer = \App\Models\WarehouseTransfer::first();
        $this->actingAs($this->admin)->get(route('transfers.show', $transfer))->assertOk();
    }
}
