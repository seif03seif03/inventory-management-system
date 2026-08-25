<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBarcodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole   = Role::create(['name' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->category = Category::create(['name' => 'Test Category', 'active' => true]);
    }

    private function baseProductData(array $overrides = []): array
    {
        return array_merge([
            'category_id'   => $this->category->id,
            'name'          => 'iPhone 15',
            'sku'           => 'PRD-1001',
            'barcode'       => '8901234567890',
            'price'         => 999,
            'minimum_stock' => 5,
            'active'        => 1,
        ], $overrides);
    }

    public function test_product_can_be_created_with_a_barcode(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('products.store'), $this->baseProductData());

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['barcode' => '8901234567890']);
    }

    public function test_product_can_be_created_without_a_barcode(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('products.store'), $this->baseProductData(['barcode' => null, 'sku' => 'PRD-1002']));

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['sku' => 'PRD-1002', 'barcode' => null]);
    }

    public function test_two_products_can_both_have_no_barcode(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->baseProductData(['barcode' => null, 'sku' => 'PRD-A']));
        $response = $this->actingAs($this->admin)->post(route('products.store'), $this->baseProductData(['barcode' => null, 'sku' => 'PRD-B']));

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseCount('products', 2);
    }

    public function test_duplicate_barcode_is_rejected(): void
    {
        Product::create($this->baseProductData(['sku' => 'PRD-ORIG']));

        $response = $this->actingAs($this->admin)->post(route('products.store'), $this->baseProductData(['sku' => 'PRD-DUPE']));

        $response->assertSessionHasErrors('barcode');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_updating_a_product_without_changing_its_own_barcode_succeeds(): void
    {
        $product = Product::create($this->baseProductData());

        $response = $this->actingAs($this->admin)->put(route('products.update', $product), $this->baseProductData([
            'name' => 'iPhone 15 Pro',
        ]));

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'iPhone 15 Pro', 'barcode' => '8901234567890']);
    }

    public function test_product_search_finds_by_barcode(): void
    {
        Product::create($this->baseProductData());
        Product::create($this->baseProductData(['sku' => 'PRD-OTHER', 'barcode' => '1112223334445', 'name' => 'Other Product']));

        $response = $this->actingAs($this->admin)->get(route('products.index', ['search' => '8901234567890']));

        $response->assertOk();
        $response->assertSee('iPhone 15');
        $response->assertDontSee('Other Product');
    }

    public function test_searching_an_unknown_barcode_reports_no_results_instead_of_erroring(): void
    {
        Product::create($this->baseProductData());

        $this->actingAs($this->admin)
            ->get(route('products.index', ['search' => '999999999999']))
            ->assertOk()
            ->assertSee('No products found.')
            ->assertDontSee('iPhone 15');
    }

    public function test_the_scan_field_exposes_barcodes_on_every_stock_document_form(): void
    {
        // The scanner is keyboard input matched against each option's
        // data-barcode, so the forms must carry that attribute for the lookup
        // to work at all. No package, no camera, no extra request.
        Product::create($this->baseProductData());

        foreach (['stock-in.create', 'stock-out.create', 'transfers.create'] as $route) {
            $this->actingAs($this->admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee('barcodeScanInput')
                ->assertSee('data-barcode="8901234567890"', false);
        }
    }
}
