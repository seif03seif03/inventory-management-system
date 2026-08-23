<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_displays_real_statistics_and_recent_data(): void
    {
        $category = Category::create(['name' => 'Electronics', 'active' => true]);

        $product1 = Product::create([
            'category_id' => $category->id,
            'name' => 'iPhone 15',
            'sku' => 'IPH15',
            'price' => 1200,
            'minimum_stock' => 10,
            'active' => true,
        ]);

        $product2 = Product::create([
            'category_id' => $category->id,
            'name' => 'Samsung Galaxy S24',
            'sku' => 'SGS24',
            'price' => 1000,
            'minimum_stock' => 5,
            'active' => true,
        ]);

        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $supplier = Supplier::create(['name' => 'Tech Supplier', 'active' => true]);
        $distributor = Distributor::create(['name' => 'Global Distributor', 'active' => true]);

        // Stock In today for iPhone 15: +15
        $stockIn = StockIn::create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ST-IN-1001',
            'receipt_date' => now()->toDateString(),
            'status' => 'completed',
        ]);
        $stockIn->items()->create([
            'product_id' => $product1->id,
            'quantity' => 15,
            'unit_cost' => 800,
        ]);
        StockMovement::create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => 15,
            'reference_type' => 'stock_in',
            'reference_id' => $stockIn->id,
        ]);

        // Stock Out today for iPhone 15: -8 (leaving stock = 7, which is <= minimum_stock 10)
        $stockOut = StockOut::create([
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ST-OUT-1001',
            'issue_date' => now()->toDateString(),
            'status' => 'completed',
        ]);
        $stockOut->items()->create([
            'product_id' => $product1->id,
            'quantity' => 8,
        ]);
        StockMovement::create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_OUT,
            'quantity' => 8,
            'reference_type' => 'stock_out',
            'reference_id' => $stockOut->id,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Total Products');
        $response->assertSee('2'); // 2 active products
        $response->assertSee('7'); // Total stock (15 - 8)
        $response->assertSee('15'); // Stock In today
        $response->assertSee('8'); // Stock Out today
        $response->assertSee('iPhone 15');
        $response->assertSee('IPH15');
        $response->assertSee('Main Warehouse');
        $response->assertSee('Low Stock');
    }
}
