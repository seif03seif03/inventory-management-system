<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
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
 * Every role against every route, in one table.
 *
 * The individual feature tests each check the rule they care about. This one
 * exists so that adding a route without deciding who may reach it becomes a
 * failing test rather than an accident: the matrix below is the authorisation
 * design, written down.
 *
 * Only THREE outcomes are asserted, deliberately:
 *
 *   - a guest is sent to /login
 *   - a role that may not reach the route gets 403
 *   - a role that may reach it does NOT get 403 (200 for reads, 302 for writes)
 *
 * Nothing here asserts what a write actually did. Writes are posted with empty
 * payloads on purpose — validation then sends the allowed roles back with a 302,
 * which keeps this test about authorisation alone and unaffected by every future
 * change to a validation rule. The CRUD and workflow tests own the behaviour.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every role the seeder creates, plus null for an account whose role was
     * never set — RoleMiddleware has to refuse that case rather than crash on
     * a null role.
     */
    private const ROLES = [
        'Admin',
        'Warehouse Manager',
        'Warehouse Employee',
        'Inventory Employee',
        'Viewer',
        null,
    ];

    /** Creating a transfer or an adjustment moves real stock. */
    private const STOCK_WRITERS = ['Admin', 'Warehouse Manager'];

    private const ADMIN_ONLY = ['Admin'];

    // -----------------------------------------------------------------------
    // The matrix
    // -----------------------------------------------------------------------

    /**
     * @return array<int, array{0: string, 1: string, 2: array<int, string|null>}>
     *         [http method, uri, roles allowed through]
     */
    private function matrix(array $f): array
    {
        $everyone = self::ROLES;

        return [
            // --- Dashboard and profile ---------------------------------------
            ['GET',    '/',          $everyone],
            ['GET',    '/dashboard', $everyone],
            ['GET',    '/profile',   $everyone],
            ['PUT',    '/profile',   $everyone],

            // --- Products ----------------------------------------------------
            ['GET',    '/products',                      $everyone],
            ['GET',    '/products/create',               $everyone],
            ['POST',   '/products',                      $everyone],
            ['GET',    "/products/{$f['product']}",      $everyone],
            ['GET',    "/products/{$f['product']}/edit", $everyone],
            ['PUT',    "/products/{$f['product']}",      $everyone],
            ['DELETE', "/products/{$f['product']}",      $everyone],

            // --- Categories --------------------------------------------------
            ['GET',    '/categories',                       $everyone],
            ['GET',    '/categories/create',                $everyone],
            ['POST',   '/categories',                       $everyone],
            ['GET',    "/categories/{$f['category']}",      $everyone],
            ['GET',    "/categories/{$f['category']}/edit", $everyone],
            ['PUT',    "/categories/{$f['category']}",      $everyone],
            ['DELETE', "/categories/{$f['category']}",      $everyone],

            // --- Suppliers ---------------------------------------------------
            ['GET',    '/suppliers',                       $everyone],
            ['GET',    '/suppliers/create',                $everyone],
            ['POST',   '/suppliers',                       $everyone],
            ['GET',    "/suppliers/{$f['supplier']}",      $everyone],
            ['GET',    "/suppliers/{$f['supplier']}/edit", $everyone],
            ['PUT',    "/suppliers/{$f['supplier']}",      $everyone],
            ['DELETE', "/suppliers/{$f['supplier']}",      $everyone],

            // --- Distributors ------------------------------------------------
            ['GET',    '/distributors',                          $everyone],
            ['GET',    '/distributors/create',                   $everyone],
            ['POST',   '/distributors',                          $everyone],
            ['GET',    "/distributors/{$f['distributor']}",      $everyone],
            ['GET',    "/distributors/{$f['distributor']}/edit", $everyone],
            ['PUT',    "/distributors/{$f['distributor']}",      $everyone],
            ['DELETE', "/distributors/{$f['distributor']}",      $everyone],

            // --- Warehouses --------------------------------------------------
            ['GET',    '/warehouses',                        $everyone],
            ['GET',    '/warehouses/create',                 $everyone],
            ['POST',   '/warehouses',                        $everyone],
            ['GET',    "/warehouses/{$f['warehouse']}",      $everyone],
            ['GET',    "/warehouses/{$f['warehouse']}/edit", $everyone],
            ['PUT',    "/warehouses/{$f['warehouse']}",      $everyone],
            ['DELETE', "/warehouses/{$f['warehouse']}",      $everyone],

            // --- Stock In / Stock Out ----------------------------------------
            ['GET',    '/stock-in',                 $everyone],
            ['GET',    '/stock-in/create',          $everyone],
            ['POST',   '/stock-in',                 $everyone],
            ['GET',    "/stock-in/{$f['stockIn']}", $everyone],

            ['GET',    '/stock-out',                  $everyone],
            ['GET',    '/stock-out/create',           $everyone],
            ['POST',   '/stock-out',                  $everyone],
            ['GET',    "/stock-out/{$f['stockOut']}", $everyone],

            ['GET',    '/stock-movements', $everyone],

            // --- Transfers: viewing is open, creating is not ------------------
            ['GET',    '/transfers',                  $everyone],
            ['GET',    '/transfers/create',           self::STOCK_WRITERS],
            ['POST',   '/transfers',                  self::STOCK_WRITERS],
            ['GET',    "/transfers/{$f['transfer']}", $everyone],

            // --- Adjustments: same split -------------------------------------
            ['GET',    '/adjustments',                    $everyone],
            ['GET',    '/adjustments/create',             self::STOCK_WRITERS],
            ['POST',   '/adjustments',                    self::STOCK_WRITERS],
            ['GET',    "/adjustments/{$f['adjustment']}", $everyone],

            // --- Reports -----------------------------------------------------
            ['GET',    '/reports',           $everyone],
            ['GET',    '/reports/stock',     $everyone],
            ['GET',    '/reports/movements', $everyone],
            ['GET',    '/reports/low-stock', $everyone],
            ['GET',    '/reports/stock-in',  $everyone],
            ['GET',    '/reports/stock-out', $everyone],

            // Exports are permissioned by report, not by format — {format} is a
            // route parameter, not a separate rule. CSV is exercised here for
            // every report; ReportExportTest owns the PDF path, which is far
            // slower to render and would add nothing about authorisation.
            ['GET',    '/reports/stock/export/csv',     $everyone],
            ['GET',    '/reports/movements/export/csv', $everyone],
            ['GET',    '/reports/low-stock/export/csv', $everyone],
            ['GET',    '/reports/stock-in/export/csv',  $everyone],
            ['GET',    '/reports/stock-out/export/csv', $everyone],

            // --- Admin only --------------------------------------------------
            ['GET',    '/users',                         self::ADMIN_ONLY],
            ['GET',    '/users/create',                  self::ADMIN_ONLY],
            ['POST',   '/users',                         self::ADMIN_ONLY],
            ['GET',    "/users/{$f['targetUser']}/edit", self::ADMIN_ONLY],
            ['PUT',    "/users/{$f['targetUser']}",      self::ADMIN_ONLY],
            ['DELETE', "/users/{$f['targetUser']}",      self::ADMIN_ONLY],
            ['GET',    '/activity-logs',                 self::ADMIN_ONLY],
        ];
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_every_role_gets_the_documented_answer_on_every_route(): void
    {
        foreach (self::ROLES as $roleName) {
            // Fresh records for each pass. Route model binding runs before the
            // role middleware, so a record deleted by an earlier pass would
            // turn a later 403 into a 404 and quietly stop testing the rule.
            $fixtures = $this->freshFixtures();
            $user     = $this->userWithRole($roleName);
            $label    = $roleName ?? 'no role';

            foreach ($this->matrix($fixtures) as [$method, $uri, $allowedRoles]) {
                $status = $this->actingAs($user)->call($method, $uri)->getStatusCode();
                $where  = "{$method} {$uri} as {$label}";

                if (in_array($roleName, $allowedRoles, true)) {
                    $this->assertContains($status, [200, 302], "Expected {$where} to be allowed, got {$status}.");

                    continue;
                }

                $this->assertSame(403, $status, "Expected {$where} to be refused with 403, got {$status}.");
            }
        }
    }

    public function test_guests_are_sent_to_login_from_every_route(): void
    {
        foreach ($this->matrix($this->freshFixtures()) as [$method, $uri, $_allowedRoles]) {
            $this->call($method, $uri)
                ->assertRedirect('/login', "Expected a guest to be redirected from {$method} {$uri}.");
        }
    }

    public function test_a_role_gated_route_refuses_rather_than_silently_ignoring_the_request(): void
    {
        // The matrix asserts the status code; this asserts the consequence. A
        // 403 that had already written the row would still pass the matrix.
        $fixtures = $this->freshFixtures();
        $employee = $this->userWithRole('Warehouse Employee');

        $movementsBefore = StockMovement::count();

        $this->actingAs($employee)->post('/adjustments', [
            'warehouse_id'     => $fixtures['warehouse'],
            'reference_number' => 'ADJ-FORBIDDEN',
            'adjustment_date'  => today()->toDateString(),
            'reason'           => 'damage',
            'products'         => [$fixtures['product']],
            'directions'       => ['increase'],
            'quantities'       => [5],
        ])->assertForbidden();

        $this->assertDatabaseMissing('inventory_adjustments', ['reference_number' => 'ADJ-FORBIDDEN']);
        $this->assertSame($movementsBefore, StockMovement::count(), 'A refused adjustment still touched the ledger.');
    }

    // -----------------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------------

    private function userWithRole(?string $roleName): User
    {
        return User::factory()->create([
            'role_id' => $roleName === null
                ? null
                : Role::firstOrCreate(['name' => $roleName])->id,
        ]);
    }

    /**
     * One record of every bound type, rebuilt for each role pass.
     *
     * The deletable ones are given history on purpose: a product that has moved
     * cannot be deleted, so DELETE returns 302-with-an-error instead of actually
     * removing the row. That keeps the matrix stable without weakening it — the
     * question here is only whether the request got past authorisation, and the
     * CRUD tests already prove a clean record really is deleted.
     */
    private function freshFixtures(): array
    {
        $category    = Category::factory()->create();
        $product     = Product::factory()->create(['category_id' => $category->id]);
        $supplier    = Supplier::factory()->create();
        $distributor = Distributor::factory()->create();
        $warehouse   = Warehouse::factory()->create();
        $otherHouse  = Warehouse::factory()->create();

        $author = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Admin'])->id,
        ]);

        $stockIn = StockIn::create([
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'SI-MATRIX',
            'receipt_date'     => today()->toDateString(),
            'status'           => 'completed',
        ]);
        $stockIn->items()->create(['product_id' => $product->id, 'quantity' => 100, 'unit_cost' => 5]);

        StockMovement::create([
            'product_id'     => $product->id,
            'warehouse_id'   => $warehouse->id,
            'type'           => StockMovement::TYPE_IN,
            'quantity'       => 100,
            'reference_type' => StockMovement::REFERENCE_STOCK_IN,
            'reference_id'   => $stockIn->id,
        ]);

        $stockOut = StockOut::create([
            'distributor_id'   => $distributor->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'SO-MATRIX',
            'issue_date'       => today()->toDateString(),
            'status'           => 'completed',
        ]);
        $stockOut->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        StockMovement::create([
            'product_id'     => $product->id,
            'warehouse_id'   => $warehouse->id,
            'type'           => StockMovement::TYPE_OUT,
            'quantity'       => 1,
            'reference_type' => StockMovement::REFERENCE_STOCK_OUT,
            'reference_id'   => $stockOut->id,
        ]);

        $transfer = WarehouseTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id'   => $otherHouse->id,
            'reference_number'  => 'TR-MATRIX',
            'transfer_date'     => today()->toDateString(),
            'status'            => WarehouseTransfer::STATUS_COMPLETED,
            'created_by'        => $author->id,
        ]);
        $transfer->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $adjustment = InventoryAdjustment::create([
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'ADJ-MATRIX',
            'adjustment_date'  => today()->toDateString(),
            'reason'           => 'damage',
            'status'           => InventoryAdjustment::STATUS_COMPLETED,
            'created_by'       => $author->id,
        ]);
        $adjustment->items()->create([
            'product_id' => $product->id,
            'direction'  => InventoryAdjustmentItem::DIRECTION_INCREASE,
            'quantity'   => 1,
        ]);

        // The DELETE /users/{user} row must not be an Admin: the controller
        // refuses to remove the last one, which would mask the rule under test.
        $targetUser = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'Viewer'])->id,
        ]);

        return [
            'category'    => $category->id,
            'product'     => $product->id,
            'supplier'    => $supplier->id,
            'distributor' => $distributor->id,
            'warehouse'   => $warehouse->id,
            'stockIn'     => $stockIn->id,
            'stockOut'    => $stockOut->id,
            'transfer'    => $transfer->id,
            'adjustment'  => $adjustment->id,
            'targetUser'  => $targetUser->id,
        ];
    }
}
