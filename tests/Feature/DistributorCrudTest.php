<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_distributor_pages_and_crud_actions_work(): void
    {
        $this->get(route('distributors.index'))->assertOk();
        $this->get(route('distributors.create'))->assertOk();

        $response = $this->post(route('distributors.store'), [
            'name' => 'Distributor Co',
            'phone' => '123456789',
            'email' => 'distributor@example.com',
            'address' => '123 Distributor St',
            'active' => '1',
        ]);

        $distributor = Distributor::firstOrFail();

        $response->assertRedirect(route('distributors.index'));
        $this->assertDatabaseHas('distributors', [
            'name' => 'Distributor Co',
            'phone' => '123456789',
            'email' => 'distributor@example.com',
            'address' => '123 Distributor St',
            'active' => true,
        ]);

        $this->get(route('distributors.show', $distributor))->assertOk()->assertSee('Distributor Co');
        $this->get(route('distributors.edit', $distributor))->assertOk()->assertSee('Distributor Co');

        $this->put(route('distributors.update', $distributor), [
            'name' => 'Updated Distributor Co',
            'phone' => '987654321',
            'email' => 'distributor-updated@example.com',
            'address' => '456 Updated St',
            'active' => '0',
        ])->assertRedirect(route('distributors.index'));

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'name' => 'Updated Distributor Co',
            'phone' => '987654321',
            'email' => 'distributor-updated@example.com',
            'address' => '456 Updated St',
            'active' => false,
        ]);

        $this->delete(route('distributors.destroy', $distributor))->assertRedirect(route('distributors.index'));
        $this->assertDatabaseMissing('distributors', ['id' => $distributor->id]);
    }

    public function test_distributor_name_is_required(): void
    {
        $this->post(route('distributors.store'), [
            'phone' => '123456789',
        ])->assertSessionHasErrors('name');
    }
}
