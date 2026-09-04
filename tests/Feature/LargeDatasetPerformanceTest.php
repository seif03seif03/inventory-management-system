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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Does the application still hold up when the tables are not tiny?
 *
 * Every other feature test works with a handful of rows — which is exactly the
 * size at which an N+1 query, an unpaginated page and a full table scan all
 * look perfectly healthy. This file seeds thousands of ledger rows and then asks
 * the questions that only get harder with data:
 *
 *   - does the query COUNT stay flat as rows are added? An N+1 does not.
 *   - does a page ever hand the view more rows than it means to render?
 *   - are the numbers still right once there is enough history to get them wrong?
 *
 * Query counts, not stopwatches, carry nearly every assertion here. Wall-clock
 * time on a shared CI runner is noise, but "this page runs the same number of
 * queries whether the catalogue holds 25 products or 225" is a fact that either
 * holds or does not. There is one deliberately loose timing test at the end,
 * and it is a smoke alarm rather than a benchmark.
 *
 * The data is inserted with the query builder rather than factories: 3,000
 * models with faker-generated fields would make this file slower than the rest
 * of the suite combined, and nothing here depends on the values being varied.
 */
class LargeDatasetPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Warehouse $main;
    private Warehouse $branch;
    private Warehouse $depot;
    private Supplier $supplier;
    private Distributor $distributor;

    /** Stock left in each warehouse by one round of seedLedger(). */
    private const STOCK_PER_ROUND = 60;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::create(['name' => 'Admin'])->id,
        ]);

        $this->category    = Category::create(['name' => 'Bulk Goods', 'active' => true]);
        $this->main        = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);
        $this->branch      = Warehouse::create(['name' => 'North Branch', 'active' => true]);
        $this->depot       = Warehouse::create(['name' => 'South Depot', 'active' => true]);
        $this->supplier    = Supplier::create(['name' => 'Bulk Supply Co', 'active' => true]);
        $this->distributor = Distributor::create(['name' => 'Bulk Retail', 'active' => true]);

        $this->actingAs($this->admin);

        // The authenticated user object survives from one request to the next
        // within a single test, so its role relation gets lazily loaded by
        // whichever request happens to run first and is cached for the rest.
        // Loading it here keeps that one-off hydration out of the query counts
        // below, which are about the cost of a page, not about warming up an
        // object the test itself is holding.
        $this->admin->load('role');
    }

    // -----------------------------------------------------------------------
    // Seeding
    // -----------------------------------------------------------------------

    /**
     * Insert $count products and return their ids.
     *
     * Callable more than once: ids continue from wherever the table left off, so
     * the unique SKU and barcode indexes are never hit.
     *
     * @return array<int, int>
     */
    private function seedProducts(int $count): array
    {
        $highWater = (int) (Product::max('id') ?? 0);
        $now       = now()->toDateTimeString();
        $rows      = [];

        for ($i = 1; $i <= $count; $i++) {
            $n = $highWater + $i;

            $rows[] = [
                'category_id'   => $this->category->id,
                'name'          => sprintf('Bulk Product %04d', $n),
                'sku'           => sprintf('BULK-%04d', $n),
                'barcode'       => sprintf('9%012d', $n),
                'description'   => null,
                'price'         => 10,
                'minimum_stock' => 5,
                'active'        => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        return Product::where('id', '>', $highWater)->orderBy('id')->pluck('id')->all();
    }

    /**
     * Write $rounds rounds of movements for every given product in all three
     * warehouses. One round is IN 50, IN 30, OUT 20 — three rows leaving
     * STOCK_PER_ROUND on the shelf, so the expected stock after seeding is
     * arithmetic rather than a query.
     *
     * @param  array<int, int>  $productIds
     * @return int  how many movement rows were written
     */
    private function seedLedger(array $productIds, int $rounds = 1): int
    {
        $warehouseIds = [$this->main->id, $this->branch->id, $this->depot->id];
        $now          = now()->toDateTimeString();
        $rows         = [];

        // Every row carries a reference so the dashboard chart (which ignores
        // movements with no document behind them) has something to draw. Two
        // fixed reference ids keep the chart's per-document grouping small —
        // this file is measuring the ledger, not the chart legend.
        foreach ($productIds as $productId) {
            foreach ($warehouseIds as $warehouseId) {
                for ($round = 0; $round < $rounds; $round++) {
                    foreach ([[StockMovement::TYPE_IN, 50, 1], [StockMovement::TYPE_IN, 30, 1], [StockMovement::TYPE_OUT, 20, 2]] as [$type, $quantity, $reference]) {
                        $rows[] = [
                            'product_id'     => $productId,
                            'warehouse_id'   => $warehouseId,
                            'type'           => $type,
                            'quantity'       => $quantity,
                            'reference_type' => $type === StockMovement::TYPE_IN
                                ? StockMovement::REFERENCE_STOCK_IN
                                : StockMovement::REFERENCE_STOCK_OUT,
                            'reference_id'   => $reference,
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('stock_movements')->insert($chunk);
        }

        return count($rows);
    }

    // -----------------------------------------------------------------------
    // Measuring
    // -----------------------------------------------------------------------

    /**
     * Run $work and return the SQL of every query it caused.
     *
     * @return array<int, string>
     */
    private function queriesDuring(callable $work): array
    {
        $sql = [];

        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $work();

        // Returned by value, so a listener left over from an earlier call in the
        // same test cannot alter a result that has already been handed back.
        return $sql;
    }

    /** @param array<int, string> $queries */
    private function assertSomeQuery(array $queries, string $startsWith, string $message): void
    {
        foreach ($queries as $sql) {
            if (str_starts_with($sql, $startsWith)) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail($message . PHP_EOL . 'Queries seen:' . PHP_EOL . implode(PHP_EOL, $queries));
    }

    // -----------------------------------------------------------------------
    // Query counts must not grow with the data
    // -----------------------------------------------------------------------

    public function test_the_products_index_runs_the_same_queries_whatever_the_catalogue_size(): void
    {
        $small = $this->seedProducts(25);
        $this->seedLedger($small);

        $before = $this->queriesDuring(fn () => $this->get(route('products.index'))->assertOk());

        // Ten times the catalogue, ten times the ledger.
        $this->seedLedger($this->seedProducts(225));

        $after = $this->queriesDuring(fn () => $this->get(route('products.index'))->assertOk());

        $this->assertSame(
            count($before),
            count($after),
            'The products index runs one query per row somewhere: its query count grew with the catalogue.'
        );
    }

    public function test_the_products_index_hands_the_view_one_page_of_rows(): void
    {
        $this->seedLedger($this->seedProducts(120));

        $response = $this->get(route('products.index'))->assertOk();

        $products = $response->viewData('products');

        $this->assertCount(20, $products, 'A page must render a page, not the whole table.');
        $this->assertSame(120, $products->total());

        // The stock map is built for the current page only. If it covered every
        // product it would be a second full scan of the ledger per page view.
        $this->assertCount(20, $response->viewData('productStocks'));
    }

    public function test_the_dashboard_runs_the_same_queries_whatever_the_ledger_size(): void
    {
        $products = $this->seedProducts(40);
        $this->seedLedger($products);

        $before = $this->queriesDuring(fn () => $this->get(route('dashboard'))->assertOk());

        // Four more rounds over the same products: same rows on screen, five
        // times the history behind them.
        $this->seedLedger($products, 4);

        $after = $this->queriesDuring(fn () => $this->get(route('dashboard'))->assertOk());

        $this->assertSame(
            count($before),
            count($after),
            'The dashboard reads the ledger row by row somewhere.'
        );
    }

    public function test_the_stock_report_runs_the_same_queries_whatever_the_ledger_size(): void
    {
        $products = $this->seedProducts(40);
        $this->seedLedger($products);

        $before = $this->queriesDuring(fn () => $this->get(route('reports.stock'))->assertOk());

        $this->seedLedger($products, 4);

        $after = $this->queriesDuring(fn () => $this->get(route('reports.stock'))->assertOk());

        $this->assertSame(count($before), count($after));
    }

    public function test_the_movements_report_does_not_query_once_per_row(): void
    {
        // Each row shows its product and its warehouse. Without eager loading
        // that is two extra queries per row — invisible with three movements on
        // screen, 40 extra queries with a full page of twenty.
        $products = $this->seedProducts(30);
        $this->seedLedger($products);

        $before = $this->queriesDuring(fn () => $this->get(route('reports.movements'))->assertOk());

        $this->seedLedger($products, 4);

        $after = $this->queriesDuring(fn () => $this->get(route('reports.movements'))->assertOk());

        $this->assertSame(
            count($before),
            count($after),
            'The movements report is loading its product or warehouse per row.'
        );
    }

    public function test_the_movements_export_does_not_query_once_per_row(): void
    {
        // An export deliberately skips pagination, so an N+1 here is not twenty
        // extra queries but one per movement in the whole filtered set.
        $products = $this->seedProducts(30);
        $rows     = $this->seedLedger($products);

        $before = $this->queriesDuring(function () {
            $this->get(route('reports.movements.export', ['format' => 'csv']))->assertOk()->streamedContent();
        });

        $moreRows = $this->seedLedger($products, 4);

        $after = $this->queriesDuring(function () {
            $this->get(route('reports.movements.export', ['format' => 'csv']))->assertOk()->streamedContent();
        });

        $this->assertGreaterThan($rows, $moreRows, 'The second export must really have more to export.');
        $this->assertSame(
            count($before),
            count($after),
            'The movements export queries per exported row.'
        );
    }

    // -----------------------------------------------------------------------
    // The shape of the queries themselves
    // -----------------------------------------------------------------------

    public function test_the_dashboard_reads_its_ledger_totals_in_a_single_pass(): void
    {
        $this->seedLedger($this->seedProducts(10));

        $queries = $this->queriesDuring(fn () => $this->get(route('dashboard'))->assertOk());

        $totals = array_values(array_filter(
            $queries,
            fn (string $sql) => str_contains($sql, 'as total_in')
        ));

        // Four figures — all-time IN, all-time OUT, today's IN, today's OUT —
        // come out of one scan of the ledger. Written as four ->sum() calls they
        // would be four scans of the largest table in the schema.
        $this->assertCount(1, $totals, 'The dashboard totals should be one conditional-aggregate query.');
        $this->assertStringContainsString('as total_out', $totals[0]);
        $this->assertStringContainsString('as in_today', $totals[0]);
        $this->assertStringContainsString('as out_today', $totals[0]);
    }

    public function test_the_dashboard_asks_for_todays_figures_in_a_form_an_index_can_serve(): void
    {
        $this->seedLedger($this->seedProducts(10));

        $queries = $this->queriesDuring(fn () => $this->get(route('dashboard'))->assertOk());

        $totals = array_values(array_filter(
            $queries,
            fn (string $sql) => str_contains($sql, 'as in_today')
        ));

        $this->assertCount(1, $totals);

        // whereDate() would compile to a function call around created_at, and a
        // column wrapped in a function cannot be answered from an index — so
        // "how much came in today" would scan all of history. A half-open range
        // selects exactly the same rows and leaves the column bare.
        $this->assertStringContainsString('created_at >= ?', $totals[0]);
        $this->assertStringContainsString('created_at < ?', $totals[0]);
    }

    public function test_dropdown_queries_fetch_only_the_columns_the_page_renders(): void
    {
        $this->seedLedger($this->seedProducts(200));

        $queries = $this->queriesDuring(fn () => $this->get(route('stock-in.create'))->assertOk());

        // Every product in the catalogue goes into the form's <option> list. The
        // options render the name, the SKU and a barcode data attribute, so the
        // description and price have no business being loaded 200 times.
        $this->assertSomeQuery(
            $queries,
            'select "id", "name", "sku", "barcode" from "products"',
            'The product dropdown is loading whole product rows.'
        );

        $this->assertSomeQuery(
            $queries,
            'select "id", "name" from "warehouses"',
            'The warehouse dropdown is loading whole warehouse rows.'
        );

        $this->assertSomeQuery(
            $queries,
            'select "id", "name" from "suppliers"',
            'The supplier dropdown is loading whole supplier rows.'
        );

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('select * from "products"', $sql);
            $this->assertStringNotContainsString('select * from "warehouses"', $sql);
            $this->assertStringNotContainsString('select * from "suppliers"', $sql);
        }
    }

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    public function test_the_ledger_carries_the_composite_indexes_the_reports_need(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Index introspection here is written against SQLite.');
        }

        $indexes = collect(DB::select("PRAGMA index_list('stock_movements')"))
            ->pluck('name')
            ->all();

        // Named in the add_security_and_reporting_indexes migration. Each one
        // matches a query this application actually issues:
        //   product+warehouse+type -> the current-stock calculation
        //   type+created_at        -> the dashboard's daily and 30-day figures
        //   warehouse+created_at   -> the movements report filtered by warehouse
        foreach ([
            'movements_product_warehouse_type_idx',
            'movements_type_created_idx',
            'movements_warehouse_created_idx',
        ] as $index) {
            $this->assertContains($index, $indexes, "The {$index} index is missing from stock_movements.");
        }
    }

    public function test_reading_one_products_stock_searches_an_index_instead_of_scanning(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('EXPLAIN QUERY PLAN output here is SQLite specific.');
        }

        $products = $this->seedProducts(200);
        $this->seedLedger($products, 2);

        $query = StockMovement::where('product_id', $products[0])
            ->where('warehouse_id', $this->main->id)
            ->where('type', StockMovement::TYPE_IN)
            ->selectRaw('SUM(quantity) as total');

        $plan = collect(DB::select('EXPLAIN QUERY PLAN ' . $query->toSql(), $query->getBindings()))
            ->pluck('detail')
            ->implode(' | ');

        $this->assertStringContainsString(
            'USING INDEX',
            $plan,
            "Reading one product's stock is scanning the whole ledger. Plan was: {$plan}"
        );
        $this->assertStringNotContainsString('SCAN stock_movements', $plan, "Plan was: {$plan}");
    }

    // -----------------------------------------------------------------------
    // Still correct at scale
    // -----------------------------------------------------------------------

    public function test_the_stock_figures_are_still_right_with_thousands_of_movements(): void
    {
        $products = $this->seedProducts(150);
        $written  = $this->seedLedger($products, 2);

        // 150 products x 3 warehouses x 2 rounds x 3 rows.
        $this->assertSame(2700, $written);
        $this->assertDatabaseCount('stock_movements', 2700);

        $expectedPerWarehouse = 2 * self::STOCK_PER_ROUND;

        $this->assertSame(
            $expectedPerWarehouse,
            StockMovement::currentStock($products[0], $this->main->id)
        );

        $this->assertSame(
            3 * $expectedPerWarehouse,
            StockMovement::totalStockAllWarehouses($products[0]),
            'Summing one product across three warehouses.'
        );

        // The same arithmetic the other way round: the grouped summary query
        // must produce one row per (product, warehouse) and agree with the
        // per-pair reads above.
        $rows = StockMovement::currentStockRows()->get();

        $this->assertCount(150 * 3, $rows);
        $this->assertSame([$expectedPerWarehouse], $rows->pluck('current_stock')->map(fn ($v) => (int) $v)->unique()->values()->all());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalStock', 150 * 3 * $expectedPerWarehouse);
    }

    public function test_a_full_export_writes_every_matching_row(): void
    {
        $products = $this->seedProducts(60);
        $this->seedLedger($products);

        $csv = $this->get(route('reports.stock.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        // One heading row plus one row per (product, warehouse) pair — the
        // export ignores pagination on purpose, so all 180 must be there.
        $lines = array_filter(explode("\n", trim($csv)));

        $this->assertSame(1 + (60 * 3), count($lines));

        // fputcsv quotes any field holding a space, so the product name arrives
        // quoted and the SKU beside it does not.
        $this->assertStringContainsString('"Bulk Product 0001",BULK-0001', $csv);
        $this->assertStringContainsString('"Bulk Product 0060",BULK-0060', $csv);
    }

    // -----------------------------------------------------------------------
    // A smoke alarm, not a benchmark
    // -----------------------------------------------------------------------

    public function test_the_dashboard_still_answers_promptly_with_a_large_ledger(): void
    {
        $this->seedLedger($this->seedProducts(200), 2);

        $this->assertDatabaseCount('stock_movements', 3600);

        $start = microtime(true);

        $this->get(route('dashboard'))->assertOk();

        $elapsed = microtime(true) - $start;

        // Five seconds is far more than this page needs and far less than a
        // reintroduced N+1 or a full scan per row would take. The budget is
        // deliberately loose: a tight one would fail on a busy machine and
        // teach everyone to ignore this test.
        $this->assertLessThan(
            5.0,
            $elapsed,
            sprintf('The dashboard took %.2fs with 3,600 movements.', $elapsed)
        );
    }
}
