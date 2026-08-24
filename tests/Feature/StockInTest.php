<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * Build the records a receipt needs to point at.
     */
    private function setUpInventory(): array
    {
        $category = Category::create(['name' => 'Electronics', 'active' => true]);

        $product = Product::create([
            'category_id'   => $category->id,
            'name'          => 'iPhone 15',
            'sku'           => 'PRD-1001',
            'price'         => 799.00,
            'minimum_stock' => 10,
            'active'        => true,
        ]);

        $supplier  = Supplier::create(['name' => 'TechSource Egypt', 'active' => true]);
        $warehouse = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);

        return [$product, $supplier, $warehouse];
    }

    public function test_stock_in_pages_load(): void
    {
        $this->setUpInventory();

        $this->get(route('stock-in.index'))->assertOk();

        $this->get(route('stock-in.create'))
            ->assertOk()
            ->assertSee('TechSource Egypt')   // supplier dropdown comes from the DB
            ->assertSee('Main Warehouse')     // warehouse dropdown comes from the DB
            ->assertSee('PRD-1001');          // product dropdown comes from the DB
    }

    public function test_a_receipt_creates_items_and_in_movements_and_raises_stock(): void
    {
        [$product, $supplier, $warehouse] = $this->setUpInventory();

        $response = $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-2201',
            'receipt_date'     => '2026-08-18',
            'notes'            => 'First delivery',
            'products'         => [$product->id],
            'quantities'       => [100],
            'unit_costs'       => [40000],
        ]);

        $stockIn = StockIn::firstOrFail();
        $response->assertRedirect(route('stock-in.show', $stockIn));

        // 1. the parent receipt, saved as completed
        $this->assertDatabaseHas('stock_ins', [
            'id'               => $stockIn->id,
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-2201',
            'status'           => 'completed',
        ]);

        // 2. the child item row
        $this->assertDatabaseHas('stock_in_items', [
            'stock_in_id' => $stockIn->id,
            'product_id'  => $product->id,
            'quantity'    => 100,
        ]);

        // 3. the ledger entry that actually moves the stock
        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $product->id,
            'warehouse_id'   => $warehouse->id,
            'type'           => 'IN',
            'quantity'       => 100,
            'reference_type' => 'stock_in',
            'reference_id'   => $stockIn->id,
        ]);

        // 4. current stock = 100
        $this->assertSame(100, StockMovement::currentStock($product->id, $warehouse->id));

        // A second receipt of 50 must take it to 150.
        $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-2202',
            'receipt_date'     => '2026-08-19',
            'products'         => [$product->id],
            'quantities'       => [50],
            'unit_costs'       => [40000],
        ]);

        $this->assertSame(150, StockMovement::currentStock($product->id, $warehouse->id));
        $this->assertSame(2, StockMovement::count());
    }

    public function test_a_receipt_can_hold_several_products(): void
    {
        [$product, $supplier, $warehouse] = $this->setUpInventory();

        $second = Product::create([
            'category_id'   => $product->category_id,
            'name'          => 'Samsung S24',
            'sku'           => 'PRD-1002',
            'price'         => 749.00,
            'minimum_stock' => 5,
            'active'        => true,
        ]);

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-2203',
            'receipt_date'     => '2026-08-18',
            'products'         => [$product->id, $second->id],
            'quantities'       => [100, 50],
            'unit_costs'       => [40000, 30000],
        ]);

        $stockIn = StockIn::firstOrFail();

        $this->assertSame(2, $stockIn->items()->count());
        $this->assertSame(150, (int) $stockIn->items()->sum('quantity'));
        $this->assertSame(100, StockMovement::currentStock($product->id, $warehouse->id));
        $this->assertSame(50, StockMovement::currentStock($second->id, $warehouse->id));
    }

    public function test_stock_is_tracked_per_product_and_warehouse(): void
    {
        [$product, $supplier, $warehouse] = $this->setUpInventory();
        $second = Warehouse::create(['name' => 'North Branch', 'active' => true]);

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-A',
            'receipt_date'     => '2026-08-18',
            'products'         => [$product->id],
            'quantities'       => [70],
            'unit_costs'       => [40000],
        ]);

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $second->id,
            'reference_number' => 'RCPT-B',
            'receipt_date'     => '2026-08-18',
            'products'         => [$product->id],
            'quantities'       => [30],
            'unit_costs'       => [40000],
        ]);

        // The same product holds different quantities in different warehouses.
        $this->assertSame(70, StockMovement::currentStock($product->id, $warehouse->id));
        $this->assertSame(30, StockMovement::currentStock($product->id, $second->id));
    }

    public function test_the_receipt_detail_page_shows_real_data(): void
    {
        [$product, $supplier, $warehouse] = $this->setUpInventory();

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-2201',
            'receipt_date'     => '2026-08-18',
            'notes'            => 'Handle with care',
            'products'         => [$product->id],
            'quantities'       => [100],
            'unit_costs'       => [40000],
        ]);

        $stockIn = StockIn::firstOrFail();

        $this->get(route('stock-in.show', $stockIn))
            ->assertOk()
            ->assertSee('RCPT-2201')
            ->assertSee('TechSource Egypt')
            ->assertSee('Main Warehouse')
            ->assertSee('18 Aug 2026')
            ->assertSee('Handle with care')
            ->assertSee('iPhone 15')
            ->assertSee('Completed');

        // And the index shows the calculated totals.
        $this->get(route('stock-in.index'))
            ->assertOk()
            ->assertSee('RCPT-2201')
            ->assertSee('+100');
    }

    public function test_zero_or_negative_quantity_is_rejected_and_nothing_is_written(): void
    {
        [$product, $supplier, $warehouse] = $this->setUpInventory();

        $payload = [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-BAD',
            'receipt_date'     => '2026-08-18',
            'products'         => [$product->id],
            'unit_costs'       => [40000],
        ];

        $this->post(route('stock-in.store'), $payload + ['quantities' => [0]])
            ->assertSessionHasErrors('quantities.0');

        $this->post(route('stock-in.store'), $payload + ['quantities' => [-5]])
            ->assertSessionHasErrors('quantities.0');

        // The whole operation is rejected before anything is written.
        $this->assertDatabaseCount('stock_ins', 0);
        $this->assertDatabaseCount('stock_in_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_receipt_needs_at_least_one_valid_item(): void
    {
        [, $supplier, $warehouse] = $this->setUpInventory();

        $base = [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-EMPTY',
            'receipt_date'     => '2026-08-18',
        ];

        // No items at all.
        $this->post(route('stock-in.store'), $base)->assertSessionHasErrors('products');

        // An empty item row.
        $this->post(route('stock-in.store'), $base + [
            'products'   => [''],
            'quantities' => [''],
            'unit_costs' => [''],
        ])->assertSessionHasErrors(['products.0', 'quantities.0', 'unit_costs.0']);

        // A product that does not exist.
        $this->post(route('stock-in.store'), $base + [
            'products'   => [99999],
            'quantities' => [5],
            'unit_costs' => [100],
        ])->assertSessionHasErrors('products.0');

        $this->assertDatabaseCount('stock_ins', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_parent_fields_are_required(): void
    {
        $this->setUpInventory();

        $this->post(route('stock-in.store'), [])
            ->assertSessionHasErrors([
                'supplier_id',
                'warehouse_id',
                'reference_number',
                'receipt_date',
                'products',
            ]);

        // Non-existent supplier / warehouse are rejected too.
        $this->post(route('stock-in.store'), [
            'supplier_id'      => 99999,
            'warehouse_id'     => 99999,
            'reference_number' => 'RCPT-X',
            'receipt_date'     => 'not-a-date',
            'products'         => [1],
            'quantities'       => [1],
            'unit_costs'       => [1],
        ])->assertSessionHasErrors(['supplier_id', 'warehouse_id', 'receipt_date']);

        $this->assertDatabaseCount('stock_ins', 0);
    }

    public function test_index_filters_narrow_the_list(): void
    {
        [$product, $supplier, $warehouse] = $this->setUpInventory();
        $otherSupplier  = Supplier::create(['name' => 'Nile Distribution', 'active' => true]);
        $otherWarehouse = Warehouse::create(['name' => 'Alexandria Warehouse', 'active' => true]);
        $otherProduct   = Product::create([
            'category_id'   => $product->category_id,
            'name'          => 'Samsung Galaxy S24',
            'sku'           => 'PRD-2002',
            'price'         => 699.00,
            'minimum_stock' => 10,
            'active'        => true,
        ]);

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'RCPT-AAA',
            'receipt_date'     => '2026-08-18',
            'products'         => [$product->id],
            'quantities'       => [10],
            'unit_costs'       => [100],
        ]);

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $otherSupplier->id,
            'warehouse_id'     => $otherWarehouse->id,
            'reference_number' => 'RCPT-BBB',
            'receipt_date'     => '2026-08-01',
            'products'         => [$otherProduct->id],
            'quantities'       => [20],
            'unit_costs'       => [100],
        ]);

        // Search by reference number
        $this->get(route('stock-in.index', ['search' => 'AAA']))
            ->assertSee('RCPT-AAA')->assertDontSee('RCPT-BBB');

        // Filter by supplier
        $this->get(route('stock-in.index', ['supplier_id' => $otherSupplier->id]))
            ->assertSee('RCPT-BBB')->assertDontSee('RCPT-AAA');

        // Filter by date
        $this->get(route('stock-in.index', ['date' => '2026-08-01']))
            ->assertSee('RCPT-BBB')->assertDontSee('RCPT-AAA');

        // Filter by status — both are completed, so 'pending' shows neither
        $this->get(route('stock-in.index', ['status' => 'pending']))
            ->assertDontSee('RCPT-AAA')->assertDontSee('RCPT-BBB');

        // Filter by warehouse
        $this->get(route('stock-in.index', ['warehouse_id' => $otherWarehouse->id]))
            ->assertSee('RCPT-BBB')->assertDontSee('RCPT-AAA');

        // Filter by product
        $this->get(route('stock-in.index', ['product_id' => $otherProduct->id]))
            ->assertSee('RCPT-BBB')->assertDontSee('RCPT-AAA');

        // Search by product SKU finds both receipts
        $this->get(route('stock-in.index', ['search' => 'PRD-1001']))
            ->assertSee('RCPT-AAA')->assertDontSee('RCPT-BBB');
    }
}
