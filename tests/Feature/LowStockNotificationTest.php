<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\LowStockNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Is the low-stock alert arithmetically right?
 *
 * NotificationPermissionTest owns the question of WHO is alerted. This file owns
 * WHAT they are alerted about: the threshold comparison, the per-warehouse
 * grouping, the ordering, and the fact that the bell, the dashboard and the
 * low-stock report all read the same ledger and therefore cannot disagree.
 *
 * A low-stock alert is derived, never stored — so every case here is set up by
 * writing movements and then asking, exactly as production does.
 */
class LowStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $recipient;
    private Category $category;
    private Warehouse $main;
    private Warehouse $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipient = User::factory()->create([
            'role_id'               => Role::create(['name' => 'Admin'])->id,
            'phone'                 => '+201000000001',
            'receive_notifications' => true,
        ]);

        $this->category = Category::create(['name' => 'Consumables', 'active' => true]);
        $this->main     = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $this->branch   = Warehouse::create(['name' => 'North Branch', 'active' => true]);

        $this->actingAs($this->recipient);
    }

    private function product(string $sku, int $minimumStock, bool $active = true): Product
    {
        return Product::create([
            'category_id'   => $this->category->id,
            'name'          => "Product {$sku}",
            'sku'           => $sku,
            'price'         => 10,
            'minimum_stock' => $minimumStock,
            'active'        => $active,
        ]);
    }

    private function move(Product $product, Warehouse $warehouse, string $type, int $quantity): void
    {
        StockMovement::create([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => $type,
            'quantity'     => $quantity,
        ]);
    }

    /** Put an exact quantity on the shelf in one movement. */
    private function stock(Product $product, Warehouse $warehouse, int $quantity): void
    {
        $this->move($product, $warehouse, StockMovement::TYPE_IN, $quantity);
    }

    /** @return array<int, string> the product names currently being alerted on */
    private function alertedNames(): array
    {
        return LowStockNotifier::for($this->recipient)->pluck('product_name')->all();
    }

    // -----------------------------------------------------------------------
    // The threshold itself
    // -----------------------------------------------------------------------

    public function test_stock_equal_to_the_minimum_is_low(): void
    {
        // The rule is "at or below", not "below": a product sitting exactly on
        // its reorder point is the moment to reorder, not one unit later.
        $product = $this->product('EQ-1', 10);
        $this->stock($product, $this->main, 10);

        $this->assertSame(1, LowStockNotifier::countFor($this->recipient));
        $this->assertContains('Product EQ-1', $this->alertedNames());
    }

    public function test_one_unit_above_the_minimum_is_not_low(): void
    {
        $product = $this->product('ABOVE-1', 10);
        $this->stock($product, $this->main, 11);

        $this->assertSame(0, LowStockNotifier::countFor($this->recipient));
    }

    public function test_one_unit_below_the_minimum_is_low(): void
    {
        $product = $this->product('BELOW-1', 10);
        $this->stock($product, $this->main, 9);

        $this->assertSame(1, LowStockNotifier::countFor($this->recipient));
    }

    public function test_stock_that_has_run_out_is_low(): void
    {
        $product = $this->product('OUT-1', 10);
        $this->stock($product, $this->main, 4);
        $this->move($product, $this->main, StockMovement::TYPE_OUT, 4);

        $this->assertSame(0, StockMovement::currentStock($product->id, $this->main->id));
        $this->assertSame(1, LowStockNotifier::countFor($this->recipient));
    }

    public function test_a_product_with_no_reorder_point_never_raises_an_alert(): void
    {
        // minimum_stock 0 means "we do not track a reorder point for this", so
        // even zero on the shelf is not an alert. Without the `> 0` guard every
        // untracked product would alert the moment it emptied.
        $product = $this->product('NOMIN-1', 0);
        $this->stock($product, $this->main, 3);
        $this->move($product, $this->main, StockMovement::TYPE_OUT, 3);

        $this->assertSame(0, StockMovement::currentStock($product->id, $this->main->id));
        $this->assertSame(0, LowStockNotifier::countFor($this->recipient));
    }

    public function test_a_retired_product_stops_raising_alerts_but_keeps_its_stock(): void
    {
        $product = $this->product('RETIRED-1', 10);
        $this->stock($product, $this->main, 2);

        $this->assertSame(1, LowStockNotifier::countFor($this->recipient));

        $product->update(['active' => false]);

        $this->assertSame(0, LowStockNotifier::countFor($this->recipient), 'A retired product must not keep nagging.');
        $this->assertSame(2, StockMovement::currentStock($product->id, $this->main->id), 'Its units are still on the shelf.');
    }

    // -----------------------------------------------------------------------
    // Grouping: the alert is per warehouse, not per company
    // -----------------------------------------------------------------------

    public function test_each_warehouse_is_judged_against_the_minimum_separately(): void
    {
        // 5 + 5 = 10 company-wide, which reaches the minimum of 8 — but neither
        // warehouse can fill an order of 8 on its own, so BOTH are alerted.
        // minimum_stock is read as a per-location reorder point, which is what
        // makes the alert actionable: it tells you where to send stock.
        $product = $this->product('SPLIT-1', 8);
        $this->stock($product, $this->main, 5);
        $this->stock($product, $this->branch, 5);

        $this->assertSame(2, LowStockNotifier::countFor($this->recipient));

        $rows = LowStockNotifier::for($this->recipient);

        $this->assertEqualsCanonicalizing(
            ['Main Warehouse', 'North Branch'],
            $rows->pluck('warehouse_name')->all()
        );
    }

    public function test_a_healthy_warehouse_is_not_listed_alongside_a_depleted_one(): void
    {
        $product = $this->product('MIXED-1', 8);
        $this->stock($product, $this->main, 50);
        $this->stock($product, $this->branch, 3);

        $rows = LowStockNotifier::for($this->recipient);

        $this->assertCount(1, $rows);
        $this->assertSame('North Branch', $rows->first()->warehouse_name);
        $this->assertSame(3, (int) $rows->first()->current_stock);
    }

    public function test_a_product_that_has_never_moved_raises_no_alert(): void
    {
        // Documented consequence of deriving alerts from the ledger: with no
        // movements there is no (product, warehouse) row to compare, so a
        // product that was created but never received is silent. It cannot be
        // low "at" a warehouse it has never been to. The Products page is where
        // a never-received product shows as zero.
        $this->product('NEVER-1', 25);

        $this->assertSame(0, LowStockNotifier::countFor($this->recipient));
    }

    // -----------------------------------------------------------------------
    // Ordering and limit
    // -----------------------------------------------------------------------

    public function test_the_worst_shortfall_is_listed_first(): void
    {
        // Ordered by how far below the minimum each row is, not by stock level:
        // 1 unit against a minimum of 4 is less urgent than 10 against 100.
        $mild = $this->product('MILD-1', 4);
        $this->stock($mild, $this->main, 3);          // shortfall 1

        $severe = $this->product('SEVERE-1', 100);
        $this->stock($severe, $this->main, 10);       // shortfall 90

        $middling = $this->product('MID-1', 30);
        $this->stock($middling, $this->main, 10);     // shortfall 20

        $this->assertSame(
            ['Product SEVERE-1', 'Product MID-1', 'Product MILD-1'],
            $this->alertedNames()
        );
    }

    public function test_the_limit_shortens_the_list_without_changing_the_count(): void
    {
        // The bell shows a handful of rows but the badge must still say how many
        // there really are, or the number would understate the problem.
        foreach (range(1, 12) as $n) {
            $product = $this->product("MANY-{$n}", 10);
            $this->stock($product, $this->main, 1);
        }

        $this->assertCount(10, LowStockNotifier::for($this->recipient), 'Default limit is 10 rows.');
        $this->assertCount(3, LowStockNotifier::for($this->recipient, 3));
        $this->assertSame(12, LowStockNotifier::countFor($this->recipient), 'The count is not capped by the limit.');
    }

    // -----------------------------------------------------------------------
    // The ledger drives the alert
    // -----------------------------------------------------------------------

    public function test_issuing_stock_creates_the_alert_and_receiving_stock_clears_it(): void
    {
        $supplier    = \App\Models\Supplier::create(['name' => 'ReStock Co', 'active' => true]);
        $distributor = \App\Models\Distributor::create(['name' => 'City Dist', 'active' => true]);

        $product = $this->product('CYCLE-1', 10);
        $this->stock($product, $this->main, 40);

        $this->assertSame(0, LowStockNotifier::countFor($this->recipient), 'Well stocked to begin with.');

        // Issue 32, leaving 8 against a minimum of 10.
        $this->post(route('stock-out.store'), [
            'distributor_id'   => $distributor->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'SO-CYCLE',
            'issue_date'       => today()->toDateString(),
            'products'         => [$product->id],
            'quantities'       => [32],
        ])->assertSessionHasNoErrors();

        $this->assertSame(8, StockMovement::currentStock($product->id, $this->main->id));
        $this->assertSame(1, LowStockNotifier::countFor($this->recipient), 'Issuing stock must raise the alert.');

        // Receive 5 more, reaching 13 — clear of the minimum again.
        $this->post(route('stock-in.store'), [
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'SI-CYCLE',
            'receipt_date'     => today()->toDateString(),
            'products'         => [$product->id],
            'quantities'       => [5],
            'unit_costs'       => [10],
        ])->assertSessionHasNoErrors();

        $this->assertSame(13, StockMovement::currentStock($product->id, $this->main->id));
        $this->assertSame(0, LowStockNotifier::countFor($this->recipient), 'Restocking must clear the alert.');
    }

    public function test_a_transfer_moves_the_alert_between_warehouses(): void
    {
        // A transfer neither creates nor destroys stock, so an alert it causes at
        // the source must be matched by one it clears at the destination.
        $product = $this->product('MOVE-1', 10);
        $this->stock($product, $this->main, 30);
        $this->stock($product, $this->branch, 2);

        $before = LowStockNotifier::for($this->recipient);
        $this->assertSame(['North Branch'], $before->pluck('warehouse_name')->all());

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $this->main->id,
            'to_warehouse_id'   => $this->branch->id,
            'reference_number'  => 'TR-MOVE',
            'transfer_date'     => today()->toDateString(),
            'products'          => [$product->id],
            'quantities'        => [25],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, StockMovement::currentStock($product->id, $this->main->id));
        $this->assertSame(27, StockMovement::currentStock($product->id, $this->branch->id));

        $after = LowStockNotifier::for($this->recipient);

        $this->assertSame(['Main Warehouse'], $after->pluck('warehouse_name')->all(), 'The alert must follow the stock.');
    }

    public function test_a_downward_adjustment_raises_the_alert(): void
    {
        $product = $this->product('SHRINK-1', 10);
        $this->stock($product, $this->main, 12);

        $this->assertSame(0, LowStockNotifier::countFor($this->recipient));

        $this->post(route('adjustments.store'), [
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'ADJ-SHRINK',
            'adjustment_date'  => today()->toDateString(),
            'reason'           => 'damage',
            'products'         => [$product->id],
            'directions'       => ['decrease'],
            'quantities'       => [4],
        ])->assertSessionHasNoErrors();

        $this->assertSame(8, StockMovement::currentStock($product->id, $this->main->id));
        $this->assertSame(1, LowStockNotifier::countFor($this->recipient));
    }

    // -----------------------------------------------------------------------
    // Every screen tells the same story
    // -----------------------------------------------------------------------

    public function test_the_bell_the_dashboard_and_the_low_stock_report_agree(): void
    {
        $low = $this->product('AGREE-LOW', 20);
        $this->stock($low, $this->main, 4);

        $healthy = $this->product('AGREE-OK', 5);
        $this->stock($healthy, $this->main, 500);

        $this->assertSame(1, LowStockNotifier::countFor($this->recipient));

        // assertDontSee on the healthy product would be meaningless here: every
        // active product is listed in the report's own filter dropdown. The
        // chart payload is built from the result rows, so it shows what the
        // report actually selected.
        $this->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSee('Product AGREE-LOW')
            ->assertSee('"labels":["Product AGREE-LOW"]', false);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Product AGREE-LOW');
    }

    public function test_the_low_stock_report_totals_the_shortfall_across_rows(): void
    {
        // Shortfall is minimum - current per row, summed. 20-4 plus 30-10 = 36.
        $first = $this->product('SHORT-1', 20);
        $this->stock($first, $this->main, 4);

        $second = $this->product('SHORT-2', 30);
        $this->stock($second, $this->main, 10);

        $this->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSee('Total Shortfall')
            ->assertSee('36');
    }

    public function test_the_low_stock_report_can_be_narrowed_to_one_warehouse(): void
    {
        // Two products, one low in each warehouse, so the filter's effect shows
        // up in the selected rows rather than in the warehouse dropdown — which
        // always lists every warehouse regardless of the filter.
        $atMain = $this->product('FILTER-MAIN', 10);
        $this->stock($atMain, $this->main, 2);

        $atBranch = $this->product('FILTER-BRANCH', 10);
        $this->stock($atBranch, $this->branch, 3);

        $this->assertSame(2, LowStockNotifier::countFor($this->recipient));

        $this->get(route('reports.low-stock', ['warehouse_id' => $this->branch->id]))
            ->assertOk()
            ->assertSee('"labels":["Product FILTER-BRANCH"]', false);
    }
}
