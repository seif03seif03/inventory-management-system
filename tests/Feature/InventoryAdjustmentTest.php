<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 Task 9 — Inventory Adjustments.
 *
 * The properties that matter: an adjustment must move stock through the SAME
 * ledger as every other document (never a second stock system), a decrease must
 * never drive stock negative, and the whole document must be all-or-nothing.
 */
class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);

        $this->warehouse = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);

        $this->product = Product::create([
            'category_id'   => Category::create(['name' => 'Electronics', 'active' => true])->id,
            'name'          => 'iPhone 15',
            'sku'           => 'IPH-15',
            'price'         => 999,
            'minimum_stock' => 5,
            'active'        => true,
        ]);

        // Seed 100 units so decreases have something to work against.
        StockMovement::create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 100,
        ]);

        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'ADJ-1001',
            'adjustment_date'  => '2026-08-25',
            'reason'           => InventoryAdjustment::REASON_DAMAGE,
            'products'         => [$this->product->id],
            'directions'       => [InventoryAdjustmentItem::DIRECTION_DECREASE],
            'quantities'       => [10],
        ], $overrides);
    }

    private function stock(): int
    {
        return StockMovement::currentStock($this->product->id, $this->warehouse->id);
    }

    // -----------------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------------

    public function test_index_and_create_pages_load(): void
    {
        $this->get(route('adjustments.index'))->assertOk();
        $this->get(route('adjustments.create'))->assertOk();
    }

    // -----------------------------------------------------------------------
    // Decreases
    // -----------------------------------------------------------------------

    public function test_a_decrease_writes_an_out_movement_and_lowers_stock(): void
    {
        $this->post(route('adjustments.store'), $this->payload())
            ->assertRedirect(route('adjustments.show', InventoryAdjustment::first()));

        $this->assertSame(90, $this->stock());

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $this->product->id,
            'warehouse_id'   => $this->warehouse->id,
            'type'           => StockMovement::TYPE_OUT,
            'quantity'       => 10,
            'reference_type' => StockMovement::REFERENCE_ADJUSTMENT,
        ]);
    }

    public function test_a_decrease_larger_than_available_stock_is_refused(): void
    {
        $this->post(route('adjustments.store'), $this->payload(['quantities' => [500]]))
            ->assertSessionHas('stockErrors');

        $this->assertSame(100, $this->stock(), 'Stock must be untouched.');
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertDatabaseCount('inventory_adjustment_items', 0);
    }

    public function test_stock_can_never_be_driven_negative(): void
    {
        $this->post(route('adjustments.store'), $this->payload(['quantities' => [100]]));
        $this->assertSame(0, $this->stock());

        // One more unit than exists.
        $this->post(route('adjustments.store'), $this->payload([
            'reference_number' => 'ADJ-1002',
            'quantities'       => [1],
        ]))->assertSessionHas('stockErrors');

        $this->assertSame(0, $this->stock());
    }

    // -----------------------------------------------------------------------
    // Increases
    // -----------------------------------------------------------------------

    public function test_an_increase_writes_an_in_movement_and_raises_stock(): void
    {
        $this->post(route('adjustments.store'), $this->payload([
            'reason'     => InventoryAdjustment::REASON_RECOUNT,
            'directions' => [InventoryAdjustmentItem::DIRECTION_INCREASE],
            'quantities' => [25],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(125, $this->stock());

        $this->assertDatabaseHas('stock_movements', [
            'type'           => StockMovement::TYPE_IN,
            'quantity'       => 25,
            'reference_type' => StockMovement::REFERENCE_ADJUSTMENT,
        ]);
    }

    public function test_an_increase_needs_no_existing_stock(): void
    {
        $fresh = Product::create([
            'category_id'   => $this->product->category_id,
            'name'          => 'Brand New Item',
            'sku'           => 'NEW-1',
            'price'         => 10,
            'minimum_stock' => 0,
            'active'        => true,
        ]);

        $this->post(route('adjustments.store'), $this->payload([
            'reason'     => InventoryAdjustment::REASON_CORRECTION,
            'products'   => [$fresh->id],
            'directions' => [InventoryAdjustmentItem::DIRECTION_INCREASE],
            'quantities' => [7],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(7, StockMovement::currentStock($fresh->id, $this->warehouse->id));
    }

    // -----------------------------------------------------------------------
    // Mixed documents
    // -----------------------------------------------------------------------

    public function test_increase_and_decrease_on_one_document_are_judged_on_the_net_effect(): void
    {
        // 100 in stock. A 120 decrease alone would fail, but a same-document
        // 50 increase pays for it, so the net -70 is fine.
        $this->post(route('adjustments.store'), $this->payload([
            'reason'     => InventoryAdjustment::REASON_RECOUNT,
            'products'   => [$this->product->id, $this->product->id],
            'directions' => [
                InventoryAdjustmentItem::DIRECTION_INCREASE,
                InventoryAdjustmentItem::DIRECTION_DECREASE,
            ],
            'quantities' => [50, 120],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(30, $this->stock());
    }

    public function test_the_net_effect_is_reported_on_the_document(): void
    {
        $this->post(route('adjustments.store'), $this->payload([
            'products'   => [$this->product->id, $this->product->id],
            'directions' => [
                InventoryAdjustmentItem::DIRECTION_INCREASE,
                InventoryAdjustmentItem::DIRECTION_DECREASE,
            ],
            'quantities' => [30, 10],
        ]));

        $adjustment = InventoryAdjustment::with('items')->first();

        $this->assertSame(20, $adjustment->netQuantity());
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    public function test_a_reason_is_required(): void
    {
        $this->post(route('adjustments.store'), $this->payload(['reason' => null]))
            ->assertSessionHasErrors('reason');
    }

    public function test_an_unrecognised_reason_is_refused(): void
    {
        $this->post(route('adjustments.store'), $this->payload(['reason' => 'because-i-said-so']))
            ->assertSessionHasErrors('reason');
    }

    public function test_an_unrecognised_direction_is_refused(): void
    {
        $this->post(route('adjustments.store'), $this->payload(['directions' => ['sideways']]))
            ->assertSessionHasErrors('directions.0');
    }

    public function test_zero_and_negative_quantities_are_refused(): void
    {
        foreach ([0, -5] as $quantity) {
            $this->post(route('adjustments.store'), $this->payload(['quantities' => [$quantity]]))
                ->assertSessionHasErrors('quantities.0');
        }

        $this->assertSame(100, $this->stock());
    }

    public function test_mismatched_row_arrays_are_refused(): void
    {
        // A hand-crafted request: three products but two quantities would
        // otherwise silently pair the wrong values together.
        $this->post(route('adjustments.store'), $this->payload([
            'products'   => [$this->product->id, $this->product->id, $this->product->id],
            'directions' => [InventoryAdjustmentItem::DIRECTION_DECREASE],
            'quantities' => [1, 2],
        ]))->assertSessionHas('error');

        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_an_inactive_warehouse_or_product_is_refused(): void
    {
        $retiredWarehouse = Warehouse::create(['name' => 'Closed Depot', 'active' => false]);

        $this->post(route('adjustments.store'), $this->payload(['warehouse_id' => $retiredWarehouse->id]))
            ->assertSessionHasErrors('warehouse_id');

        $this->product->update(['active' => false]);

        $this->post(route('adjustments.store'), $this->payload())
            ->assertSessionHasErrors('products.0');
    }

    // -----------------------------------------------------------------------
    // Ledger integrity
    // -----------------------------------------------------------------------

    public function test_the_adjustment_uses_the_shared_ledger_not_a_second_stock_system(): void
    {
        $this->post(route('adjustments.store'), $this->payload());

        // The one rule: current stock = SUM(IN) - SUM(OUT). If an adjustment
        // kept its own counter, these two would disagree.
        $in  = StockMovement::where('product_id', $this->product->id)->where('type', StockMovement::TYPE_IN)->sum('quantity');
        $out = StockMovement::where('product_id', $this->product->id)->where('type', StockMovement::TYPE_OUT)->sum('quantity');

        $this->assertSame($this->stock(), (int) $in - (int) $out);
    }

    public function test_the_movement_resolves_back_to_its_adjustment(): void
    {
        $this->post(route('adjustments.store'), $this->payload());

        $movement = StockMovement::where('reference_type', StockMovement::REFERENCE_ADJUSTMENT)->first();

        $this->assertInstanceOf(InventoryAdjustment::class, $movement->reference);
        $this->assertSame('ADJ-1001', $movement->reference->reference_number);
    }

    public function test_the_quantity_is_stored_positive_with_direction_on_the_type(): void
    {
        // The codebase convention: quantity is always positive and the type
        // decides the sign. A negative quantity in the ledger would break
        // every SUM that reads it.
        $this->post(route('adjustments.store'), $this->payload());

        $this->assertSame(0, StockMovement::where('quantity', '<', 0)->count());
    }

    public function test_the_document_is_created_and_read_only(): void
    {
        $this->post(route('adjustments.store'), $this->payload());
        $adjustment = InventoryAdjustment::first();

        $this->get(route('adjustments.show', $adjustment))
            ->assertOk()
            ->assertSee('ADJ-1001')
            ->assertSee('iPhone 15');

        // Editing a posted document would change stock history.
        $this->put("/adjustments/{$adjustment->id}")->assertStatus(405);
        $this->assertContains($this->delete("/adjustments/{$adjustment->id}")->status(), [404, 405]);
    }

    public function test_the_author_is_recorded(): void
    {
        $this->post(route('adjustments.store'), $this->payload());

        $this->assertSame($this->admin->id, InventoryAdjustment::first()->created_by);
    }

    // -----------------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------------

    public function test_a_manager_can_create_adjustments(): void
    {
        $manager = User::factory()->create(['role_id' => Role::create(['name' => 'Warehouse Manager'])->id]);

        $this->actingAs($manager)
            ->post(route('adjustments.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(90, $this->stock());
    }

    public function test_an_employee_cannot_create_adjustments_and_no_stock_moves(): void
    {
        // An adjustment can conjure or destroy stock with no counterparty,
        // which makes it the most sensitive write in the system.
        $employee = User::factory()->create(['role_id' => Role::create(['name' => 'Warehouse Employee'])->id]);

        $this->actingAs($employee)->get(route('adjustments.create'))->assertStatus(403);
        $this->actingAs($employee)->post(route('adjustments.store'), $this->payload())->assertStatus(403);

        $this->assertSame(100, $this->stock());
        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_an_employee_can_still_view_the_adjustment_history(): void
    {
        $this->post(route('adjustments.store'), $this->payload());

        $employee = User::factory()->create(['role_id' => Role::create(['name' => 'Warehouse Employee'])->id]);

        $this->actingAs($employee)->get(route('adjustments.index'))->assertOk()->assertSee('ADJ-1001');
    }

    public function test_guests_cannot_reach_adjustments(): void
    {
        auth()->logout();

        $this->get(route('adjustments.index'))->assertRedirect(route('login'));
        $this->post(route('adjustments.store'), $this->payload())->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------------

    public function test_the_adjustment_is_written_to_the_activity_log(): void
    {
        $this->post(route('adjustments.store'), $this->payload());

        $this->assertDatabaseHas('activity_logs', [
            'subject_type'  => 'inventory_adjustment',
            'action'        => 'created',
            'subject_label' => 'ADJ-1001',
            'user_id'       => $this->admin->id,
        ]);
    }
}
