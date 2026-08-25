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

class InventoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    private function product(string $name = 'iPhone 15', string $sku = 'PRD-1001', bool $active = true): Product
    {
        $category = Category::firstOrCreate(['name' => 'Electronics'], ['active' => true]);

        return Product::create([
            'category_id'   => $category->id,
            'name'          => $name,
            'sku'           => $sku,
            'price'         => 999,
            'minimum_stock' => 5,
            'active'        => $active,
        ]);
    }

    private function warehouse(string $name = 'Main Warehouse'): Warehouse
    {
        return Warehouse::create(['name' => $name, 'active' => true]);
    }

    // -----------------------------------------------------------------------
    // Category deletes no longer destroy products
    // -----------------------------------------------------------------------

    public function test_category_with_products_cannot_be_deleted_and_products_survive(): void
    {
        // This used to CASCADE: the category delete succeeded, reported success,
        // and silently took every product in it along.
        $product  = $this->product();
        $category = Category::find($product->category_id);

        $this->from(route('categories.index'))
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_empty_category_can_still_be_deleted(): void
    {
        $category = Category::create(['name' => 'Unused', 'active' => true]);

        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_the_database_itself_refuses_to_cascade_a_category_delete(): void
    {
        // Defence in depth: even bypassing the controller, the FK must hold.
        $product  = $this->product();
        $category = Category::find($product->category_id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $category->delete();
    }

    // -----------------------------------------------------------------------
    // A movement resolves to the document that actually created it
    // -----------------------------------------------------------------------

    public function test_movement_reference_resolves_to_the_correct_document_type(): void
    {
        // The bug: reference_id alone is ambiguous. With Stock In #1 and
        // Stock Out #1 both present, a belongsTo on reference_id returned the
        // wrong document. These three ids collide deliberately.
        $product   = $this->product();
        $warehouse = $this->warehouse();

        $stockIn = StockIn::create([
            'supplier_id'      => Supplier::create(['name' => 'Acme', 'active' => true])->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'GRN-1001',
            'receipt_date'     => '2026-08-01',
            'status'           => 'completed',
        ]);

        $stockOut = StockOut::create([
            'distributor_id'   => Distributor::create(['name' => 'Nile', 'active' => true])->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'ISS-1001',
            'issue_date'       => '2026-08-02',
            'status'           => 'completed',
        ]);

        $transfer = WarehouseTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id'   => $this->warehouse('Second Warehouse')->id,
            'reference_number'  => 'TRF-1001',
            'transfer_date'     => '2026-08-03',
            'status'            => WarehouseTransfer::STATUS_COMPLETED,
        ]);

        $this->assertSame(1, $stockIn->id);
        $this->assertSame(1, $stockOut->id);
        $this->assertSame(1, $transfer->id);

        $movements = collect([
            StockMovement::REFERENCE_STOCK_IN  => $stockIn,
            StockMovement::REFERENCE_STOCK_OUT => $stockOut,
            StockMovement::REFERENCE_TRANSFER  => $transfer,
        ])->map(function ($document, $referenceType) use ($product, $warehouse) {
            return StockMovement::create([
                'product_id'     => $product->id,
                'warehouse_id'   => $warehouse->id,
                'type'           => StockMovement::TYPE_IN,
                'quantity'       => 5,
                'reference_type' => $referenceType,
                'reference_id'   => $document->id,
            ]);
        });

        $this->assertInstanceOf(StockIn::class, $movements[StockMovement::REFERENCE_STOCK_IN]->reference);
        $this->assertInstanceOf(StockOut::class, $movements[StockMovement::REFERENCE_STOCK_OUT]->reference);
        $this->assertInstanceOf(WarehouseTransfer::class, $movements[StockMovement::REFERENCE_TRANSFER]->reference);

        $this->assertSame('GRN-1001', $movements[StockMovement::REFERENCE_STOCK_IN]->reference->reference_number);
        $this->assertSame('ISS-1001', $movements[StockMovement::REFERENCE_STOCK_OUT]->reference->reference_number);
        $this->assertSame('TRF-1001', $movements[StockMovement::REFERENCE_TRANSFER]->reference->reference_number);
    }

    public function test_movement_without_a_reference_resolves_to_null(): void
    {
        $movement = StockMovement::create([
            'product_id'   => $this->product()->id,
            'warehouse_id' => $this->warehouse()->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 5,
        ]);

        $this->assertNull($movement->reference);
    }

    // -----------------------------------------------------------------------
    // Inactive products keep their stock visible
    // -----------------------------------------------------------------------

    public function test_stock_report_still_shows_stock_held_by_an_inactive_product(): void
    {
        // Deactivating a product does not empty the shelf. This stock used to
        // vanish from every report while currentStock() still counted it.
        $retired   = $this->product('Retired Widget', 'PRD-RET', false);
        $warehouse = $this->warehouse();

        StockMovement::create([
            'product_id'   => $retired->id,
            'warehouse_id' => $warehouse->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 40,
        ]);

        $this->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Retired Widget')
            ->assertSee('Inactive');

        // The ledger rule and the summary query must agree on what exists.
        $this->assertSame(40, StockMovement::currentStock($retired->id, $warehouse->id));

        $row = StockMovement::currentStockRows()->get()
            ->firstWhere('product_id', $retired->id);

        $this->assertNotNull($row, 'currentStockRows() must not hide an inactive product holding stock.');
        $this->assertSame(40, (int) $row->current_stock);
    }

    public function test_low_stock_views_ignore_inactive_products(): void
    {
        // Retired products should not raise alerts, so the low-stock views
        // filter on product_active themselves.
        $retired   = $this->product('Retired Widget', 'PRD-RET', false);
        $active    = $this->product('Live Widget', 'PRD-LIVE');
        $warehouse = $this->warehouse();

        foreach ([$retired, $active] as $product) {
            StockMovement::create([
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => StockMovement::TYPE_IN,
                'quantity'     => 1, // below minimum_stock of 5
            ]);
        }

        $this->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSee('Live Widget')
            ->assertDontSee('Retired Widget');

        // The dashboard is checked through its view data, not the rendered page:
        // the retired product legitimately appears in "Recent Stock Movements",
        // so only the low-stock panel is meaningful here.
        $lowStockNames = collect($this->get(route('dashboard'))->assertOk()->viewData('lowStockProducts'))
            ->pluck('product_name');

        $this->assertContains('Live Widget', $lowStockNames->all());
        $this->assertNotContains('Retired Widget', $lowStockNames->all());
    }
}
