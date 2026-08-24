<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_stock_out_pages_load(): void
    {
        $this->createScenario(10);

        $this->get(route('stock-out.index'))->assertOk();
        $this->get(route('stock-out.create'))->assertOk()->assertSee('Available Stock');
    }

    public function test_stock_out_creates_issue_items_and_out_movements(): void
    {
        [$product, $warehouse, $distributor] = $this->createScenario(10);

        $response = $this->post(route('stock-out.store'), [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-1001',
            'issue_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [4],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('stock_outs', [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-1001',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('stock_out_items', [
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_OUT,
            'quantity' => 4,
            'reference_type' => 'stock_out',
        ]);

        $this->assertSame(6, StockMovement::currentStock($product->id, $warehouse->id));
    }

    public function test_stock_out_rejects_insufficient_stock(): void
    {
        [$product, $warehouse, $distributor] = $this->createScenario(3);

        $this->post(route('stock-out.store'), [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-1002',
            'issue_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [4],
        ])->assertSessionHas('stockErrors');

        $this->assertDatabaseMissing('stock_outs', ['reference_number' => 'ISS-1002']);
        $this->assertSame(3, StockMovement::currentStock($product->id, $warehouse->id));
    }

    public function test_stock_out_rejects_duplicate_rows_that_exceed_combined_available_stock(): void
    {
        [$product, $warehouse, $distributor] = $this->createScenario(5);

        $this->post(route('stock-out.store'), [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-1003',
            'issue_date' => now()->toDateString(),
            'products' => [$product->id, $product->id],
            'quantities' => [3, 3],
        ])->assertSessionHas('stockErrors');

        $this->assertDatabaseMissing('stock_outs', ['reference_number' => 'ISS-1003']);
        $this->assertSame(5, StockMovement::currentStock($product->id, $warehouse->id));
    }

    public function test_stock_out_filters_narrow_the_index(): void
    {
        [$product, $warehouse, $distributor] = $this->createScenario(10);
        $otherWarehouse = Warehouse::create(['name' => 'Other Warehouse', 'active' => true]);
        $otherProduct = Product::create([
            'category_id' => $product->category_id,
            'name' => 'Samsung Galaxy S24',
            'sku' => 'SGS24',
            'price' => 900,
            'minimum_stock' => 2,
            'active' => true,
        ]);
        StockMovement::create([
            'product_id' => $otherProduct->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => 10,
            'reference_type' => 'test_setup',
            'reference_id' => 2,
        ]);

        $this->post(route('stock-out.store'), [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-MAIN',
            'issue_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [1],
        ]);

        $this->post(route('stock-out.store'), [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-OTHER-PRODUCT',
            'issue_date' => now()->toDateString(),
            'products' => [$otherProduct->id],
            'quantities' => [1],
        ]);

        $this->get(route('stock-out.index', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertSee('ISS-MAIN');

        $this->get(route('stock-out.index', ['warehouse_id' => $otherWarehouse->id]))
            ->assertOk()
            ->assertDontSee('ISS-MAIN');

        $this->get(route('stock-out.index', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('ISS-MAIN')
            ->assertDontSee('ISS-OTHER-PRODUCT');

        $this->get(route('stock-out.index', ['product_id' => $otherProduct->id]))
            ->assertOk()
            ->assertSee('ISS-OTHER-PRODUCT')
            ->assertDontSee('ISS-MAIN');
    }

    public function test_stock_out_requires_active_records(): void
    {
        [$product, $warehouse, $distributor] = $this->createScenario(10);

        $product->update(['active' => false]);

        $this->post(route('stock-out.store'), [
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-INACTIVE',
            'issue_date' => now()->toDateString(),
            'products' => [$product->id],
            'quantities' => [1],
        ])->assertSessionHasErrors('products.0');
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

        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $distributor = Distributor::create(['name' => 'Downtown Distributor', 'active' => true]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => $availableStock,
            'reference_type' => 'test_setup',
            'reference_id' => 1,
        ]);

        return [$product, $warehouse, $distributor];
    }
}
