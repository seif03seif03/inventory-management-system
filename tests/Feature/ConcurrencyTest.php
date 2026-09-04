<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\InventoryStockLock;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Two people issuing the same last unit at the same time.
 *
 * The defence has three parts and each is tested here:
 *
 *   1. the write runs inside one transaction, so a half-written document cannot
 *      survive a failure;
 *   2. every row the write depends on is locked FOR UPDATE before it is read,
 *      always in the same order, so two concurrent requests queue up instead of
 *      deadlocking;
 *   3. the stock check is repeated INSIDE the transaction, after the lock — the
 *      check made before it is only a courtesy to produce better error
 *      messages, and is stale by definition.
 *
 * A note on the test database. This suite runs on SQLite, where lockForUpdate()
 * is accepted but compiled away, and there is no second connection to race
 * against. So these tests do not prove that MySQL holds row locks — that is the
 * database's job. They prove the parts that live in this codebase: that the
 * application asks for the locks, over the right ids, in a consistent order,
 * and that the check behind the lock really does catch stock that moved.
 */
class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Warehouse $main;
    private Warehouse $branch;
    private Supplier $supplier;
    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($this->admin);

        $this->category    = Category::create(['name' => 'Consumables', 'active' => true]);
        $this->main        = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $this->branch      = Warehouse::create(['name' => 'North Branch', 'active' => true]);
        $this->supplier    = Supplier::create(['name' => 'Supply Co', 'active' => true]);
        $this->distributor = Distributor::create(['name' => 'City Dist', 'active' => true]);
    }

    private function product(string $sku): Product
    {
        return Product::create([
            'category_id'   => $this->category->id,
            'name'          => "Product {$sku}",
            'sku'           => $sku,
            'price'         => 10,
            'minimum_stock' => 0,
            'active'        => true,
        ]);
    }

    private function stock(Product $product, Warehouse $warehouse, int $quantity): void
    {
        StockMovement::create([
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => $quantity,
        ]);
    }

    /**
     * Record the locking selects issued while $work runs.
     *
     * On SQLite the "for update" clause is compiled to an empty string, so the
     * text cannot be matched (the test below borrows MySQL's grammar to prove
     * the clause is really there). Here the locking selects are recognised by
     * their shape instead — id column only, filtered by id, ordered by id —
     * which nothing else in the request produces.
     *
     * @return array<int, array{table: string, sql: string, bindings: array}>
     */
    private function captureLockQueries(callable $work): array
    {
        $locks = [];

        DB::listen(function ($query) use (&$locks) {
            $shape = '/^select "id" from "(products|warehouses)" where "id" in \(\?(?:, \?)*\) order by "id" asc$/';

            if (preg_match($shape, $query->sql, $matches)) {
                $locks[] = [
                    'table'    => $matches[1],
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                ];
            }
        });

        $work();

        return $locks;
    }

    // -----------------------------------------------------------------------
    // The lock helper
    // -----------------------------------------------------------------------

    public function test_the_lock_really_asks_the_database_for_a_row_lock(): void
    {
        // Everything else in this file checks WHICH rows are locked. This checks
        // that a lock is asked for at all, which SQLite's grammar hides by
        // dropping the clause. Borrowing MySQL's grammar for one dry run shows
        // the SQL the builder is actually carrying; pretend() logs it instead of
        // running it, so nothing reaches the SQLite database.
        $product = $this->product('FORUPDATE-1');

        $connection = DB::connection();
        $original   = $connection->getQueryGrammar();

        $connection->setQueryGrammar(new MySqlGrammar($connection));

        try {
            $log = $connection->pretend(
                fn () => InventoryStockLock::lock([$product->id], [$this->main->id])
            );
        } finally {
            $connection->setQueryGrammar($original);
        }

        $this->assertCount(2, $log);

        $this->assertStringContainsString('`products`', $log[0]['query']);
        $this->assertStringContainsString('for update', $log[0]['query'], 'The product rows were read without a lock.');

        $this->assertStringContainsString('`warehouses`', $log[1]['query']);
        $this->assertStringContainsString('for update', $log[1]['query'], 'The warehouse rows were read without a lock.');
    }

    public function test_the_lock_asks_for_rows_in_ascending_id_order(): void
    {
        // Deadlocks happen when two transactions take the same locks in
        // different orders. Sorting the ids gives every request one global
        // order, so the second one waits instead of deadlocking.
        $first  = $this->product('LOCK-A');   // lower id
        $second = $this->product('LOCK-B');   // higher id

        $locks = $this->captureLockQueries(function () use ($first, $second) {
            DB::transaction(function () use ($first, $second) {
                // Deliberately passed high-to-low, with a duplicate and two
                // unusable values mixed in.
                InventoryStockLock::lock(
                    [$second->id, $first->id, $second->id, 0, -3],
                    [$this->branch->id, $this->main->id, '0']
                );
            });
        });

        $this->assertCount(2, $locks, 'One locking select for products, one for warehouses.');

        $this->assertSame([$first->id, $second->id], $locks[0]['bindings'], 'Product ids were not sorted ascending, or a duplicate slipped through.');
        $this->assertSame([$this->main->id, $this->branch->id], $locks[1]['bindings'], 'Warehouse ids were not sorted ascending.');
    }

    public function test_the_lock_always_takes_products_before_warehouses(): void
    {
        // The two tables have to be locked in a fixed order too, for the same
        // reason the ids within each are sorted.
        $product = $this->product('ORDER-1');

        $locks = $this->captureLockQueries(function () use ($product) {
            DB::transaction(fn () => InventoryStockLock::lock([$product->id], [$this->main->id]));
        });

        $this->assertSame(['products', 'warehouses'], array_column($locks, 'table'));
    }

    public function test_the_lock_issues_no_query_for_an_empty_list(): void
    {
        $product = $this->product('EMPTY-1');

        $locks = $this->captureLockQueries(function () use ($product) {
            DB::transaction(function () use ($product) {
                InventoryStockLock::lock([], []);
                InventoryStockLock::lock([$product->id], []);
                InventoryStockLock::lock([], [$this->main->id]);
                // Ids that normalise away leave nothing to lock either.
                InventoryStockLock::lock([0, -1, 'abc'], [null]);
            });
        });

        $this->assertSame(['products', 'warehouses'], array_column($locks, 'table'), 'Only the two non-empty calls should have locked anything.');
    }

    // -----------------------------------------------------------------------
    // The documents take the locks they need
    // -----------------------------------------------------------------------

    public function test_issuing_stock_locks_the_products_and_the_warehouse(): void
    {
        $first  = $this->product('SO-LOCK-A');
        $second = $this->product('SO-LOCK-B');

        $this->stock($first, $this->main, 10);
        $this->stock($second, $this->main, 10);

        $locks = $this->captureLockQueries(function () use ($first, $second) {
            $this->post(route('stock-out.store'), [
                'distributor_id'   => $this->distributor->id,
                'warehouse_id'     => $this->main->id,
                'reference_number' => 'SO-LOCKS',
                'issue_date'       => today()->toDateString(),
                'products'         => [$first->id, $second->id],
                'quantities'       => [1, 1],
            ])->assertSessionHasNoErrors();
        });

        $this->assertCount(2, $locks);
        $this->assertSame([$first->id, $second->id], $locks[0]['bindings']);
        $this->assertSame([$this->main->id], $locks[1]['bindings']);
    }

    public function test_receiving_stock_locks_before_writing(): void
    {
        // A receipt cannot fail for lack of stock, but it still has to queue
        // behind whoever else is touching the same product and warehouse — an
        // unlocked receipt could interleave with an issue and leave the ledger
        // reflecting only one of them.
        $product = $this->product('SI-LOCK-1');

        $locks = $this->captureLockQueries(function () use ($product) {
            $this->post(route('stock-in.store'), [
                'supplier_id'      => $this->supplier->id,
                'warehouse_id'     => $this->main->id,
                'reference_number' => 'SI-LOCKS',
                'receipt_date'     => today()->toDateString(),
                'products'         => [$product->id],
                'quantities'       => [5],
                'unit_costs'       => [1],
            ])->assertSessionHasNoErrors();
        });

        $this->assertCount(2, $locks);
        $this->assertSame([$product->id], $locks[0]['bindings']);
        $this->assertSame([$this->main->id], $locks[1]['bindings']);
    }

    public function test_an_adjustment_locks_before_writing(): void
    {
        $product = $this->product('ADJ-LOCK-1');
        $this->stock($product, $this->main, 10);

        $locks = $this->captureLockQueries(function () use ($product) {
            $this->post(route('adjustments.store'), [
                'warehouse_id'     => $this->main->id,
                'reference_number' => 'ADJ-LOCKS',
                'adjustment_date'  => today()->toDateString(),
                'reason'           => 'damage',
                'products'         => [$product->id],
                'directions'       => ['decrease'],
                'quantities'       => [2],
            ])->assertSessionHasNoErrors();
        });

        $this->assertCount(2, $locks);
        $this->assertSame([$product->id], $locks[0]['bindings']);
        $this->assertSame([$this->main->id], $locks[1]['bindings']);
    }

    public function test_a_transfer_locks_both_ends(): void
    {
        // A transfer reads stock at the source and writes it at the destination.
        // Locking only the source would let two transfers into the same
        // warehouse interleave, so both ids must appear — and in id order, not
        // in from/to order, or two transfers running in opposite directions
        // between the same pair of warehouses would deadlock.
        $product = $this->product('TR-LOCK-1');
        $this->stock($product, $this->branch, 20);

        $locks = $this->captureLockQueries(function () use ($product) {
            // Sent branch -> main, i.e. higher id to lower id, so a naive
            // implementation would lock in descending order here.
            $this->post(route('transfers.store'), [
                'from_warehouse_id' => $this->branch->id,
                'to_warehouse_id'   => $this->main->id,
                'reference_number'  => 'TR-LOCKS',
                'transfer_date'     => today()->toDateString(),
                'products'          => [$product->id],
                'quantities'        => [5],
            ])->assertSessionHasNoErrors();
        });

        $this->assertCount(2, $locks);
        $this->assertSame([$product->id], $locks[0]['bindings']);
        $this->assertSame([$this->main->id, $this->branch->id], $locks[1]['bindings']);
    }

    // -----------------------------------------------------------------------
    // The check behind the lock is the one that counts
    // -----------------------------------------------------------------------

    public function test_stock_that_disappears_after_the_first_check_is_caught_by_the_second(): void
    {
        // The race, made deterministic. The controller checks stock, opens a
        // transaction, locks, and checks again. Here a competing issue lands
        // between the two checks: Product::retrieved fires when the lock loads
        // the product rows, and the lock is the first thing inside the
        // transaction — everything before it reads through the query builder and
        // hydrates no models.
        //
        // Without the second check this request would issue stock that is no
        // longer there and drive the ledger negative.
        $product = $this->product('RACE-1');
        $this->stock($product, $this->main, 10);

        $raced = false;

        Product::retrieved(function () use ($product, &$raced) {
            if ($raced) {
                return;
            }

            $raced = true;

            // Somebody else just took the lot.
            StockMovement::create([
                'product_id'   => $product->id,
                'warehouse_id' => $this->main->id,
                'type'         => StockMovement::TYPE_OUT,
                'quantity'     => 10,
            ]);
        });

        $response = $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'SO-RACE',
            'issue_date'       => today()->toDateString(),
            'products'         => [$product->id],
            'quantities'       => [10],
        ]);

        $this->assertTrue($raced, 'The competing write never ran, so nothing was actually raced.');

        $response->assertSessionHas('stockErrors');

        // No document, and nothing in the ledger claiming to belong to one.
        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => StockMovement::REFERENCE_STOCK_OUT]);
        $this->assertSame(0, StockMovement::currentStock($product->id, $this->main->id), 'The ledger must not go negative.');
    }

    public function test_the_same_race_is_caught_on_an_adjustment(): void
    {
        $product = $this->product('RACE-2');
        $this->stock($product, $this->main, 6);

        $raced = false;

        Product::retrieved(function () use ($product, &$raced) {
            if ($raced) {
                return;
            }

            $raced = true;

            StockMovement::create([
                'product_id'   => $product->id,
                'warehouse_id' => $this->main->id,
                'type'         => StockMovement::TYPE_OUT,
                'quantity'     => 6,
            ]);
        });

        $this->post(route('adjustments.store'), [
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'ADJ-RACE',
            'adjustment_date'  => today()->toDateString(),
            'reason'           => 'damage',
            'products'         => [$product->id],
            'directions'       => ['decrease'],
            'quantities'       => [6],
        ])->assertSessionHas('stockErrors');

        $this->assertTrue($raced);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => StockMovement::REFERENCE_ADJUSTMENT]);
        $this->assertSame(0, StockMovement::currentStock($product->id, $this->main->id));
    }

    public function test_the_second_check_lets_a_still_valid_document_through(): void
    {
        // The mirror image, so the test above is not just proving that any
        // interference blocks the write: a competing movement that leaves enough
        // stock behind must not stop the document.
        $product = $this->product('RACE-3');
        $this->stock($product, $this->main, 10);

        $raced = false;

        Product::retrieved(function () use ($product, &$raced) {
            if ($raced) {
                return;
            }

            $raced = true;

            // Somebody took 2, leaving 8 against a request for 4.
            StockMovement::create([
                'product_id'   => $product->id,
                'warehouse_id' => $this->main->id,
                'type'         => StockMovement::TYPE_OUT,
                'quantity'     => 2,
            ]);
        });

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'SO-RACE-OK',
            'issue_date'       => today()->toDateString(),
            'products'         => [$product->id],
            'quantities'       => [4],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($raced);
        $this->assertSame(1, StockOut::count());
        $this->assertSame(4, StockMovement::currentStock($product->id, $this->main->id), '10 in, 2 raced away, 4 issued.');
    }

    // -----------------------------------------------------------------------
    // All or nothing
    // -----------------------------------------------------------------------

    public function test_a_failure_halfway_through_leaves_no_trace(): void
    {
        // A document, its item rows and its ledger entries are spread over three
        // tables. If the second movement fails, the first must not survive: a
        // receipt holding one of its two movements would make current stock
        // permanently wrong, and nothing in a ledger-based system can detect it
        // after the fact.
        $first  = $this->product('ATOM-A');
        $second = $this->product('ATOM-B');

        $written = 0;

        StockMovement::creating(function () use (&$written) {
            $written++;

            if ($written === 2) {
                throw new RuntimeException('simulated failure mid-write');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->post(route('stock-in.store'), [
                'supplier_id'      => $this->supplier->id,
                'warehouse_id'     => $this->main->id,
                'reference_number' => 'SI-ATOMIC',
                'receipt_date'     => today()->toDateString(),
                'products'         => [$first->id, $second->id],
                'quantities'       => [5, 7],
                'unit_costs'       => [1, 1],
            ]);

            $this->fail('The simulated failure never surfaced.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated failure mid-write', $e->getMessage());
        }

        $this->assertSame(2, $written, 'The second movement should have been attempted.');

        // Nothing survives: not the receipt, not its item rows, not the one
        // movement that did get written before the failure.
        $this->assertDatabaseCount('stock_ins', 0);
        $this->assertDatabaseCount('stock_in_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->assertSame(0, StockMovement::currentStock($first->id, $this->main->id));
        $this->assertSame(0, StockMovement::currentStock($second->id, $this->main->id));
    }

    public function test_every_ledger_entry_is_written_inside_a_transaction(): void
    {
        // If any path ever creates a movement outside a transaction, the
        // guarantee above quietly stops holding for that document type. Compared
        // against the level outside the request, because this suite already runs
        // each test inside a transaction of its own.
        $product = $this->product('TXN-1');

        $outsideRequest = DB::transactionLevel();
        $levels         = [];

        StockMovement::creating(function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        $this->post(route('stock-in.store'), [
            'supplier_id'      => $this->supplier->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'SI-TXN',
            'receipt_date'     => today()->toDateString(),
            'products'         => [$product->id],
            'quantities'       => [3],
            'unit_costs'       => [1],
        ])->assertSessionHasNoErrors();

        $this->post(route('stock-out.store'), [
            'distributor_id'   => $this->distributor->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'SO-TXN',
            'issue_date'       => today()->toDateString(),
            'products'         => [$product->id],
            'quantities'       => [1],
        ])->assertSessionHasNoErrors();

        $this->post(route('transfers.store'), [
            'from_warehouse_id' => $this->main->id,
            'to_warehouse_id'   => $this->branch->id,
            'reference_number'  => 'TR-TXN',
            'transfer_date'     => today()->toDateString(),
            'products'          => [$product->id],
            'quantities'        => [1],
        ])->assertSessionHasNoErrors();

        $this->post(route('adjustments.store'), [
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'ADJ-TXN',
            'adjustment_date'  => today()->toDateString(),
            'reason'           => 'damage',
            'products'         => [$product->id],
            'directions'       => ['decrease'],
            'quantities'       => [1],
        ])->assertSessionHasNoErrors();

        // Stock In 1 + Stock Out 1 + Transfer 2 (out of one end, into the other)
        // + Adjustment 1.
        $this->assertCount(5, $levels, 'Every document type must have written its ledger entries.');

        foreach ($levels as $level) {
            $this->assertGreaterThan($outsideRequest, $level, 'A ledger entry was written outside a transaction of its own.');
        }
    }

    public function test_two_sequential_issues_cannot_together_exceed_the_stock(): void
    {
        // Not a race, but the invariant a lost race would break: in whatever
        // order the requests arrive, the ledger never goes below zero and the
        // rejected document leaves nothing behind.
        $product = $this->product('SEQ-1');
        $this->stock($product, $this->main, 10);

        $issue = function (int $quantity, string $reference) use ($product) {
            return $this->post(route('stock-out.store'), [
                'distributor_id'   => $this->distributor->id,
                'warehouse_id'     => $this->main->id,
                'reference_number' => $reference,
                'issue_date'       => today()->toDateString(),
                'products'         => [$product->id],
                'quantities'       => [$quantity],
            ]);
        };

        $issue(7, 'SO-SEQ-1')->assertSessionHasNoErrors();
        $issue(7, 'SO-SEQ-2')->assertSessionHas('stockErrors');

        $this->assertSame(3, StockMovement::currentStock($product->id, $this->main->id));
        $this->assertSame(1, StockOut::count(), 'Only the first issue should exist.');
        $this->assertDatabaseMissing('stock_outs', ['reference_number' => 'SO-SEQ-2']);
    }
}
