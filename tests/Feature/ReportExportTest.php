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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 Task 5 + 6 — PDF and CSV export of every report.
 *
 * The property that matters most here is that an export honours the same
 * filters as the page it was launched from: a filtered screen that exports
 * unfiltered data is worse than no export, because the reader cannot tell.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private Product $widget;
    private Product $gadget;
    private Warehouse $main;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));

        $category = Category::create(['name' => 'Electronics', 'active' => true]);

        $this->widget = Product::create([
            'category_id' => $category->id, 'name' => 'Exportable Widget', 'sku' => 'EXP-1',
            'price' => 10, 'minimum_stock' => 50, 'active' => true,
        ]);
        $this->gadget = Product::create([
            'category_id' => $category->id, 'name' => 'Excluded Gadget', 'sku' => 'EXC-1',
            'price' => 20, 'minimum_stock' => 50, 'active' => true,
        ]);

        $this->main = Warehouse::create(['name' => 'Main Warehouse', 'active' => true]);

        foreach ([$this->widget, $this->gadget] as $product) {
            StockMovement::create([
                'product_id'   => $product->id,
                'warehouse_id' => $this->main->id,
                'type'         => StockMovement::TYPE_IN,
                'quantity'     => 5,
            ]);
        }
    }

    /** Pull a streamed download's body out for inspection. */
    private function csvBody(string $url): string
    {
        $response = $this->get($url);
        $response->assertOk();

        return $response->streamedContent();
    }

    // -----------------------------------------------------------------------
    // CSV
    // -----------------------------------------------------------------------

    public function test_stock_report_csv_downloads_with_headings_and_rows(): void
    {
        $body = $this->csvBody(route('reports.stock.export', ['format' => 'csv']));

        $this->assertStringContainsString('Product,SKU,Category,Warehouse', $body);
        $this->assertStringContainsString('Exportable Widget', $body);
        $this->assertStringContainsString('Excluded Gadget', $body);
    }

    public function test_csv_export_honours_the_active_filters(): void
    {
        $body = $this->csvBody(route('reports.stock.export', [
            'format'     => 'csv',
            'product_id' => $this->widget->id,
        ]));

        $this->assertStringContainsString('Exportable Widget', $body);
        $this->assertStringNotContainsString('Excluded Gadget', $body);
    }

    public function test_csv_export_is_not_limited_to_one_page_of_results(): void
    {
        // The screen paginates at 20; an export must contain everything that
        // matched, not just the visible page.
        $category = Category::create(['name' => 'Bulk', 'active' => true]);

        for ($i = 0; $i < 25; $i++) {
            $product = Product::create([
                'category_id' => $category->id, 'name' => "Bulk Product {$i}", 'sku' => "BULK-{$i}",
                'price' => 1, 'minimum_stock' => 0, 'active' => true,
            ]);

            StockMovement::create([
                'product_id'   => $product->id,
                'warehouse_id' => $this->main->id,
                'type'         => StockMovement::TYPE_IN,
                'quantity'     => 1,
            ]);
        }

        $body = $this->csvBody(route('reports.stock.export', [
            'format'      => 'csv',
            'category_id' => $category->id,
        ]));

        $this->assertStringContainsString('Bulk Product 0', $body);
        $this->assertStringContainsString('Bulk Product 24', $body);
    }

    public function test_csv_starts_with_a_utf8_bom_so_excel_reads_arabic_correctly(): void
    {
        $body = $this->csvBody(route('reports.stock.export', ['format' => 'csv']));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
    }

    public function test_every_report_exports_as_csv(): void
    {
        $this->seedDocuments();

        foreach ([
            'reports.stock.export',
            'reports.low-stock.export',
            'reports.movements.export',
            'reports.stock-in.export',
            'reports.stock-out.export',
        ] as $route) {
            $response = $this->get(route($route, ['format' => 'csv']));
            $response->assertOk();
            $this->assertNotSame('', $response->streamedContent(), "{$route} produced an empty CSV.");
        }
    }

    // -----------------------------------------------------------------------
    // PDF
    // -----------------------------------------------------------------------

    public function test_every_report_exports_as_pdf(): void
    {
        $this->seedDocuments();

        foreach ([
            'reports.stock.export',
            'reports.low-stock.export',
            'reports.movements.export',
            'reports.stock-in.export',
            'reports.stock-out.export',
        ] as $route) {
            $response = $this->get(route($route, ['format' => 'pdf']));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');

            // A real PDF starts with %PDF — proves dompdf actually rendered
            // rather than returning an error page with a PDF content type.
            // dompdf returns a plain response (not streamed), so read content().
            $this->assertStringStartsWith('%PDF', $response->content());
        }
    }

    public function test_an_unknown_export_format_is_rejected(): void
    {
        // {format} is route-constrained, so this 404s instead of silently
        // falling through to one of the real formats.
        $this->get('/reports/stock/export/exe')->assertNotFound();
    }

    public function test_an_export_matching_nothing_still_renders(): void
    {
        $empty = Product::create([
            'category_id' => Category::create(['name' => 'Empty', 'active' => true])->id,
            'name' => 'Never Moved', 'sku' => 'NM-1', 'price' => 1, 'minimum_stock' => 0, 'active' => true,
        ]);

        $this->get(route('reports.stock.export', ['format' => 'pdf', 'product_id' => $empty->id]))
            ->assertOk();

        $body = $this->csvBody(route('reports.stock.export', ['format' => 'csv', 'product_id' => $empty->id]));
        $this->assertStringContainsString('Product,SKU', $body);
        $this->assertStringNotContainsString('Never Moved', $body);
    }

    public function test_exports_require_authentication(): void
    {
        auth()->logout();

        $this->get(route('reports.stock.export', ['format' => 'csv']))
            ->assertRedirect(route('login'));
    }

    private function seedDocuments(): void
    {
        $supplier    = Supplier::create(['name' => 'Acme', 'active' => true]);
        $distributor = Distributor::create(['name' => 'Nile', 'active' => true]);

        $receipt = StockIn::create([
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'GRN-9001',
            'receipt_date'     => '2026-08-01',
            'status'           => 'completed',
        ]);
        $receipt->items()->create(['product_id' => $this->widget->id, 'quantity' => 10, 'unit_cost' => 10]);

        $issue = StockOut::create([
            'distributor_id'   => $distributor->id,
            'warehouse_id'     => $this->main->id,
            'reference_number' => 'ISS-9001',
            'issue_date'       => '2026-08-02',
            'status'           => 'completed',
        ]);
        $issue->items()->create(['product_id' => $this->widget->id, 'quantity' => 2]);
    }
}
