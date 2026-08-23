<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_category_pages_and_crud_actions_work(): void
    {
        $this->get(route('categories.index'))->assertOk();
        $this->get(route('categories.create'))->assertOk();

        $response = $this->post(route('categories.store'), [
            'name' => 'Electronics',
            'description' => 'Phones, tablets and gadgets',
            'active' => '1',
        ]);

        $category = Category::firstOrFail();

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'name' => 'Electronics',
            'description' => 'Phones, tablets and gadgets',
            'active' => true,
        ]);

        $this->get(route('categories.show', $category))->assertOk()->assertSee('Electronics');
        $this->get(route('categories.edit', $category))->assertOk()->assertSee('Electronics');

        $this->put(route('categories.update', $category), [
            'name' => 'Computer Accessories',
            'description' => 'Keyboards, mice and cables',
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Computer Accessories',
            'description' => 'Keyboards, mice and cables',
            'active' => false,
        ]);

        $this->delete(route('categories.destroy', $category))->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_name_is_required(): void
    {
        $this->post(route('categories.store'), [
            'description' => 'Missing name',
        ])->assertSessionHasErrors('name');
    }
}
