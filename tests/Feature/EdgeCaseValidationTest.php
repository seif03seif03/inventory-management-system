<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The awkward inputs: boundary quantities, duplicate keys, retired records.
 *
 * StockInTest and InventoryAdjustmentTest already own the zero/negative and
 * mismatched-row rules for their own documents. This file deliberately does not
 * repeat them — it covers the same class of input everywhere it is NOT yet
 * checked (Stock Out, Transfers, master data), plus the duplicate-key and
 * inactive-record cases that cut across every screen.
 */
class EdgeCaseValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Product $product;
    private Supplier $supplier;
    private Distributor $distributor;
    private Warehouse $warehouse;
    private Warehouse $otherWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($this->admin);

        $this->category       = Category::create(['name' => 'Electronics', 'active' => true]);
        $this->supplier       = Supplier::create(['name' => 'TechSource', 'active' => true]);
        $this->distributor    = Distributor::create(['name' => 'Downtown Dist', 'active' => true]);
        $this->warehouse      = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $this->otherWarehouse = Warehouse::create(['name' => 'North Branch', 'active' => true]);

        $this->product = Product::create([
            'category_id'   => $this->category->id,
            'name'          => 'iPhone 15',
            'sku'           => 'IPH-15',
            'barcode'       => '1234567890123',
            'price'         => 999,
            'minimum_stock' => 5,
            'active'        => true,
        ]);
    }

    private function stockOnHand(int $quantity, ?Warehouse $warehouse = null): void
    {
        StockMovement::create([
            'product_id'   => $this->product->id,
            'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => $quantity,
        ]);
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id'   => $this->category->id,
            'name'          => 'Some Product',
            'sku'           => 'SKU-NEW',
            'price'         => 10,
            'minimum_stock' => 0,
            'active'        => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // Duplicate keys
    // -----------------------------------------------------------------------

    public function test_a_duplicate_sku_is_refused_on_create_and_on_update(): void
    {
        $this->post(route('products.store'), $this->productPayload(['sku' => 'IPH-15']))
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::count());

        $other = Product::create($this->productPayload(['sku' => 'OTHER-1', 'active' => true]));

        $this->put(route('products.update', $other), $this->productPayload(['sku' => 'IPH-15']))
            ->assertSessionHasErrors('sku');

        $this->assertSame('OTHER-1', $other->refresh()->sku);
    }

    public function test_a_sku_that_differs_only_in_case_is_refused(): void
    {
        // Product::booted() upper-cases the SKU on save, so 'iph-15' and
        // 'IPH-15' are the same key once stored. Validating the raw value would
        // let this through and then hit the unique index as a 500.
        $this->post(route('products.store'), $this->productPayload(['sku' => 'iph-15']))
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::count());
    }

    public function test_a_product_may_keep_its_own_sku_when_updated(): void
    {
        // The unique rule has to ignore the row being edited, or saving a
        // product without renaming it would fail.
        $this->put(route('products.update', $this->product), $this->productPayload([
            'name'    => 'iPhone 15 Pro',
            'sku'     => 'IPH-15',
            'barcode' => '1234567890123',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('iPhone 15 Pro', $this->product->refresh()->name);
    }

    public function test_a_duplicate_barcode_is_refused(): void
    {
        $this->post(route('products.store'), $this->productPayload(['barcode' => '1234567890123']))
            ->assertSessionHasErrors('barcode');

        $this->assertSame(1, Product::count());
    }

    public function test_several_products_may_have_no_barcode(): void
    {
        // A blank barcode is stored as NULL, never as ''. Two empty strings
        // would collide on the unique index; two NULLs do not.
        $this->post(route('products.store'), $this->productPayload(['sku' => 'NB-1', 'barcode' => '']))
            ->assertSessionHasNoErrors();

        $this->post(route('products.store'), $this->productPayload(['sku' => 'NB-2', 'barcode' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Product::whereNull('barcode')->count());
        $this->assertSame(0, Product::where('barcode', '')->count());
    }

    public function test_a_duplicate_user_email_is_refused_on_create_and_on_update(): void
    {
        $viewerRole = Role::create(['name' => 'Viewer']);

        $existing = User::factory()->create(['email' => 'taken@example.test', 'role_id' => $viewerRole->id]);

        $this->post(route('users.store'), [
            'name'                  => 'Impostor',
            'email'                 => 'taken@example.test',
            'role_id'               => $viewerRole->id,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $other = User::factory()->create(['email' => 'other@example.test', 'role_id' => $viewerRole->id]);

        $this->put(route('users.update', $other), [
            'name'    => $other->name,
            'email'   => 'taken@example.test',
            'role_id' => $viewerRole->id,
        ])->assertSessionHasErrors('email');

        $this->assertSame('other@example.test', $other->refresh()->email);
        $this->assertSame('taken@example.test', $existing->refresh()->email);
    }

    public function test_a_user_cannot_take_an_email_already_used_by_another_account_via_the_profile_form(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.test']);

        $this->actingAs($victim);

        User::factory()->create(['email' => 'occupied@example.test']);

        $this->put(route('profile.update'), [
            'name'  => $victim->name,
            'email' => 'occupied@example.test',
        ])->assertSessionHasErrors('email');

        $this->assertSame('victim@example.test', $victim->refresh()->email);
    }

    // -----------------------------------------------------------------------
    // Numeric boundaries on master data
    // -----------------------------------------------------------------------

    public function test_a_negative_price_or_minimum_stock_is_refused(): void
    {
        $this->post(route('products.store'), $this->productPayload(['price' => -1]))
            ->assertSessionHasErrors('price');

        $this->post(route('products.store'), $this->productPayload(['minimum_stock' => -1]))
            ->assertSessionHasErrors('minimum_stock');

        $this->post(route('products.store'), $this->productPayload(['minimum_stock' => 2.5]))
            ->assertSessionHasErrors('minimum_stock');

        $this->assertSame(1, Product::count());
    }

    public function test_zero_price_and_zero_minimum_stock_are_accepted(): void
    {
        // Zero is a real value, not a missing one: a free sample costs nothing,
        // and a product with no reorder point should never raise a low-stock
        // alert. Rejecting zero here would be wrong.
        $this->post(route('products.store'), $this->productPayload([
            'sku'           => 'FREE-1',
            'price'         => 0,
            'minimum_stock' => 0,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['sku' => 'FREE-1', 'minimum_stock' => 0]);
    }

    public function test_an_over_long_name_is_refused_rather_than_silently_truncated(): void
    {
        $tooLong = str_repeat('a', 256);

        $this->post(route('products.store'), $this->productPayload(['name' => $tooLong]))
            ->assertSessionHasErrors('name');

        $this->post(route('categories.store'), ['name' => $tooLong])
            ->assertSessionHasErrors('name');

        $this->post(route('suppliers.store'), ['name' => $tooLong, 'active' => '1'])
            ->assertSessionHasErrors('name');

        $this->post(route('warehouses.store'), ['name' => $tooLong, 'active' => '1'])
            ->assertSessionHasErrors('name');

        $this->post(route('distributors.store'), ['name' => $tooLong, 'active' => '1'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_malformed_contact_email_is_refused(): void
    {
        $this->post(route('suppliers.store'), [
            'name'   => 'Bad Email Co',
            'email'  => 'not-an-email',
            'active' => '1',
        ])->assertSessionHasErrors('email');

        $this->post(route('distributors.store'), [
            'name'   => 'Bad Email Dist',
            'email'  => 'also@not@valid',
            'active' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, Supplier::count());
        $this->assertSame(1, Distributor::count());
    }

    // -----------------------------------------------------------------------
    // Quantity boundaries on the documents that did not already cover them
    // -----------------------------------------------------------------------

    public function test_stock_out_refuses_zero_and_negative_quantities(): void
    {
        $this->stockOnHand(50);

        foreach ([0, -5] as $quantity) {
            $this->post(route('stock-out.store'), [
                'distributor_id'   => $this->distributor->id,
                'warehouse_id'     => $this->warehouse->id,
                'reference_number' => 'SO-BAD',
                'issue_date'       => today()->toDateString(),
                'products'         => [$this->product->id],
                'quantities'       => [$quantity],
            ])->assertSessionHasErrors('quantities.0');
        }

        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertSame(50, StockMovement::currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_a_transfer_refuses_zero_and_negative_quantities(): void
    {
        $this->stockOnHand(50);

        foreach ([0, -5] as $quantity) {
            $this->post(route('transfers.store'), [
                'from_warehouse_id' => $this->warehouse->id,
                'to_warehouse_id'   => $this->otherWarehouse->id,
                'reference_number'  => 'TR-BAD',
                'transfer_date'     => today()->toDateString(),
                'products'          => [$this->product->id],
                'quantities'        => [$quantity],
            ])->assertSessionHasErrors('quantities.0');
        }

        $this->assertDatabaseCount('warehouse_transfers', 0);
        $this->assertSame(50, StockMovement::currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_a_non_numeric_quantity_is_refused(): void
    {
        $this->stockOnHand(50);

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SO-TEXT',
            'issue_date'       => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => ['plenty'],
        ])->assertSessionHasErrors('quantities.0');

        $this->assertDatabaseCount('stock_outs', 0);
    }

    public function test_a_negative_unit_cost_is_refused_on_a_receipt(): void
    {
        $this->post(route('stock-in.store'), [
            'supplier_id'      => $this->supplier->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SI-NEGCOST',
            'receipt_date'     => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => [5],
            'unit_costs'       => [-10],
        ])->assertSessionHasErrors('unit_costs.0');

        $this->assertDatabaseCount('stock_ins', 0);
    }

    public function test_a_zero_unit_cost_is_accepted_on_a_receipt(): void
    {
        // Free goods and warranty replacements arrive at no cost.
        $this->post(route('stock-in.store'), [
            'supplier_id'      => $this->supplier->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SI-FREE',
            'receipt_date'     => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => [5],
            'unit_costs'       => [0],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, StockMovement::currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_issuing_exactly_the_available_stock_is_allowed_and_lands_on_zero(): void
    {
        // The boundary itself: >= must not be read as >.
        $this->stockOnHand(7);

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SO-EXACT',
            'issue_date'       => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => [7],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, StockMovement::currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_one_unit_beyond_the_available_stock_is_refused(): void
    {
        $this->stockOnHand(7);

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SO-OVER',
            'issue_date'       => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => [8],
        ])->assertSessionHas('stockErrors');

        $this->assertSame(7, StockMovement::currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_mismatched_row_arrays_are_refused_on_stock_out_and_transfers(): void
    {
        $this->stockOnHand(50);

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SO-RAGGED',
            'issue_date'       => today()->toDateString(),
            'products'         => [$this->product->id, $this->product->id],
            'quantities'       => [1],
        ])->assertSessionHas('error');

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id'   => $this->otherWarehouse->id,
            'reference_number'  => 'TR-RAGGED',
            'transfer_date'     => today()->toDateString(),
            'products'          => [$this->product->id, $this->product->id],
            'quantities'        => [1],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertDatabaseCount('warehouse_transfers', 0);
        $this->assertSame(50, StockMovement::currentStock($this->product->id, $this->warehouse->id));
    }

    // -----------------------------------------------------------------------
    // Retired records
    // -----------------------------------------------------------------------

    public function test_an_inactive_supplier_warehouse_or_distributor_cannot_be_used_on_a_document(): void
    {
        $this->stockOnHand(50);

        $this->supplier->update(['active' => false]);
        $this->distributor->update(['active' => false]);
        $retiredWarehouse = Warehouse::create(['name' => 'Closed Depot', 'active' => false]);

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $this->supplier->id,
            'warehouse_id'     => $retiredWarehouse->id,
            'reference_number' => 'SI-RETIRED',
            'receipt_date'     => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => [1],
            'unit_costs'       => [1],
        ])->assertSessionHasErrors(['supplier_id', 'warehouse_id']);

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SO-RETIRED',
            'issue_date'       => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => [1],
        ])->assertSessionHasErrors('distributor_id');

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id'   => $retiredWarehouse->id,
            'reference_number'  => 'TR-RETIRED',
            'transfer_date'     => today()->toDateString(),
            'products'          => [$this->product->id],
            'quantities'        => [1],
        ])->assertSessionHasErrors('to_warehouse_id');

        $this->assertDatabaseCount('stock_ins', 0);
        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertDatabaseCount('warehouse_transfers', 0);
    }

    public function test_an_inactive_product_cannot_be_added_to_a_document_but_keeps_the_stock_it_had(): void
    {
        // Deactivating a product does not make the units on the shelf vanish —
        // the ledger still has to explain them, so reports must keep counting
        // them even though no new document may name the product.
        $this->stockOnHand(30);
        $this->product->update(['active' => false]);

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->warehouse->id,
            'reference_number' => 'SO-DEAD',
            'issue_date'       => today()->toDateString(),
            'products'         => [$this->product->id],
            'quantities'       => [1],
        ])->assertSessionHasErrors('products.0');

        $this->assertSame(30, StockMovement::currentStock($this->product->id, $this->warehouse->id));

        $this->get(route('reports.stock'))->assertOk()->assertSee('iPhone 15');
    }

    public function test_an_inactive_record_is_absent_from_the_document_form_dropdowns(): void
    {
        $this->supplier->update(['active' => false]);
        Supplier::create(['name' => 'Still Trading Co', 'active' => true]);

        $this->get(route('stock-in.create'))
            ->assertOk()
            ->assertSee('Still Trading Co')
            ->assertDontSee('TechSource');
    }

    public function test_a_transfer_between_the_same_warehouse_twice_over_is_still_refused(): void
    {
        // 'different' has to be enforced server-side; the form's own dropdown
        // cannot be trusted on a hand-crafted request.
        $this->stockOnHand(50);

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id'   => $this->warehouse->id,
            'reference_number'  => 'TR-SELF',
            'transfer_date'     => today()->toDateString(),
            'products'          => [$this->product->id],
            'quantities'        => [1],
        ])->assertSessionHasErrors('to_warehouse_id');

        $this->assertSame(50, StockMovement::currentStock($this->product->id, $this->warehouse->id));
    }

    // -----------------------------------------------------------------------
    // Passwords
    // -----------------------------------------------------------------------

    public function test_a_short_or_unconfirmed_password_is_refused(): void
    {
        $viewerRole = Role::create(['name' => 'Viewer']);

        $this->post(route('users.store'), [
            'name'                  => 'Weak',
            'email'                 => 'weak@example.test',
            'role_id'               => $viewerRole->id,
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post(route('users.store'), [
            'name'                  => 'Mismatch',
            'email'                 => 'mismatch@example.test',
            'role_id'               => $viewerRole->id,
            'password'              => 'password123',
            'password_confirmation' => 'password456',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.test']);
    }

    public function test_changing_a_password_requires_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name'                  => $user->name,
            'email'                 => $user->email,
            'current_password'      => 'wrong-password',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(
            password_verify('correct-horse', $user->refresh()->password),
            'The password must be unchanged when the current one was wrong.'
        );
    }

    public function test_an_unknown_role_cannot_be_assigned(): void
    {
        $this->post(route('users.store'), [
            'name'                  => 'Ghost Role',
            'email'                 => 'ghost@example.test',
            'role_id'               => 99999,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'ghost@example.test']);
    }
}
