<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin        = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->employeeRole = Role::create(['name' => 'Warehouse Employee']);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id'   => Category::firstOrCreate(['name' => 'Misc'], ['active' => true])->id,
            'name'          => 'Logged Widget',
            'sku'           => 'LOG-1',
            'price'         => 10,
            'minimum_stock' => 0,
            'active'        => true,
        ], $overrides));
    }

    // -----------------------------------------------------------------------
    // What gets recorded
    // -----------------------------------------------------------------------

    public function test_creating_a_record_is_logged_with_its_label_and_actor(): void
    {
        $this->actingAs($this->admin);

        $product = $this->product();

        $log = ActivityLog::where('subject_type', 'product')->where('subject_id', $product->id)->first();

        $this->assertNotNull($log);
        $this->assertSame(ActivityLog::ACTION_CREATED, $log->action);
        $this->assertSame('Logged Widget', $log->subject_label);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_subject_type_is_stored_as_a_readable_alias_not_a_class_path(): void
    {
        // Keeps the table legible and means moving the class does not
        // invalidate history.
        $this->actingAs($this->admin);
        $this->product();

        $this->assertDatabaseHas('activity_logs', ['subject_type' => 'product']);
        $this->assertDatabaseMissing('activity_logs', ['subject_type' => Product::class]);
    }

    public function test_updating_a_record_logs_which_fields_changed(): void
    {
        $this->actingAs($this->admin);
        $product = $this->product();

        $product->update(['name' => 'Renamed Widget', 'price' => 25]);

        $log = ActivityLog::where('action', ActivityLog::ACTION_UPDATED)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('name', $log->properties);
        $this->assertArrayHasKey('price', $log->properties);
        $this->assertSame('Renamed Widget', $log->properties['name']);
    }

    public function test_a_save_that_changes_nothing_is_not_logged(): void
    {
        $this->actingAs($this->admin);
        $product = $this->product();

        $before = ActivityLog::count();
        $product->update(['name' => 'Logged Widget']); // identical value
        $this->assertSame($before, ActivityLog::count(), 'A no-op save should not create log noise.');
    }

    public function test_deleting_a_record_is_logged_and_keeps_the_name(): void
    {
        // After a delete the subject row is gone, so a log saying
        // 'deleted product #7' would be far less useful than one naming it.
        $this->actingAs($this->admin);
        $product = $this->product();
        $id      = $product->id;

        $product->delete();

        $log = ActivityLog::where('action', ActivityLog::ACTION_DELETED)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($id, (int) $log->subject_id);
        $this->assertSame('Logged Widget', $log->subject_label);
        $this->assertNull($log->subject, 'The subject is gone; the label is what survives.');
    }

    public function test_the_stock_ledger_is_not_duplicated_into_the_activity_log(): void
    {
        // stock_movements already explains every quantity change. Logging it
        // here would double writes on the hottest table and create two
        // competing audit trails.
        $this->actingAs($this->admin);
        $this->product();

        $this->assertDatabaseMissing('activity_logs', ['subject_type' => 'stock_movement']);
    }

    // -----------------------------------------------------------------------
    // Secrets must not leak into the audit trail
    // -----------------------------------------------------------------------

    public function test_a_password_change_is_recorded_but_the_value_is_redacted(): void
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->employeeRole->id]);
        $user->update(['password' => 'a-brand-new-secret']);

        $log = ActivityLog::where('subject_type', 'user')
            ->where('action', ActivityLog::ACTION_UPDATED)
            ->latest('id')->first();

        $this->assertNotNull($log, 'A password change must still appear in the trail.');
        $this->assertArrayHasKey('password', $log->properties);
        $this->assertSame('[redacted]', $log->properties['password']);

        $encoded = json_encode($log->properties);
        $this->assertStringNotContainsString('a-brand-new-secret', $encoded);
    }

    public function test_a_phone_number_is_never_written_into_the_activity_log(): void
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create(['role_id' => $this->employeeRole->id]);
        $user->update(['phone' => '+201012345678']);

        $log = ActivityLog::where('subject_type', 'user')
            ->where('action', ActivityLog::ACTION_UPDATED)
            ->latest('id')->first();

        $this->assertSame('[redacted]', $log->properties['phone']);

        // Belt and braces: the raw column must not contain it either.
        $this->assertStringNotContainsString(
            '+201012345678',
            (string) \DB::table('activity_logs')->max('properties')
        );
    }

    // -----------------------------------------------------------------------
    // Access
    // -----------------------------------------------------------------------

    public function test_admin_can_view_the_activity_log(): void
    {
        $this->actingAs($this->admin);
        $this->product();

        $this->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee('Logged Widget');
    }

    public function test_non_admins_cannot_view_the_activity_log(): void
    {
        $employee = User::factory()->create(['role_id' => $this->employeeRole->id]);

        $this->actingAs($employee)->get(route('activity-logs.index'))->assertStatus(403);
    }

    public function test_the_activity_log_is_read_only(): void
    {
        // An audit trail an administrator can rewrite is not an audit trail,
        // so no write routes exist at all. 404 (no such URI) and 405 (wrong
        // verb) are both acceptable proof that nothing is writable.
        $this->actingAs($this->admin);

        $this->assertContains($this->post('/activity-logs')->status(), [404, 405]);
        $this->assertContains($this->delete('/activity-logs/1')->status(), [404, 405]);
    }

    public function test_the_log_filters_by_action_and_record_type(): void
    {
        $this->actingAs($this->admin);

        $product = $this->product();
        $product->update(['name' => 'Filtered Widget']);

        $this->get(route('activity-logs.index', ['action' => ActivityLog::ACTION_UPDATED]))
            ->assertOk()
            ->assertSee('Filtered Widget');

        $this->get(route('activity-logs.index', ['subject_type' => 'category']))
            ->assertOk()
            ->assertDontSee('Filtered Widget');
    }

    public function test_deleting_a_user_keeps_their_log_entries(): void
    {
        // nullOnDelete, not cascade: removing an account must never erase the
        // record of what it did.
        $this->actingAs($this->admin);

        $actor = User::factory()->create(['role_id' => $this->employeeRole->id]);
        $this->actingAs($actor)->product();

        $logCount = ActivityLog::where('user_id', $actor->id)->count();
        $this->assertGreaterThan(0, $logCount);

        $actor->delete();

        $this->assertGreaterThan(0, ActivityLog::whereNull('user_id')->count());
    }
}
