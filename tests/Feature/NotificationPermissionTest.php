<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\LowStockNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The receive_notifications permission and its phone-number requirement.
 *
 * The rule under test: the permission is only meaningful if there is a number
 * to reach the user on, so enabling it requires a phone — enforced server-side,
 * never by the browser. Revoking it must NOT delete the stored number.
 */
class NotificationPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin        = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->employeeRole = Role::create(['name' => 'Warehouse Employee']);

        $this->actingAs($this->admin);
    }

    private function userPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'New Staffer',
            'email'                 => 'staffer@example.test',
            'role_id'               => $this->employeeRole->id,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // Creating users
    // -----------------------------------------------------------------------

    public function test_a_user_without_the_permission_needs_no_phone(): void
    {
        $this->post(route('users.store'), $this->userPayload())
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email'                 => 'staffer@example.test',
            'phone'                 => null,
            'receive_notifications' => false,
        ]);
    }

    public function test_enabling_the_permission_without_a_phone_is_rejected(): void
    {
        $this->post(route('users.store'), $this->userPayload([
            'receive_notifications' => '1',
        ]))->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'staffer@example.test']);
    }

    public function test_the_rejection_message_explains_the_requirement(): void
    {
        $this->post(route('users.store'), $this->userPayload(['receive_notifications' => '1']))
            ->assertSessionHasErrors([
                'phone' => 'Users who receive notifications must have a phone number.',
            ]);
    }

    public function test_the_permission_with_a_phone_is_accepted(): void
    {
        $this->post(route('users.store'), $this->userPayload([
            'receive_notifications' => '1',
            'phone'                 => '+201012345678',
        ]))->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email'                 => 'staffer@example.test',
            'phone'                 => '+201012345678',
            'receive_notifications' => true,
        ]);
    }

    // -----------------------------------------------------------------------
    // Editing users
    // -----------------------------------------------------------------------

    private function existingUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role_id'               => $this->employeeRole->id,
            'phone'                 => null,
            'receive_notifications' => false,
        ], $overrides));
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name'    => $user->name,
            'email'   => $user->email,
            'role_id' => $user->role_id,
        ], $overrides);
    }

    public function test_revoking_the_permission_keeps_the_stored_phone(): void
    {
        // Kept on purpose so restoring the permission later does not mean
        // re-entering the number.
        $user = $this->existingUser(['phone' => '+201099887766', 'receive_notifications' => true]);

        $this->put(route('users.update', $user), $this->updatePayload($user))
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertFalse($user->receive_notifications);
        $this->assertSame('+201099887766', $user->phone, 'Revoking the permission must not wipe the number.');
    }

    public function test_re_enabling_the_permission_for_a_user_who_already_has_a_phone_succeeds(): void
    {
        // The stored number satisfies the requirement, so the admin does not
        // have to retype it.
        $user = $this->existingUser(['phone' => '+201099887766', 'receive_notifications' => false]);

        $this->put(route('users.update', $user), $this->updatePayload($user, [
            'receive_notifications' => '1',
        ]))->assertRedirect(route('users.index'));

        $this->assertTrue($user->refresh()->receive_notifications);
    }

    public function test_enabling_the_permission_for_a_user_who_never_had_a_phone_is_rejected(): void
    {
        $user = $this->existingUser();

        $this->put(route('users.update', $user), $this->updatePayload($user, [
            'receive_notifications' => '1',
        ]))->assertSessionHasErrors('phone');

        $this->assertFalse($user->refresh()->receive_notifications, 'The permission must not be saved.');
    }

    // -----------------------------------------------------------------------
    // Recipient selection
    // -----------------------------------------------------------------------

    private function seedLowStock(): void
    {
        $product = Product::create([
            'category_id'   => Category::create(['name' => 'Misc', 'active' => true])->id,
            'name'          => 'Nearly Out Widget',
            'sku'           => 'LOW-1',
            'price'         => 5,
            'minimum_stock' => 20,
            'active'        => true,
        ]);

        StockMovement::create([
            'product_id'   => $product->id,
            'warehouse_id' => Warehouse::create(['name' => 'Main', 'active' => true])->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 2,
        ]);
    }

    public function test_a_permitted_user_sees_the_low_stock_alert_in_the_navbar(): void
    {
        $this->seedLowStock();

        $recipient = $this->existingUser(['phone' => '+201000000001', 'receive_notifications' => true]);

        // notif-wrap is the bell's own markup. The layout's <script> mentions
        // the element ids unconditionally, so asserting on those would pass
        // even when the bell is absent.
        $this->actingAs($recipient)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('notif-wrap')
            ->assertSee('Nearly Out Widget');
    }

    public function test_a_user_without_the_permission_gets_no_bell_and_no_alerts(): void
    {
        $this->seedLowStock();

        $outsider = $this->existingUser();

        $this->actingAs($outsider)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('notif-wrap');

        // Note: the product name IS still on the page — the dashboard's own
        // low-stock panel is open to every role. What the permission gates is
        // the notification bell, not visibility of stock data.
        $this->assertSame(0, LowStockNotifier::countFor($outsider));
        $this->assertTrue(LowStockNotifier::for($outsider)->isEmpty());
    }

    public function test_role_alone_never_grants_notifications(): void
    {
        // Admin must not implicitly receive alerts — that would make the
        // permission meaningless and re-hardcode recipients.
        $this->seedLowStock();

        $this->assertFalse($this->admin->canReceiveNotifications());
        $this->assertSame(0, LowStockNotifier::countFor($this->admin));
    }

    public function test_the_permission_without_a_phone_is_not_a_recipient(): void
    {
        // Only reachable via direct DB manipulation, but the notifier must
        // still refuse rather than treat it as a valid recipient.
        $this->seedLowStock();

        $broken = $this->existingUser(['receive_notifications' => true, 'phone' => null]);

        $this->assertFalse($broken->canReceiveNotifications());
        $this->assertSame(0, LowStockNotifier::countFor($broken));
        $this->assertFalse(LowStockNotifier::recipients()->contains('id', $broken->id));
    }

    public function test_recipients_all_expose_a_phone_for_a_future_sms_channel(): void
    {
        $this->seedLowStock();

        $reachable = $this->existingUser(['phone' => '+201000000002', 'receive_notifications' => true]);
        $this->existingUser(['email' => 'nophone@example.test', 'receive_notifications' => true, 'phone' => null]);

        $recipients = LowStockNotifier::recipients();

        $this->assertTrue($recipients->contains('id', $reachable->id));

        foreach ($recipients as $recipient) {
            $this->assertNotEmpty($recipient->phone, 'Every recipient must carry a number a sender can use.');
        }
    }

    // -----------------------------------------------------------------------
    // Self-service limits
    // -----------------------------------------------------------------------

    public function test_a_user_can_update_their_own_phone(): void
    {
        $user = $this->existingUser();

        $this->actingAs($user)->put(route('profile.update'), [
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => '+201055554444',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+201055554444', $user->refresh()->phone);
    }

    public function test_a_user_cannot_grant_themselves_notifications_or_change_their_role(): void
    {
        $user     = $this->existingUser();
        $adminRole = Role::where('name', 'Admin')->first();

        $this->actingAs($user)->put(route('profile.update'), [
            'name'                  => $user->name,
            'email'                 => $user->email,
            'phone'                 => '+201066667777',
            'receive_notifications' => '1',
            'role_id'               => $adminRole->id,
        ]);

        $user->refresh();

        $this->assertFalse($user->receive_notifications, 'Privilege escalation via the profile form.');
        $this->assertSame($this->employeeRole->id, $user->role_id, 'Role escalation via the profile form.');
    }

    public function test_a_recipient_cannot_blank_their_own_phone(): void
    {
        // That would leave the permission with no way to reach them.
        $user = $this->existingUser(['phone' => '+201088889999', 'receive_notifications' => true]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => '',
        ])->assertSessionHasErrors('phone');

        $this->assertSame('+201088889999', $user->refresh()->phone);
    }

    public function test_the_users_list_shows_phone_and_permission_state(): void
    {
        $this->existingUser(['name' => 'Reachable Rita', 'phone' => '+201011112222', 'receive_notifications' => true]);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Reachable Rita')
            ->assertSee('+201011112222')
            ->assertSee('Enabled');
    }
}
