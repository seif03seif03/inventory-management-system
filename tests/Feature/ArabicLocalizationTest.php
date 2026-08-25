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
 * Arabic localisation.
 *
 * The requirement is that no English UI text leaks through when the app is in
 * Arabic. Two things are checked: that every __() key used anywhere in the code
 * has an Arabic entry (a structural guarantee that scales as pages are added),
 * and that real pages actually render Arabic end to end.
 */
class ArabicLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::create(['name' => 'Admin'])->id,
        ]);

        $this->actingAs($this->admin);
        session(['locale' => 'ar']);
    }

    /** Every literal __('...') key found in views and app code. */
    private function translationKeysUsedInCode(): array
    {
        $keys = [];

        foreach ([resource_path('views'), app_path()] as $base) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $m);

                foreach ($m[1] as $key) {
                    $key = str_replace("\\'", "'", $key);

                    // Skip validation rule strings that share the __() shape.
                    if ($key !== '' && ! str_contains($key, '|')) {
                        $keys[$key] = true;
                    }
                }
            }
        }

        return array_keys($keys);
    }

    public function test_every_translation_key_used_in_the_code_has_an_arabic_entry(): void
    {
        $arabic  = json_decode(file_get_contents(lang_path('ar.json')), true);
        $missing = array_values(array_diff($this->translationKeysUsedInCode(), array_keys($arabic)));

        $this->assertSame([], $missing,
            "These keys would render in English when the app is in Arabic:\n  " . implode("\n  ", $missing));
    }

    public function test_no_arabic_translation_is_left_as_its_english_source(): void
    {
        // A key copied verbatim into ar.json still renders English.
        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true);

        $untranslated = [];

        foreach ($arabic as $key => $value) {
            // Symbols and acronyms legitimately stay identical (PDF, CSV, SKU).
            if ($key === $value && preg_match('/\p{Ll}/u', $key)) {
                $untranslated[] = $key;
            }
        }

        $this->assertSame([], $untranslated,
            "Copied verbatim instead of translated:\n  " . implode("\n  ", $untranslated));
    }

    public function test_the_shell_renders_arabic_and_switches_to_rtl(): void
    {
        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);

        // Sidebar labels, in Arabic.
        $response->assertSee('لوحة التحكم', false);   // Dashboard
        $response->assertSee('المنتجات', false);      // Products
        $response->assertSee('التسويات', false);      // Adjustments
        $response->assertSee('سجل النشاط', false);    // Activity Logs
    }

    public function test_english_ui_labels_do_not_appear_on_key_pages(): void
    {
        $this->seedData();

        // Words that would only be present if a label had escaped translation.
        // Deliberately narrow — the pages legitimately contain English product
        // names, SKUs and technical attributes.
        $englishLabels = [
            'Add Product', 'Save Product', 'Contact Information',
            'Current Stock', 'Minimum Stock', 'Reference Number',
            'Adjustment Details', 'Created By',
        ];

        $pages = [
            route('products.index'),
            route('products.create'),
            route('categories.index'),
            route('suppliers.index'),
            route('distributors.index'),
            route('warehouses.index'),
            route('stock-in.index'),
            route('stock-out.index'),
            route('transfers.index'),
            route('adjustments.index'),
            route('adjustments.create'),
            route('stock-movements.index'),
            route('reports.index'),
            route('reports.stock'),
            route('users.index'),
            route('activity-logs.index'),
            route('profile.edit'),
        ];

        foreach ($pages as $url) {
            $response = $this->get($url)->assertOk();

            foreach ($englishLabels as $label) {
                $response->assertDontSee('>' . $label . '<', false);
            }
        }
    }

    public function test_validation_messages_are_arabic(): void
    {
        // A custom message from the controller...
        $this->post(route('products.store'), [])
            ->assertSessionHasErrors('name');

        $errors = session('errors')->getBag('default');

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $errors->first('name'),
            'A validation message rendered without any Arabic characters.');
    }

    public function test_flash_messages_are_arabic(): void
    {
        $category = Category::create(['name' => 'فئة', 'active' => true]);

        $this->delete(route('categories.destroy', $category));

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', session('success'));
    }

    public function test_dates_render_with_arabic_month_names(): void
    {
        $this->seedData();

        // translatedFormat() renders the month in the active locale; format()
        // would emit 'Aug' regardless.
        $this->get(route('stock-in.index'))
            ->assertOk()
            ->assertDontSee('>18 Aug 2026<', false);
    }

    public function test_english_locale_still_renders_english(): void
    {
        // The Arabic work must not have broken the default locale.
        session(['locale' => 'en']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Dashboard');
    }

    private function seedData(): void
    {
        $category  = Category::create(['name' => 'إلكترونيات', 'active' => true]);
        $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي', 'active' => true]);

        $product = Product::create([
            'category_id' => $category->id, 'name' => 'هاتف', 'sku' => 'SKU-1',
            'price' => 100, 'minimum_stock' => 5, 'active' => true,
        ]);

        StockMovement::create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_IN, 'quantity' => 50,
        ]);

        $receipt = StockIn::create([
            'supplier_id'      => Supplier::create(['name' => 'مورد', 'active' => true])->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'GRN-1',
            'receipt_date'     => '2026-08-18',
            'status'           => 'completed',
        ]);
        $receipt->items()->create(['product_id' => $product->id, 'quantity' => 50, 'unit_cost' => 10]);

        $issue = StockOut::create([
            'distributor_id'   => Distributor::create(['name' => 'موزع', 'active' => true])->id,
            'warehouse_id'     => $warehouse->id,
            'reference_number' => 'ISS-1',
            'issue_date'       => '2026-08-20',
            'status'           => 'completed',
        ]);
        $issue->items()->create(['product_id' => $product->id, 'quantity' => 5]);
    }
}
