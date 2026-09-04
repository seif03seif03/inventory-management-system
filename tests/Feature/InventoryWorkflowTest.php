<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\InventoryAdjustment;
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
 * Several documents in a row, against the same stock.
 *
 * Every document type has its own test file covering its validation, its
 * permissions and the movements it writes. This file covers what none of them
 * can see on their own: whether a receipt, an issue, a transfer and an
 * adjustment still add up when they are applied one after another to the same
 * product in the same warehouses.
 *
 * Nothing stores current stock — it is SUM(IN) - SUM(OUT) over the ledger — so
 * the risk a multi-step workflow carries is arithmetic drift: a step that reads
 * a stale figure, double-counts a transfer, or writes a movement against the
 * wrong warehouse. Each test below therefore checks stock after every step, not
 * only at the end, so a failure names the step that broke it.
 */
class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Supplier $supplier;
    private Distributor $distributor;
    private Warehouse $a;
    private Warehouse $b;
    private Warehouse $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($this->admin);

        $this->category    = Category::create(['name' => 'Hardware', 'active' => true]);
        $this->supplier    = Supplier::create(['name' => 'Acme Supply', 'active' => true]);
        $this->distributor = Distributor::create(['name' => 'Metro Retail', 'active' => true]);

        $this->a = Warehouse::create(['name' => 'Warehouse A', 'active' => true]);
        $this->b = Warehouse::create(['name' => 'Warehouse B', 'active' => true]);
        $this->c = Warehouse::create(['name' => 'Warehouse C', 'active' => true]);
    }

    private function product(string $name, string $sku, int $minimumStock = 0): Product
    {
        return Product::create([
            'category_id'   => $this->category->id,
            'name'          => $name,
            'sku'           => $sku,
            'price'         => 25,
            'minimum_stock' => $minimumStock,
            'active'        => true,
        ]);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $rows  product and quantity
     */
    private function receive(Warehouse $warehouse, array $rows, string $reference)
    {
        return $this->post(route('stock-in.store'), [
            'supplier_id'      => $this->supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => $reference,
            'receipt_date'     => today()->toDateString(),
            'products'         => array_map(fn ($row) => $row[0]->id, $rows),
            'quantities'       => array_map(fn ($row) => $row[1], $rows),
            'unit_costs'       => array_map(fn () => 10, $rows),
        ]);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $rows
     */
    private function issue(Warehouse $warehouse, array $rows, string $reference)
    {
        return $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => $reference,
            'issue_date'       => today()->toDateString(),
            'products'         => array_map(fn ($row) => $row[0]->id, $rows),
            'quantities'       => array_map(fn ($row) => $row[1], $rows),
        ]);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $rows
     */
    private function transfer(Warehouse $from, Warehouse $to, array $rows, string $reference)
    {
        return $this->post(route('transfers.store'), [
            'from_warehouse_id' => $from->id,
            'to_warehouse_id'   => $to->id,
            'reference_number'  => $reference,
            'transfer_date'     => today()->toDateString(),
            'products'          => array_map(fn ($row) => $row[0]->id, $rows),
            'quantities'        => array_map(fn ($row) => $row[1], $rows),
        ]);
    }

    /**
     * @param  array<int, array{0: Product, 1: string, 2: int}>  $rows  product, direction, quantity
     */
    private function adjust(Warehouse $warehouse, array $rows, string $reference, string $reason = 'recount')
    {
        return $this->post(route('adjustments.store'), [
            'warehouse_id'     => $warehouse->id,
            'reference_number' => $reference,
            'adjustment_date'  => today()->toDateString(),
            'reason'           => $reason,
            'products'         => array_map(fn ($row) => $row[0]->id, $rows),
            'directions'       => array_map(fn ($row) => $row[1], $rows),
            'quantities'       => array_map(fn ($row) => $row[2], $rows),
        ]);
    }

    private function stock(Product $product, Warehouse $warehouse): int
    {
        return StockMovement::currentStock($product->id, $warehouse->id);
    }

    /**
     * Every movement the workflow wrote for one product, oldest first, as
     * comparable tuples.
     *
     * @return array<int, array{string, string, int}>  warehouse, type, quantity
     */
    private function ledgerFor(Product $product): array
    {
        return StockMovement::where('product_id', $product->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($movement) => [
                $movement->warehouse->name,
                $movement->type,
                (int) $movement->quantity,
            ])
            ->all();
    }

    // -----------------------------------------------------------------------
    // The whole lifecycle, one step at a time
    // -----------------------------------------------------------------------

    public function test_the_full_lifecycle_of_one_product_across_two_warehouses(): void
    {
        $widget = $this->product('Widget', 'WID-1');

        $this->assertSame(0, $this->stock($widget, $this->a), 'A product starts at zero everywhere.');
        $this->assertSame(0, $this->stock($widget, $this->b));

        // Receive 100 at A.
        $this->receive($this->a, [[$widget, 100]], 'SI-0001')->assertRedirect();

        $this->assertSame(100, $this->stock($widget, $this->a));
        $this->assertSame(0, $this->stock($widget, $this->b), 'A receipt at A must not touch B.');

        // Transfer 40 of them to B.
        $this->transfer($this->a, $this->b, [[$widget, 40]], 'TR-0001')->assertRedirect();

        $this->assertSame(60, $this->stock($widget, $this->a));
        $this->assertSame(40, $this->stock($widget, $this->b));

        // Write 10 off at B.
        $this->adjust($this->b, [[$widget, 'decrease', 10]], 'ADJ-0001', 'damage')->assertRedirect();

        $this->assertSame(60, $this->stock($widget, $this->a), 'An adjustment at B must not touch A.');
        $this->assertSame(30, $this->stock($widget, $this->b));

        // Issue 50 from A.
        $this->issue($this->a, [[$widget, 50]], 'SO-0001')->assertRedirect();

        $this->assertSame(10, $this->stock($widget, $this->a));
        $this->assertSame(30, $this->stock($widget, $this->b));
        $this->assertSame(40, StockMovement::totalStockAllWarehouses($widget->id));

        // The ledger, read back in the order it was written. Five rows for four
        // documents, because a transfer writes both an OUT and an IN — and every
        // quantity is positive, with the type carrying the direction.
        $this->assertSame([
            ['Warehouse A', 'IN',  100],   // receipt
            ['Warehouse A', 'OUT', 40],    // transfer, source
            ['Warehouse B', 'IN',  40],    // transfer, destination
            ['Warehouse B', 'OUT', 10],    // adjustment
            ['Warehouse A', 'OUT', 50],    // issue
        ], $this->ledgerFor($widget));
    }

    public function test_each_step_of_the_workflow_leaves_a_movement_pointing_at_its_document(): void
    {
        // Without the back-reference the ledger is a pile of anonymous numbers:
        // "60 units left" with no way to answer "left after what?".
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 100]], 'SI-0002');
        $this->transfer($this->a, $this->b, [[$widget, 40]], 'TR-0002');
        $this->adjust($this->b, [[$widget, 'decrease', 10]], 'ADJ-0002', 'damage');
        $this->issue($this->a, [[$widget, 50]], 'SO-0002');

        $movements = StockMovement::orderBy('id')->get();

        $this->assertCount(5, $movements);

        foreach ($movements as $movement) {
            $this->assertNotNull($movement->reference_type, 'A workflow movement with no document is untraceable.');
            $this->assertNotNull($movement->reference_id);
        }

        $this->assertSame([
            StockMovement::REFERENCE_STOCK_IN,
            StockMovement::REFERENCE_TRANSFER,
            StockMovement::REFERENCE_TRANSFER,
            StockMovement::REFERENCE_ADJUSTMENT,
            StockMovement::REFERENCE_STOCK_OUT,
        ], $movements->pluck('reference_type')->all());

        // Both halves of the transfer belong to the SAME document, which is what
        // makes a transfer reversible as one unit.
        $this->assertSame(
            $movements[1]->reference_id,
            $movements[2]->reference_id,
            'The two sides of a transfer must reference one transfer record.'
        );

        // And each reference actually resolves.
        $this->assertSame('SI-0002', StockIn::find($movements[0]->reference_id)->reference_number);
        $this->assertSame('TR-0002', WarehouseTransfer::find($movements[1]->reference_id)->reference_number);
        $this->assertSame('ADJ-0002', InventoryAdjustment::find($movements[3]->reference_id)->reference_number);
        $this->assertSame('SO-0002', StockOut::find($movements[4]->reference_id)->reference_number);
    }

    // -----------------------------------------------------------------------
    // Several products, several warehouses
    // -----------------------------------------------------------------------

    public function test_a_multi_row_document_moves_every_product_on_it_independently(): void
    {
        // One receipt of three products, then one issue touching two of them.
        // The failure this guards against is a document that applies the first
        // row's quantity to every row, which a single-product test cannot see.
        $widget  = $this->product('Widget', 'WID-1');
        $gasket  = $this->product('Gasket', 'GAS-1');
        $bearing = $this->product('Bearing', 'BEA-1');

        $this->receive($this->a, [
            [$widget, 100],
            [$gasket, 50],
            [$bearing, 7],
        ], 'SI-MULTI')->assertRedirect();

        $this->assertSame(100, $this->stock($widget, $this->a));
        $this->assertSame(50, $this->stock($gasket, $this->a));
        $this->assertSame(7, $this->stock($bearing, $this->a));

        $this->issue($this->a, [
            [$widget, 30],
            [$bearing, 7],
        ], 'SO-MULTI')->assertRedirect();

        $this->assertSame(70, $this->stock($widget, $this->a));
        $this->assertSame(50, $this->stock($gasket, $this->a), 'A product left off the issue must not move.');
        $this->assertSame(0, $this->stock($bearing, $this->a));

        $this->assertSame(5, StockMovement::count(), 'Three receipt rows and two issue rows.');
    }

    public function test_the_same_product_twice_on_one_document_is_applied_twice(): void
    {
        // The forms allow two rows for the same product, and the ledger must show
        // both. The stock check nets them first, so 60 + 60 against 100 is
        // refused as one request for 120 rather than passing twice at 60.
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 100]], 'SI-DUP');

        $this->issue($this->a, [[$widget, 60], [$widget, 60]], 'SO-DUP-BAD')
            ->assertSessionHas('stockErrors');

        $this->assertSame(100, $this->stock($widget, $this->a), 'Nothing may move when the combined rows overdraw.');

        $this->issue($this->a, [[$widget, 60], [$widget, 30]], 'SO-DUP-OK')
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $this->stock($widget, $this->a), '100 - (60 + 30).');
        $this->assertSame(2, StockOut::first()->items()->count(), 'Both rows are kept on the document.');
    }

    public function test_stock_chains_through_three_warehouses(): void
    {
        // Each transfer reads the stock the previous one produced, so a stale
        // read anywhere in the chain shows up as a refusal or a wrong total.
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 60]], 'SI-CHAIN')->assertRedirect();

        $this->transfer($this->a, $this->b, [[$widget, 60]], 'TR-AB')->assertSessionHasNoErrors();
        $this->assertSame(0, $this->stock($widget, $this->a));
        $this->assertSame(60, $this->stock($widget, $this->b));

        $this->transfer($this->b, $this->c, [[$widget, 45]], 'TR-BC')->assertSessionHasNoErrors();
        $this->assertSame(15, $this->stock($widget, $this->b));
        $this->assertSame(45, $this->stock($widget, $this->c));

        $this->transfer($this->c, $this->a, [[$widget, 45]], 'TR-CA')->assertSessionHasNoErrors();
        $this->assertSame(45, $this->stock($widget, $this->a));
        $this->assertSame(0, $this->stock($widget, $this->c));

        $this->issue($this->c, [[$widget, 1]], 'SO-EMPTY')->assertSessionHas('stockErrors');

        $this->assertSame(60, StockMovement::totalStockAllWarehouses($widget->id), 'Moving stock around never creates or destroys it.');
    }

    public function test_a_transfer_never_changes_the_company_total_but_the_other_documents_do(): void
    {
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 100]], 'SI-TOTAL');
        $this->assertSame(100, StockMovement::totalStockAllWarehouses($widget->id));

        $this->transfer($this->a, $this->b, [[$widget, 100]], 'TR-TOTAL');
        $this->assertSame(100, StockMovement::totalStockAllWarehouses($widget->id), 'A transfer only relocates.');

        $this->adjust($this->b, [[$widget, 'decrease', 25]], 'ADJ-TOTAL', 'loss');
        $this->assertSame(75, StockMovement::totalStockAllWarehouses($widget->id));

        $this->adjust($this->b, [[$widget, 'increase', 5]], 'ADJ-TOTAL-UP', 'recount');
        $this->assertSame(80, StockMovement::totalStockAllWarehouses($widget->id));

        $this->issue($this->b, [[$widget, 80]], 'SO-TOTAL');
        $this->assertSame(0, StockMovement::totalStockAllWarehouses($widget->id));
    }

    public function test_an_upward_adjustment_creates_stock_that_can_then_be_issued(): void
    {
        // A recount that finds more than the ledger knew about is the only way
        // stock appears without a receipt, and what it creates has to behave like
        // any other stock afterwards.
        $widget = $this->product('Widget', 'WID-1');

        $this->adjust($this->a, [[$widget, 'increase', 12]], 'ADJ-FOUND', 'recount')->assertRedirect();

        $this->assertSame(12, $this->stock($widget, $this->a));

        $this->transfer($this->a, $this->b, [[$widget, 12]], 'TR-FOUND')->assertSessionHasNoErrors();
        $this->issue($this->b, [[$widget, 12]], 'SO-FOUND')->assertSessionHasNoErrors();

        $this->assertSame(0, StockMovement::totalStockAllWarehouses($widget->id));
    }

    // -----------------------------------------------------------------------
    // The order of the steps, and steps that fail
    // -----------------------------------------------------------------------

    public function test_the_same_operations_in_a_different_order_reach_the_same_stock(): void
    {
        // Receive, transfer, then adjust — against receive, adjust, then
        // transfer. Same figures in, so the same figures out; if any step read a
        // cached total instead of the ledger the two orders would diverge.
        $first  = $this->product('Widget', 'WID-1');
        $second = $this->product('Gasket', 'GAS-1');

        $this->receive($this->a, [[$first, 80]], 'SI-ORDER-1');
        $this->transfer($this->a, $this->b, [[$first, 30]], 'TR-ORDER-1');
        $this->adjust($this->a, [[$first, 'decrease', 20]], 'ADJ-ORDER-1', 'damage');

        $this->receive($this->a, [[$second, 80]], 'SI-ORDER-2');
        $this->adjust($this->a, [[$second, 'decrease', 20]], 'ADJ-ORDER-2', 'damage');
        $this->transfer($this->a, $this->b, [[$second, 30]], 'TR-ORDER-2');

        $this->assertSame(30, $this->stock($first, $this->a));
        $this->assertSame(30, $this->stock($second, $this->a));

        $this->assertSame(30, $this->stock($first, $this->b));
        $this->assertSame(30, $this->stock($second, $this->b));
    }

    public function test_a_refused_step_leaves_the_workflow_exactly_where_it_was(): void
    {
        // A rejected step must be a no-op, not a partial one — otherwise the next
        // step in the workflow starts from a figure nobody chose.
        $widget = $this->product('Widget', 'WID-1');
        $gasket = $this->product('Gasket', 'GAS-1');

        $this->receive($this->a, [[$widget, 20], [$gasket, 20]], 'SI-REFUSE');

        $movementsBefore = StockMovement::count();

        // One row of two is impossible, so the whole document is refused.
        $this->issue($this->a, [[$widget, 5], [$gasket, 999]], 'SO-REFUSED')
            ->assertSessionHas('stockErrors');

        $this->assertSame(20, $this->stock($widget, $this->a), 'The valid row must not have been applied on its own.');
        $this->assertSame(20, $this->stock($gasket, $this->a));
        $this->assertSame($movementsBefore, StockMovement::count());
        $this->assertDatabaseMissing('stock_outs', ['reference_number' => 'SO-REFUSED']);

        // A transfer out of an empty warehouse, refused the same way.
        $this->transfer($this->b, $this->a, [[$widget, 1]], 'TR-REFUSED')
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('warehouse_transfers', ['reference_number' => 'TR-REFUSED']);
        $this->assertSame($movementsBefore, StockMovement::count());

        // The workflow carries on from the untouched figures.
        $this->issue($this->a, [[$widget, 5], [$gasket, 20]], 'SO-RESUMED')
            ->assertSessionHasNoErrors();

        $this->assertSame(15, $this->stock($widget, $this->a));
        $this->assertSame(0, $this->stock($gasket, $this->a));
    }

    public function test_the_workflow_can_be_unwound_to_zero_and_the_ledger_keeps_every_row(): void
    {
        // Stock returning to zero is not the ledger returning to empty. The
        // history is the audit trail, and nothing in the workflow may erase it.
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 100]], 'SI-UNWIND');
        $this->transfer($this->a, $this->b, [[$widget, 40]], 'TR-UNWIND');
        $this->adjust($this->b, [[$widget, 'decrease', 10]], 'ADJ-UNWIND', 'damage');

        $this->assertSame(90, StockMovement::totalStockAllWarehouses($widget->id));

        $rowsBefore = StockMovement::count();

        $this->issue($this->a, [[$widget, 60]], 'SO-UNWIND-A')->assertSessionHasNoErrors();
        $this->issue($this->b, [[$widget, 30]], 'SO-UNWIND-B')->assertSessionHasNoErrors();

        $this->assertSame(0, $this->stock($widget, $this->a));
        $this->assertSame(0, $this->stock($widget, $this->b));
        $this->assertSame(0, StockMovement::totalStockAllWarehouses($widget->id));

        $this->assertSame($rowsBefore + 2, StockMovement::count(), 'Emptying the shelves adds rows; it never removes them.');

        // Every document is still there to be read.
        $this->assertSame(1, StockIn::count());
        $this->assertSame(1, WarehouseTransfer::count());
        $this->assertSame(1, InventoryAdjustment::count());
        $this->assertSame(2, StockOut::count());
    }

    public function test_documents_stay_readable_and_unchanged_while_stock_keeps_moving(): void
    {
        // The receipt says 100 for ever, whatever later documents do to the
        // stock. A document that reported "current" quantities would rewrite
        // history every time something else moved.
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 100]], 'SI-AUDIT');
        $receipt = StockIn::firstWhere('reference_number', 'SI-AUDIT');

        $this->transfer($this->a, $this->b, [[$widget, 40]], 'TR-AUDIT');
        $this->issue($this->a, [[$widget, 55]], 'SO-AUDIT');
        $this->adjust($this->b, [[$widget, 'decrease', 40]], 'ADJ-AUDIT', 'theft');

        $this->assertSame(5, $this->stock($widget, $this->a));
        $this->assertSame(0, $this->stock($widget, $this->b));

        $this->assertDatabaseHas('stock_in_items', [
            'stock_in_id' => $receipt->id,
            'product_id'  => $widget->id,
            'quantity'    => 100,
        ]);

        $this->get(route('stock-in.show', $receipt))
            ->assertOk()
            ->assertSee('SI-AUDIT')
            ->assertSee('Widget')
            ->assertSee('100');

        $this->get(route('transfers.show', WarehouseTransfer::first()))->assertOk()->assertSee('TR-AUDIT');
        $this->get(route('stock-out.show', StockOut::first()))->assertOk()->assertSee('SO-AUDIT');
        $this->get(route('adjustments.show', InventoryAdjustment::first()))->assertOk()->assertSee('ADJ-AUDIT');
    }

    // -----------------------------------------------------------------------
    // The reports read the same ledger
    // -----------------------------------------------------------------------

    public function test_the_stock_report_agrees_with_the_ledger_after_the_workflow(): void
    {
        // Exported rather than scraped from the page: the CSV is exactly the
        // report's result set, with none of the filter dropdowns that make an
        // on-page assertion ambiguous.
        $widget = $this->product('Widget', 'WID-1', 5);
        $gasket = $this->product('Gasket', 'GAS-1', 50);

        $this->receive($this->a, [[$widget, 100], [$gasket, 60]], 'SI-REPORT');
        $this->transfer($this->a, $this->b, [[$widget, 40]], 'TR-REPORT');
        $this->adjust($this->b, [[$widget, 'decrease', 10]], 'ADJ-REPORT', 'damage');
        $this->issue($this->a, [[$widget, 50], [$gasket, 20]], 'SO-REPORT');

        $expected = [
            'Gasket' => ['Warehouse A' => 40],
            'Widget' => ['Warehouse A' => 10, 'Warehouse B' => 30],
        ];

        foreach ($expected as $productName => $byWarehouse) {
            foreach ($byWarehouse as $warehouseName => $quantity) {
                $product   = $productName === 'Widget' ? $widget : $gasket;
                $warehouse = $warehouseName === 'Warehouse A' ? $this->a : $this->b;

                $this->assertSame($quantity, $this->stock($product, $warehouse), "Ledger disagrees for {$productName} at {$warehouseName}.");
            }
        }

        $csv = $this->get(route('reports.stock.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        // Product, SKU, Category, Warehouse, Current Stock, Minimum Stock, Status.
        // fputcsv quotes any field containing a space, hence the warehouse names.
        $this->assertStringContainsString('Gasket,GAS-1,Hardware,"Warehouse A",40,50', $csv);
        $this->assertStringContainsString('Widget,WID-1,Hardware,"Warehouse A",10,5', $csv);
        $this->assertStringContainsString('Widget,WID-1,Hardware,"Warehouse B",30,5', $csv);

        // The gasket is under its minimum of 50 and the widget is not, which is
        // what the low-stock report should be selecting on.
        $lowStock = $this->get(route('reports.low-stock.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Gasket,GAS-1,Hardware,"Warehouse A",40,50,10', $lowStock);
        $this->assertStringNotContainsString('Widget', $lowStock);
    }

    public function test_the_movements_report_lists_every_step_with_the_document_that_caused_it(): void
    {
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 100]], 'SI-TRAIL');
        $this->transfer($this->a, $this->b, [[$widget, 40]], 'TR-TRAIL');
        $this->adjust($this->b, [[$widget, 'decrease', 10]], 'ADJ-TRAIL', 'damage');
        $this->issue($this->a, [[$widget, 50]], 'SO-TRAIL');

        $csv = $this->get(route('reports.movements.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        // Date, Product, SKU, Warehouse, Type, Quantity, Reference
        $this->assertStringContainsString('Widget,WID-1,"Warehouse A",IN,100,"stock in #' . StockIn::first()->id . '"', $csv);
        $this->assertStringContainsString('Widget,WID-1,"Warehouse A",OUT,40,"warehouse transfer #' . WarehouseTransfer::first()->id . '"', $csv);
        $this->assertStringContainsString('Widget,WID-1,"Warehouse B",IN,40,"warehouse transfer #' . WarehouseTransfer::first()->id . '"', $csv);
        $this->assertStringContainsString('Widget,WID-1,"Warehouse B",OUT,10,"inventory adjustment #' . InventoryAdjustment::first()->id . '"', $csv);
        $this->assertStringContainsString('Widget,WID-1,"Warehouse A",OUT,50,"stock out #' . StockOut::first()->id . '"', $csv);

        // Five movements plus the heading row and nothing else.
        $this->assertSame(6, count(array_filter(explode("\n", trim($csv)))));
    }

    public function test_the_movements_report_can_be_narrowed_to_one_step_of_the_workflow(): void
    {
        $widget = $this->product('Widget', 'WID-1');

        $this->receive($this->a, [[$widget, 100]], 'SI-FILTER');
        $this->transfer($this->a, $this->b, [[$widget, 40]], 'TR-FILTER');
        $this->issue($this->a, [[$widget, 20]], 'SO-FILTER');

        $onlyB = $this->get(route('reports.movements.export', ['format' => 'csv', 'warehouse_id' => $this->b->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Warehouse B",IN,40', $onlyB);
        $this->assertStringNotContainsString('Warehouse A', $onlyB, 'The warehouse filter is not applied.');

        $onlyOut = $this->get(route('reports.movements.export', ['format' => 'csv', 'type' => 'OUT']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Warehouse A",OUT,40', $onlyOut);
        $this->assertStringContainsString('"Warehouse A",OUT,20', $onlyOut);
        $this->assertStringNotContainsString(',IN,', $onlyOut, 'The type filter is not applied.');
    }
}
