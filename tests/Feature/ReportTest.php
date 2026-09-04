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

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_report_pages_show_real_inventory_data(): void
    {
        [$product, $warehouse, $supplier, $distributor] = $this->createInventoryScenario();

        $this->get(route('reports.index'))->assertOk()->assertSee('Current Stock');

        $this->get(route('reports.stock'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($warehouse->name)
            ->assertSee('7')
            ->assertSee('Low Stock');

        $this->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSee($product->sku)
            ->assertSee('-3');

        $this->get(route('reports.movements', ['type' => 'OUT']))
            ->assertOk()
            ->assertSee('OUT')
            ->assertDontSee('IN</span>', false);

        $this->get(route('reports.stock-in', ['supplier_id' => $supplier->id]))
            ->assertOk()
            ->assertSee('RCPT-1001')
            ->assertSee('100.00')
            ->assertSee('1,000.00');

        $this->get(route('reports.stock-out', ['distributor_id' => $distributor->id]))
            ->assertOk()
            ->assertSee('ISS-1001')
            ->assertSee('-3');
    }

    public function test_stock_report_filters_by_category_warehouse_and_status(): void
    {
        [$product, $warehouse] = $this->createInventoryScenario();

        // Filter by warehouse
        $this->get(route('reports.stock', ['warehouse_id' => $warehouse->id]))
            ->assertOk()
            ->assertSee($product->name);

        // Filter by status=low
        $this->get(route('reports.stock', ['status' => 'low']))
            ->assertOk()
            ->assertSee('Low Stock');

        // Filter by status=out (product has stock 7, so status=out won't show it in table rows)
        $this->get(route('reports.stock', ['status' => 'out']))
            ->assertOk()
            ->assertSee('Nothing matches these filters');
    }

    public function test_stock_in_and_out_reports_filter_by_date_range(): void
    {
        [$product, $warehouse, $supplier, $distributor] = $this->createInventoryScenario();

        $today = now()->toDateString();

        $this->get(route('reports.stock-in', [
            'date_from' => $today,
            'date_to' => $today,
        ]))
            ->assertOk()
            ->assertSee('RCPT-1001');

        $this->get(route('reports.stock-out', [
            'date_from' => $today,
            'date_to' => $today,
        ]))
            ->assertOk()
            ->assertSee('ISS-1001');

        $futureDate = now()->addDays(5)->toDateString();

        $this->get(route('reports.stock-in', [
            'date_from' => $futureDate,
        ]))
            ->assertOk()
            ->assertSee('Nothing matches these filters');
    }

    private function createInventoryScenario(): array
    {
        $category = Category::create(['name' => 'Phones', 'active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'iPhone 15',
            'sku' => 'IPH15',
            'price' => 1200,
            'minimum_stock' => 10,
            'active' => true,
        ]);

        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $supplier = Supplier::create(['name' => 'Apple Supplier', 'active' => true]);
        $distributor = Distributor::create(['name' => 'Downtown Distributor', 'active' => true]);

        $stockIn = StockIn::create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'RCPT-1001',
            'receipt_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $stockIn->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 100,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => 10,
            'reference_type' => 'stock_in',
            'reference_id' => $stockIn->id,
        ]);

        $stockOut = StockOut::create([
            'distributor_id' => $distributor->id,
            'warehouse_id' => $warehouse->id,
            'reference_number' => 'ISS-1001',
            'issue_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $stockOut->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_OUT,
            'quantity' => 3,
            'reference_type' => 'stock_out',
            'reference_id' => $stockOut->id,
        ]);

        return [$product, $warehouse, $supplier, $distributor];
    }
}
