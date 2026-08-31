<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_warehouse_pages_and_crud_actions_work(): void
    {
        $this->get(route('warehouses.index'))->assertOk();
        $this->get(route('warehouses.create'))->assertOk();

        $response = $this->post(route('warehouses.store'), [
            'name' => 'Main Warehouse',
            'location' => 'Riyadh',
            'description' => 'Primary storage depot',
            'active' => '1',
        ]);

        $warehouse = Warehouse::firstOrFail();

        $response->assertRedirect(route('warehouses.index'));
        $this->assertDatabaseHas('warehouses', [
            'name' => 'Main Warehouse',
            'location' => 'Riyadh',
            'description' => 'Primary storage depot',
            'active' => true,
        ]);

        $this->get(route('warehouses.show', $warehouse))->assertOk()->assertSee('Main Warehouse');
        $this->get(route('warehouses.edit', $warehouse))->assertOk()->assertSee('Main Warehouse');

        $this->put(route('warehouses.update', $warehouse), [
            'name' => 'Updated Warehouse',
            'location' => 'Jeddah',
            'description' => 'Secondary storage depot',
            'active' => '0',
        ])->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Updated Warehouse',
            'location' => 'Jeddah',
            'description' => 'Secondary storage depot',
            'active' => false,
        ]);

        $this->delete(route('warehouses.destroy', $warehouse))->assertRedirect(route('warehouses.index'));
        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_warehouse_name_is_required(): void
    {
        $this->post(route('warehouses.store'), [
            'location' => 'Alexandria',
        ])->assertSessionHasErrors('name');
    }
}
