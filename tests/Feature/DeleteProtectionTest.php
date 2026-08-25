<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Products, warehouses, suppliers and distributors are all referenced by
 * RESTRICT foreign keys once they appear on a stock document. Deleting one
 * used to reach the database and come back as an unhandled constraint
 * violation (a 500 page). These tests pin the refusal down to a flash message
 * that the user can actually read.
 *
 * Note that the test database enforces foreign keys, so if a guard were
 * removed these tests would fail with a QueryException rather than silently
 * passing — which is exactly the regression we want to catch.
 */
class DeleteProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    // -----------------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------------

    private function product(string $name = 'iPhone 15', string $sku = 'PRD-1001'): Product
    {
        $category = Category::firstOrCreate(['name' => 'Electronics'], ['active' => true]);

        return Product::create([
            'category_id'   => $category->id,
            'name'          => $name,
            'sku'           => $sku,
            'price'         => 999,
            'minimum_stock' => 5,
            'active'        => true,
        ]);
    }

    private function warehouse(string $name = 'Main Warehouse'): Warehouse
    {
        return Warehouse::create(['name' => $name, 'active' => true]);
    }

    private function supplier(string $name = 'Acme Supplies'): Supplier
    {
        return Supplier::create(['name' => $name, 'active' => true]);
    }

    private function distributor(string $name = 'Nile Distribution'): Distributor
    {
        return Distributor::create(['name' => $name, 'active' => true]);
    }

    private function receipt(Supplier $supplier, Warehouse $warehouse, string $status = 'completed'): StockIn
    {
        return StockIn::create([
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'GRN-1001',
            'receipt_date'     => '2026-08-01',
            'status'           => $status,
        ]);
    }

    private function issue(Distributor $distributor, Warehouse $warehouse, string $status = 'completed'): StockOut
    {
        return StockOut::create([
            'distributor_id'   => $distributor->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'ISS-1001',
            'issue_date'       => '2026-08-02',
            'status'           => $status,
        ]);
    }

    // -----------------------------------------------------------------------
    // Products
    // -----------------------------------------------------------------------

    public function test_product_with_ledger_movements_cannot_be_deleted(): void
    {
        $product   = $this->product();
        $warehouse = $this->warehouse();

        StockMovement::create([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 50,
        ]);

        $this->from(route('products.index'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_on_a_receipt_line_cannot_be_deleted_even_without_a_movement(): void
    {
        // A 'pending' receipt writes an item row but no ledger movement, so the
        // guard must look past stock_movements alone.
        $product = $this->product();

        $this->receipt($this->supplier(), $this->warehouse(), 'pending')
            ->items()->create([
                'product_id' => $product->id,
                'quantity'   => 10,
                'unit_cost'  => 100,
            ]);

        $this->from(route('products.index'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_on_an_issue_line_cannot_be_deleted_even_without_a_movement(): void
    {
        $product = $this->product();

        $this->issue($this->distributor(), $this->warehouse(), 'pending')
            ->items()->create([
                'product_id' => $product->id,
                'quantity'   => 4,
            ]);

        $this->from(route('products.index'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_on_a_transfer_line_cannot_be_deleted(): void
    {
        $product = $this->product();

        WarehouseTransfer::create([
            'from_warehouse_id' => $this->warehouse('Warehouse A')->id,
            'to_warehouse_id'   => $this->warehouse('Warehouse B')->id,
            'reference_number'  => 'TRF-1001',
            'transfer_date'     => '2026-08-03',
            'status'            => WarehouseTransfer::STATUS_COMPLETED,
        ])->items()->create([
            'product_id' => $product->id,
            'quantity'   => 7,
        ]);

        $this->from(route('products.index'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_without_stock_history_can_still_be_deleted(): void
    {
        $product = $this->product();

        $this->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_the_refusal_message_is_rendered_on_the_products_page(): void
    {
        // The controller flashing an error is only half the fix — the index view
        // has to render it, or the delete looks like it silently did nothing.
        $product   = $this->product('Samsung S24', 'PRD-2002');
        $warehouse = $this->warehouse();

        StockMovement::create([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 20,
        ]);

        $this->from(route('products.index'))
            ->delete(route('products.destroy', $product));

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('cannot be deleted', false)
            ->assertSee('Samsung S24');
    }

    // -----------------------------------------------------------------------
    // Warehouses
    // -----------------------------------------------------------------------

    public function test_warehouse_with_ledger_movements_cannot_be_deleted(): void
    {
        $warehouse = $this->warehouse();

        StockMovement::create([
            'product_id'   => $this->product()->id,
            'warehouse_id' => $warehouse->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 30,
        ]);

        $this->from(route('warehouses.index'))
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_warehouse_used_only_as_a_transfer_destination_cannot_be_deleted(): void
    {
        // to_warehouse_id is a second RESTRICT foreign key on the same table,
        // so a warehouse that has only ever received a transfer is still locked.
        $source      = $this->warehouse('Warehouse A');
        $destination = $this->warehouse('Warehouse B');

        WarehouseTransfer::create([
            'from_warehouse_id' => $source->id,
            'to_warehouse_id'   => $destination->id,
            'reference_number'  => 'TRF-2002',
            'transfer_date'     => '2026-08-03',
            'status'            => WarehouseTransfer::STATUS_COMPLETED,
        ]);

        $this->from(route('warehouses.index'))
            ->delete(route('warehouses.destroy', $destination))
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('warehouses', ['id' => $destination->id]);
    }

    public function test_warehouse_named_on_a_receipt_cannot_be_deleted(): void
    {
        $warehouse = $this->warehouse();
        $this->receipt($this->supplier(), $warehouse);

        $this->from(route('warehouses.index'))
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_warehouse_without_stock_history_can_still_be_deleted(): void
    {
        $warehouse = $this->warehouse();

        $this->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_the_refusal_message_is_rendered_on_the_warehouse_detail_page(): void
    {
        // The detail page carries its own delete button, so back() lands here
        // and this view needs the error block too.
        $warehouse = $this->warehouse('Cairo Depot');

        StockMovement::create([
            'product_id'   => $this->product()->id,
            'warehouse_id' => $warehouse->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 12,
        ]);

        $this->from(route('warehouses.show', $warehouse))
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.show', $warehouse));

        $this->get(route('warehouses.show', $warehouse))
            ->assertOk()
            ->assertSee('cannot be deleted', false);
    }

    // -----------------------------------------------------------------------
    // Suppliers & distributors
    // -----------------------------------------------------------------------

    public function test_supplier_named_on_a_receipt_cannot_be_deleted(): void
    {
        $supplier = $this->supplier();
        $this->receipt($supplier, $this->warehouse());

        $this->from(route('suppliers.index'))
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_without_receipts_can_still_be_deleted(): void
    {
        $supplier = $this->supplier();

        $this->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_distributor_named_on_an_issue_cannot_be_deleted(): void
    {
        $distributor = $this->distributor();
        $this->issue($distributor, $this->warehouse());

        $this->from(route('distributors.index'))
            ->delete(route('distributors.destroy', $distributor))
            ->assertRedirect(route('distributors.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('distributors', ['id' => $distributor->id]);
    }

    public function test_distributor_without_issues_can_still_be_deleted(): void
    {
        $distributor = $this->distributor();

        $this->delete(route('distributors.destroy', $distributor))
            ->assertRedirect(route('distributors.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('distributors', ['id' => $distributor->id]);
    }
}
