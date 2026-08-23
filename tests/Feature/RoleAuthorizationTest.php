<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Admin']);
        $managerRole = Role::create(['name' => 'Warehouse Manager']);
        $employeeRole = Role::create(['name' => 'Warehouse Employee']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->manager = User::factory()->create(['role_id' => $managerRole->id]);
        $this->employee = User::factory()->create(['role_id' => $employeeRole->id]);
    }

    public function test_admin_can_access_user_management(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));
        $response->assertOk();
    }

    public function test_manager_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->manager)->get(route('users.index'));
        $response->assertStatus(403);
    }

    public function test_employee_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->employee)->get(route('users.index'));
        $response->assertStatus(403);
    }

    public function test_all_roles_can_access_dashboard_and_inventory_pages(): void
    {
        foreach ([$this->admin, $this->manager, $this->employee] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $this->actingAs($user)->get(route('products.index'))->assertOk();
            $this->actingAs($user)->get(route('reports.index'))->assertOk();
        }
    }
}
