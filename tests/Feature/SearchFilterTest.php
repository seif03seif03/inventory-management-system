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
 * Covers the search/filter behaviour added for Phase 6 Task 3, and in
 * particular the case that used to break silently: a text search combined
 * with a status filter. The searches OR several columns together, so without
 * closure grouping the OR escapes its clause and the status filter is
 * discarded — the page then returns everything and looks like it worked.
 */
class SearchFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole   = Role::create(['name' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($this->admin);
    }

    // -----------------------------------------------------------------------
    // Search + status together (the OR-grouping regression)
    // -----------------------------------------------------------------------

    public function test_category_search_and_status_filter_apply_together(): void
    {
        Category::create(['name' => 'Active Electronics', 'description' => 'gadgets', 'active' => true]);
        Category::create(['name' => 'Retired Electronics', 'description' => 'gadgets', 'active' => false]);

        $this->get(route('categories.index', ['search' => 'Electronics', 'active' => '1']))
            ->assertOk()
            ->assertSee('Active Electronics')
            ->assertDontSee('Retired Electronics');
    }

    public function test_category_search_matches_description(): void
    {
        Category::create(['name' => 'Widgets', 'description' => 'imported hardware', 'active' => true]);
        Category::create(['name' => 'Gizmos', 'description' => 'local produce', 'active' => true]);

        $this->get(route('categories.index', ['search' => 'imported']))
            ->assertOk()
            ->assertSee('Widgets')
            ->assertDontSee('Gizmos');
    }

    public function test_supplier_search_and_status_filter_apply_together(): void
    {
        Supplier::create(['name' => 'Acme Active', 'email' => 'a@acme.test', 'active' => true]);
        Supplier::create(['name' => 'Acme Retired', 'email' => 'r@acme.test', 'active' => false]);

        $this->get(route('suppliers.index', ['search' => 'Acme', 'active' => '1']))
            ->assertOk()
            ->assertSee('Acme Active')
            ->assertDontSee('Acme Retired');
    }

    public function test_distributor_search_and_status_filter_apply_together(): void
    {
        Distributor::create(['name' => 'Nile Active', 'phone' => '0100', 'active' => true]);
        Distributor::create(['name' => 'Nile Retired', 'phone' => '0200', 'active' => false]);

        $this->get(route('distributors.index', ['search' => 'Nile', 'active' => '1']))
            ->assertOk()
            ->assertSee('Nile Active')
            ->assertDontSee('Nile Retired');
    }

    public function test_warehouse_search_and_status_filter_apply_together(): void
    {
        Warehouse::create(['name' => 'Cairo Active', 'location' => 'Cairo', 'active' => true]);
        Warehouse::create(['name' => 'Cairo Retired', 'location' => 'Cairo', 'active' => false]);

        $this->get(route('warehouses.index', ['search' => 'Cairo', 'active' => '1']))
            ->assertOk()
            ->assertSee('Cairo Active')
            ->assertDontSee('Cairo Retired');
    }

    public function test_supplier_search_matches_phone_and_email(): void
    {
        Supplier::create(['name' => 'Alpha', 'email' => 'alpha@test.com', 'phone' => '0111222', 'active' => true]);
        Supplier::create(['name' => 'Beta', 'email' => 'beta@test.com', 'phone' => '0999888', 'active' => true]);

        $this->get(route('suppliers.index', ['search' => '0111222']))->assertOk()->assertSee('Alpha')->assertDontSee('Beta');
        $this->get(route('suppliers.index', ['search' => 'beta@test']))->assertOk()->assertSee('Beta')->assertDontSee('Alpha');
    }

    // -----------------------------------------------------------------------
    // Date ranges on the stock documents
    // -----------------------------------------------------------------------

    private function receipt(string $reference, string $date): StockIn
    {
        return StockIn::create([
            'supplier_id'      => Supplier::firstOrCreate(['name' => 'Acme'], ['active' => true])->id,
            'warehouse_id'     => Warehouse::firstOrCreate(['name' => 'Main'], ['active' => true])->id,
            'reference_number' => $reference,
            'receipt_date'     => $date,
            'status'           => 'completed',
        ]);
    }

    public function test_stock_in_filters_by_date_range(): void
    {
        $this->receipt('GRN-JAN', '2026-01-15');
        $this->receipt('GRN-JUN', '2026-06-15');

        $this->get(route('stock-in.index', ['date_from' => '2026-01-01', 'date_to' => '2026-01-31']))
            ->assertOk()
            ->assertSee('GRN-JAN')
            ->assertDontSee('GRN-JUN');
    }

    public function test_stock_in_still_honours_a_bookmarked_exact_date(): void
    {
        // The single-date filter predates the range and stays supported so old
        // URLs keep working.
        $this->receipt('GRN-JAN', '2026-01-15');
        $this->receipt('GRN-JUN', '2026-06-15');

        $this->get(route('stock-in.index', ['date' => '2026-01-15']))
            ->assertOk()
            ->assertSee('GRN-JAN')
            ->assertDontSee('GRN-JUN');
    }

    public function test_stock_out_filters_by_date_range(): void
    {
        $distributor = Distributor::create(['name' => 'Nile', 'active' => true]);
        $warehouse   = Warehouse::create(['name' => 'Main', 'active' => true]);

        foreach (['ISS-JAN' => '2026-01-20', 'ISS-JUN' => '2026-06-20'] as $reference => $date) {
            StockOut::create([
                'distributor_id'   => $distributor->id,
                'warehouse_id'     => $warehouse->id,
                'reference_number' => $reference,
                'issue_date'       => $date,
                'status'           => 'completed',
            ]);
        }

        $this->get(route('stock-out.index', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']))
            ->assertOk()
            ->assertSee('ISS-JUN')
            ->assertDontSee('ISS-JAN');
    }

    // -----------------------------------------------------------------------
    // Transfers: status + date range
    // -----------------------------------------------------------------------

    private function transfer(string $reference, string $date, string $status): WarehouseTransfer
    {
        return WarehouseTransfer::create([
            'from_warehouse_id' => Warehouse::firstOrCreate(['name' => 'Source'], ['active' => true])->id,
            'to_warehouse_id'   => Warehouse::firstOrCreate(['name' => 'Destination'], ['active' => true])->id,
            'reference_number'  => $reference,
            'transfer_date'     => $date,
            'status'            => $status,
        ]);
    }

    public function test_transfers_filter_by_status(): void
    {
        $this->transfer('TRF-DONE', '2026-03-01', WarehouseTransfer::STATUS_COMPLETED);
        $this->transfer('TRF-WAIT', '2026-03-02', 'pending');

        $this->get(route('transfers.index', ['status' => WarehouseTransfer::STATUS_COMPLETED]))
            ->assertOk()
            ->assertSee('TRF-DONE')
            ->assertDontSee('TRF-WAIT');
    }

    public function test_transfers_filter_by_date_range(): void
    {
        $this->transfer('TRF-MAR', '2026-03-10', WarehouseTransfer::STATUS_COMPLETED);
        $this->transfer('TRF-SEP', '2026-09-10', WarehouseTransfer::STATUS_COMPLETED);

        $this->get(route('transfers.index', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))
            ->assertOk()
            ->assertSee('TRF-MAR')
            ->assertDontSee('TRF-SEP');
    }

    // -----------------------------------------------------------------------
    // Combined filters, empty results, and clearing
    // -----------------------------------------------------------------------

    public function test_products_combine_search_category_and_status(): void
    {
        $wanted   = Category::create(['name' => 'Phones', 'active' => true]);
        $unwanted = Category::create(['name' => 'Tablets', 'active' => true]);

        Product::create(['category_id' => $wanted->id, 'name' => 'iPhone Active', 'sku' => 'P-1', 'price' => 1, 'minimum_stock' => 0, 'active' => true]);
        Product::create(['category_id' => $wanted->id, 'name' => 'iPhone Retired', 'sku' => 'P-2', 'price' => 1, 'minimum_stock' => 0, 'active' => false]);
        Product::create(['category_id' => $unwanted->id, 'name' => 'iPhone Tablet', 'sku' => 'P-3', 'price' => 1, 'minimum_stock' => 0, 'active' => true]);

        $this->get(route('products.index', ['search' => 'iPhone', 'category_id' => $wanted->id, 'active' => '1']))
            ->assertOk()
            ->assertSee('iPhone Active')
            ->assertDontSee('iPhone Retired')
            ->assertDontSee('iPhone Tablet');
    }

    public function test_stock_movements_filter_by_type_and_date_range(): void
    {
        $product   = Product::create([
            'category_id'   => Category::create(['name' => 'Misc', 'active' => true])->id,
            'name' => 'Widget', 'sku' => 'W-1', 'price' => 1, 'minimum_stock' => 0, 'active' => true,
        ]);
        $warehouse = Warehouse::create(['name' => 'Main', 'active' => true]);

        StockMovement::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => StockMovement::TYPE_IN, 'quantity' => 10]);
        StockMovement::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => StockMovement::TYPE_OUT, 'quantity' => 4]);

        $this->get(route('stock-movements.index', [
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => StockMovement::TYPE_OUT,
        ]))->assertOk()->assertSee('-4');
    }

    public function test_a_search_matching_nothing_renders_an_empty_state(): void
    {
        Category::create(['name' => 'Electronics', 'active' => true]);

        $this->get(route('categories.index', ['search' => 'nothing-matches-this']))
            ->assertOk()
            ->assertDontSee('Electronics');
    }

    public function test_clearing_filters_restores_the_full_list(): void
    {
        Supplier::create(['name' => 'Visible Supplier', 'active' => false]);

        $this->get(route('suppliers.index', ['active' => '1']))
            ->assertOk()
            ->assertDontSee('Visible Supplier');

        // No query string = no filters, matching the Clear link's target.
        $this->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('Visible Supplier');
    }

    public function test_warehouse_list_reports_real_held_stock_not_zero(): void
    {
        // These two columns were hardcoded "0" in the view.
        $warehouse = Warehouse::create(['name' => 'Counted Depot', 'active' => true]);
        $category  = Category::create(['name' => 'Misc', 'active' => true]);

        $held = Product::create([
            'category_id' => $category->id, 'name' => 'Held Widget', 'sku' => 'H-1',
            'price' => 1, 'minimum_stock' => 0, 'active' => true,
        ]);
        $emptied = Product::create([
            'category_id' => $category->id, 'name' => 'Emptied Widget', 'sku' => 'E-1',
            'price' => 1, 'minimum_stock' => 0, 'active' => true,
        ]);

        StockMovement::create(['product_id' => $held->id, 'warehouse_id' => $warehouse->id, 'type' => StockMovement::TYPE_IN, 'quantity' => 30]);

        // Fully shipped out, so it must not count towards "products held".
        StockMovement::create(['product_id' => $emptied->id, 'warehouse_id' => $warehouse->id, 'type' => StockMovement::TYPE_IN, 'quantity' => 5]);
        StockMovement::create(['product_id' => $emptied->id, 'warehouse_id' => $warehouse->id, 'type' => StockMovement::TYPE_OUT, 'quantity' => 5]);

        $stock = $this->get(route('warehouses.index'))->assertOk()->viewData('warehouseStock');

        $this->assertSame(1, $stock[$warehouse->id]['products']);
        $this->assertSame(30, $stock[$warehouse->id]['quantity']);
    }
}
