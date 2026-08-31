<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_supplier_pages_and_crud_actions_work(): void
    {
        $this->get(route('suppliers.index'))->assertOk();
        $this->get(route('suppliers.create'))->assertOk();

        $response = $this->post(route('suppliers.store'), [
            'name' => 'Supplier Co',
            'phone' => '123456789',
            'email' => 'supplier@example.com',
            'address' => '123 Supplier St',
            'active' => '1',
        ]);

        $supplier = Supplier::firstOrFail();

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Supplier Co',
            'phone' => '123456789',
            'email' => 'supplier@example.com',
            'address' => '123 Supplier St',
            'active' => true,
        ]);

        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Supplier Co');
        $this->get(route('suppliers.edit', $supplier))->assertOk()->assertSee('Supplier Co');

        $this->put(route('suppliers.update', $supplier), [
            'name' => 'Updated Supplier Co',
            'phone' => '987654321',
            'email' => 'supplier-updated@example.com',
            'address' => '456 Updated St',
            'active' => '0',
        ])->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier Co',
            'phone' => '987654321',
            'email' => 'supplier-updated@example.com',
            'address' => '456 Updated St',
            'active' => false,
        ]);

        $this->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_name_is_required(): void
    {
        $this->post(route('suppliers.store'), [
            'phone' => '123456789',
        ])->assertSessionHasErrors('name');
    }
}
