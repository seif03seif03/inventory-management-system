<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $adminRole;
    private Role $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin']);
        $this->employeeRole = Role::create(['name' => 'Warehouse Employee']);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_admin_can_view_create_user_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.create'));

        $response->assertOk();
        $response->assertSee('User Information');
    }

    public function test_admin_can_create_new_user(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role_id' => $this->employeeRole->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role_id' => $this->employeeRole->id,
        ]);

        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_admin_can_update_existing_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'role_id' => $this->employeeRole->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => 'New Name',
            'email' => 'old@example.com',
            'role_id' => $this->adminRole->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $this->admin));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You cannot delete your own account while logged in.');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_admin_can_delete_other_users(): void
    {
        $other = User::factory()->create([
            'role_id' => $this->employeeRole->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $other));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_user_can_update_own_profile(): void
    {
        $response = $this->actingAs($this->admin)->put(route('profile.update'), [
            'name' => 'Updated Admin Name',
            'email' => 'admin@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'name' => 'Updated Admin Name',
        ]);
    }
}
