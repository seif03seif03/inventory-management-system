<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $electronics = Category::create(['name' => 'Electronics', 'active' => true]);
        $groceries   = Category::create(['name' => 'Groceries', 'active' => true]);

        Product::create([
            'category_id' => $electronics->id, 'name' => 'iPhone 15', 'sku' => 'PRD-1001',
            'price' => 999, 'minimum_stock' => 5, 'active' => true,
        ]);
        Product::create([
            'category_id' => $groceries->id, 'name' => 'Rice Bag', 'sku' => 'PRD-2002',
            'price' => 20, 'minimum_stock' => 5, 'active' => true,
        ]);

        $this->get(route('products.index', ['category_id' => $electronics->id]))
            ->assertOk()
            ->assertSee('iPhone 15')
            ->assertDontSee('Rice Bag');
    }

    public function test_products_can_be_filtered_by_status(): void
    {
        $category = Category::create(['name' => 'Electronics', 'active' => true]);

        Product::create([
            'category_id' => $category->id, 'name' => 'Active Product', 'sku' => 'PRD-ACT',
            'price' => 100, 'minimum_stock' => 5, 'active' => true,
        ]);
        Product::create([
            'category_id' => $category->id, 'name' => 'Retired Product', 'sku' => 'PRD-RET',
            'price' => 100, 'minimum_stock' => 5, 'active' => false,
        ]);

        $this->get(route('products.index', ['active' => '1']))
            ->assertOk()
            ->assertSee('Active Product')
            ->assertDontSee('Retired Product');

        $this->get(route('products.index', ['active' => '0']))
            ->assertOk()
            ->assertSee('Retired Product')
            ->assertDontSee('Active Product');
    }
}
