<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use ReflectionClass;
use Tests\TestCase;

/**
 * The attacks this application has to survive.
 *
 * Auth bypass, IDOR, CSRF, mass assignment, XSS and SQL injection — each one
 * written as the request an attacker would actually send, not as a check that
 * some framework feature is switched on.
 *
 * AuthenticationTest owns login throttling, session invalidation on logout and
 * the security headers. PermissionMatrixTest owns role gating on every route.
 * This file is about the request itself being hostile.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Auth bypass
    // -----------------------------------------------------------------------

    public function test_unauthenticated_users_are_redirected_for_all_protected_routes(): void
    {
        $protectedUrls = [
            '/',
            '/dashboard',
            '/profile',
            '/products',
            '/products/create',
            '/categories',
            '/suppliers',
            '/distributors',
            '/warehouses',
            '/stock-in',
            '/stock-out',
            '/stock-movements',
            '/transfers',
            '/adjustments',
            '/reports',
            '/users',
            '/activity-logs',
        ];

        foreach ($protectedUrls as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_a_guest_cannot_write_anything(): void
    {
        // Redirecting a guest's GET is only half the rule. The writes are what
        // actually matter, and a guard that only covered reads would pass the
        // test above while leaving every POST wide open.
        $category  = Category::create(['name' => 'Guest Target', 'active' => true]);
        $warehouse = Warehouse::create(['name' => 'Guest Depot', 'active' => true]);

        $writes = [
            ['POST',   '/products',                    ['name' => 'Injected', 'sku' => 'HACK-1']],
            ['POST',   '/categories',                  ['name' => 'Injected Category']],
            ['POST',   '/suppliers',                   ['name' => 'Injected Supplier']],
            ['POST',   '/warehouses',                  ['name' => 'Injected Warehouse']],
            ['POST',   '/stock-in',                    []],
            ['POST',   '/stock-out',                   []],
            ['POST',   '/transfers',                   []],
            ['POST',   '/adjustments',                 []],
            ['POST',   '/users',                       ['name' => 'Injected Admin']],
            ['PUT',    '/profile',                     ['name' => 'Injected']],
            ['DELETE', "/categories/{$category->id}",  []],
            ['DELETE', "/warehouses/{$warehouse->id}", []],
        ];

        foreach ($writes as [$method, $uri, $payload]) {
            $this->call($method, $uri, $payload)
                ->assertRedirect('/login', "A guest reached {$method} {$uri}.");
        }

        $this->assertDatabaseMissing('products', ['sku' => 'HACK-1']);
        $this->assertDatabaseMissing('categories', ['name' => 'Injected Category']);
        $this->assertDatabaseMissing('suppliers', ['name' => 'Injected Supplier']);
        $this->assertDatabaseMissing('users', ['name' => 'Injected Admin']);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_a_deleted_users_session_stops_working(): void
    {
        // A real login, not actingAs(): actingAs pins a user instance for the
        // whole test, so the guard would never go back to the database and the
        // test would pass without proving anything.
        $user = User::factory()->create([
            'email'    => 'soon-gone@example.test',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', ['email' => 'soon-gone@example.test', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk();

        $user->delete();

        // Every test request reuses one application instance, so the session
        // guard still holds the user object it resolved a moment ago. A real
        // request starts with an empty guard and only the session cookie, so
        // forget the guards to make it resolve the stored id from scratch —
        // which is the thing under test.
        $this->app['auth']->forgetGuards();

        // The session still names a user id that no longer resolves, so the
        // guard has to treat the request as anonymous rather than trusting it.
        $this->get('/dashboard')->assertRedirect('/login');
    }

    // -----------------------------------------------------------------------
    // CSRF
    // -----------------------------------------------------------------------

    /**
     * The real middleware, with only its test-mode bypass disabled.
     *
     * Laravel skips CSRF verification for every request made through the test
     * client (VerifyCsrfToken::runningUnitTests()), so a plain $this->post()
     * can never prove the protection works — the old version of this test
     * asserted the session merely held a token, which says nothing about
     * enforcement. Here the shipped class does the checking for real.
     */
    private function csrfMiddleware(): ValidateCsrfToken
    {
        return new class(app(), app('encrypter')) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    }

    private function csrfRequest(string $uri, array $payload = []): Request
    {
        $request = Request::create($uri, 'POST', $payload);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_a_post_without_a_csrf_token_is_rejected(): void
    {
        $this->expectException(TokenMismatchException::class);

        $this->csrfMiddleware()->handle(
            $this->csrfRequest('/products', ['name' => 'Forged', 'sku' => 'FORGED-1']),
            fn () => response('the controller must never run')
        );
    }

    public function test_a_post_with_the_wrong_csrf_token_is_rejected(): void
    {
        // A stale or guessed token must fare no better than none at all.
        $this->expectException(TokenMismatchException::class);

        $this->csrfMiddleware()->handle(
            $this->csrfRequest('/products', ['_token' => 'not-the-real-token']),
            fn () => response('the controller must never run')
        );
    }

    public function test_a_post_with_the_session_csrf_token_is_accepted(): void
    {
        $session = app('session.store');
        $session->start();

        $request = Request::create('/products', 'POST', ['_token' => $session->token()]);
        $request->setLaravelSession($session);

        $response = $this->csrfMiddleware()->handle($request, fn () => response('reached the controller'));

        $this->assertSame('reached the controller', $response->getContent());
    }

    public function test_a_read_request_needs_no_csrf_token(): void
    {
        $request = Request::create('/products', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $this->csrfMiddleware()->handle($request, fn () => response('reached the controller'));

        $this->assertSame('reached the controller', $response->getContent());
    }

    public function test_no_route_is_excluded_from_csrf_verification(): void
    {
        // An $except entry is how CSRF protection silently disappears from one
        // endpoint. There is no API or webhook here, so the list must stay empty.
        $reflection = new ReflectionClass(ValidateCsrfToken::class);

        $except = $reflection->getProperty('except');
        $except->setAccessible(true);

        $neverVerify = $reflection->getProperty('neverVerify');
        $neverVerify->setAccessible(true);

        $this->assertSame([], $except->getValue($this->csrfMiddleware()));
        $this->assertSame([], $neverVerify->getValue());
    }

    public function test_every_form_carries_a_csrf_token(): void
    {
        // The login form has to be checked first, while still a guest: showLogin
        // redirects an authenticated visitor to the dashboard.
        $this->get(route('login'))->assertOk()->assertSee('name="_token"', false);

        $user = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($user);

        $formPages = [
            route('products.create'),
            route('categories.create'),
            route('suppliers.create'),
            route('distributors.create'),
            route('warehouses.create'),
            route('users.create'),
            route('profile.edit'),
        ];

        foreach ($formPages as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee('name="_token"', false);
        }
    }

    // -----------------------------------------------------------------------
    // IDOR — acting on records that belong to someone else
    // -----------------------------------------------------------------------

    public function test_a_user_cannot_edit_another_account_through_the_profile_form(): void
    {
        // /profile takes no id: it always acts on the authenticated user. Sending
        // someone else's id or email must change nothing about them.
        $attacker = User::factory()->create(['name' => 'Attacker', 'email' => 'attacker@example.test']);
        $victim   = User::factory()->create(['name' => 'Victim', 'email' => 'victim@example.test']);

        $this->actingAs($attacker)->put(route('profile.update'), [
            'id'      => $victim->id,
            'user_id' => $victim->id,
            'name'    => 'Owned',
            'email'   => 'attacker@example.test',
        ]);

        $victim->refresh();

        $this->assertSame('Victim', $victim->name, 'Another account was modified through /profile.');
        $this->assertSame('victim@example.test', $victim->email);
        $this->assertSame('Owned', $attacker->refresh()->name, 'The attacker should only have changed themselves.');
    }

    public function test_a_non_admin_cannot_reach_another_users_record_by_guessing_the_url(): void
    {
        $employee = User::factory()->create(['role_id' => Role::create(['name' => 'Warehouse Employee'])->id]);
        $victim   = User::factory()->create(['name' => 'Victim', 'email' => 'victim@example.test']);

        $this->actingAs($employee);

        $this->get("/users/{$victim->id}/edit")->assertForbidden();
        $this->put("/users/{$victim->id}", ['name' => 'Owned', 'email' => 'owned@example.test'])->assertForbidden();
        $this->delete("/users/{$victim->id}")->assertForbidden();

        $victim->refresh();

        $this->assertSame('Victim', $victim->name);
        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    public function test_an_admin_cannot_reassign_a_users_record_by_smuggling_an_id(): void
    {
        // The bound {user} decides who is written, never a field in the body.
        $adminRole  = Role::create(['name' => 'Admin']);
        $viewerRole = Role::create(['name' => 'Viewer']);

        $admin  = User::factory()->create(['role_id' => $adminRole->id]);
        $target = User::factory()->create(['name' => 'Target', 'role_id' => $viewerRole->id]);
        $other  = User::factory()->create(['name' => 'Bystander', 'role_id' => $viewerRole->id]);

        $this->actingAs($admin)->put(route('users.update', $target), [
            'id'      => $other->id,
            'name'    => 'Renamed',
            'email'   => $target->email,
            'role_id' => $target->role_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $target->refresh()->name);
        $this->assertSame('Bystander', $other->refresh()->name, 'The wrong record was written.');
        $this->assertSame($other->id, $other->refresh()->id, 'A primary key was overwritten.');
    }

    public function test_an_unknown_record_id_is_a_plain_404_and_leaks_nothing(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($admin);

        foreach (['/products/999999', '/categories/999999', '/stock-in/999999', '/transfers/999999', '/users/999999/edit'] as $uri) {
            $response = $this->get($uri);

            $response->assertNotFound();
            $response->assertDontSee('SQLSTATE');
            $response->assertDontSee('vendor\\laravel');
        }
    }

    public function test_a_non_numeric_id_does_not_reach_the_database(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);

        $this->actingAs($admin)
            ->get("/products/1'+OR+'1'='1")
            ->assertNotFound();
    }

    public function test_an_export_format_outside_the_allowed_list_is_refused(): void
    {
        // The {format} parameter is constrained to pdf|csv. Anything else must
        // not reach the controller, where it could pick a filename or a path.
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/reports/stock/export/csv')->assertOk();

        foreach (['exe', 'php', '..%2F..%2Fetc%2Fpasswd'] as $format) {
            $this->get("/reports/stock/export/{$format}")->assertNotFound();
        }
    }

    // -----------------------------------------------------------------------
    // Mass assignment
    // -----------------------------------------------------------------------

    public function test_mass_assignment_is_blocked_on_profile_update(): void
    {
        $adminRole = Role::create(['name' => 'Admin']);
        $clerkRole = Role::create(['name' => 'Inventory Clerk']);

        $user = User::factory()->create(['role_id' => $clerkRole->id]);

        $this->actingAs($user);

        $this->put(route('profile.update'), [
            'name'                  => 'Test Clerk',
            'email'                 => 'clerk@example.com',
            'phone'                 => '1234567890',
            'role_id'               => $adminRole->id,
            'receive_notifications' => '1',
        ]);

        $user->refresh();

        $this->assertEquals($clerkRole->id, $user->role_id, 'Role escalation through the profile form.');
        $this->assertFalse($user->receive_notifications, 'A permission was granted through the profile form.');
    }

    public function test_a_password_cannot_be_replaced_without_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original-password')]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name'                  => $user->name,
            'email'                 => $user->email,
            'password'              => 'attacker-chosen',
            'password_confirmation' => 'attacker-chosen',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('original-password', $user->refresh()->password));
    }

    public function test_timestamps_and_keys_in_the_payload_are_ignored(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($admin);

        $category = Category::create(['name' => 'Gadgets', 'active' => true]);

        $this->post(route('products.store'), [
            'id'            => 4242,
            'category_id'   => $category->id,
            'name'          => 'Forged Timestamps',
            'sku'           => 'FORGE-1',
            'price'         => 10,
            'minimum_stock' => 0,
            'active'        => '1',
            'created_at'    => '1999-01-01 00:00:00',
        ])->assertSessionHasNoErrors();

        $product = Product::where('sku', 'FORGE-1')->firstOrFail();

        $this->assertNotSame(4242, $product->id, 'A client-supplied primary key was honoured.');
        $this->assertNotSame('1999', $product->created_at->format('Y'), 'A client-supplied timestamp was honoured.');
    }

    public function test_a_password_is_never_stored_or_echoed_in_plain_text(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($admin);

        $this->post(route('users.store'), [
            'name'                  => 'Fresh Staffer',
            'email'                 => 'fresh@example.test',
            'role_id'               => Role::create(['name' => 'Viewer'])->id,
            'password'              => 'super-secret-123',
            'password_confirmation' => 'super-secret-123',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'fresh@example.test')->firstOrFail();

        $this->assertNotSame('super-secret-123', $created->password, 'The password was stored in plain text.');
        $this->assertTrue(Hash::check('super-secret-123', $created->password));

        $this->get(route('users.index'))->assertDontSee('super-secret-123');
        $this->get(route('users.edit', $created))->assertDontSee('super-secret-123');
        $this->get(route('activity-logs.index'))->assertDontSee('super-secret-123');
    }

    // -----------------------------------------------------------------------
    // XSS
    // -----------------------------------------------------------------------

    public function test_xss_protection_escapes_user_input(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $xssName  = '<script>alert("xss")</script>';
        $category = Category::create([
            'name'        => $xssName,
            'description' => 'Test XSS',
            'active'      => true,
        ]);

        $response = $this->get(route('categories.show', $category));
        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_stored_xss_is_escaped_everywhere_a_product_name_appears(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload  = '<img src=x onerror="alert(1)">';
        $category = Category::create(['name' => 'Safe Category', 'active' => true]);

        $product = Product::create([
            'category_id'   => $category->id,
            'name'          => $payload,
            'sku'           => 'XSS-1',
            'price'         => 1,
            'minimum_stock' => 1,
            'active'        => true,
        ]);

        StockMovement::create([
            'product_id'   => $product->id,
            'warehouse_id' => Warehouse::create(['name' => 'Safe Depot', 'active' => true])->id,
            'type'         => StockMovement::TYPE_IN,
            'quantity'     => 1,
        ]);

        $pages = [
            route('products.index'),
            route('products.show', $product),
            route('products.edit', $product),
            route('reports.stock'),
            route('reports.low-stock'),
            route('stock-movements.index'),
            route('dashboard'),
        ];

        foreach ($pages as $page) {
            $this->get($page)
                ->assertOk()
                ->assertDontSee('onerror="alert(1)"', false);
        }
    }

    public function test_a_reflected_search_term_is_escaped(): void
    {
        // The search box re-renders whatever was typed. That value is attacker
        // controlled and never touches the database, so escaping is the only
        // thing standing between it and execution.
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = '"><script>alert(1)</script>';

        $this->get(route('products.index', ['search' => $payload]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->get(route('categories.index', ['search' => $payload]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_flash_message_built_from_user_input_is_escaped(): void
    {
        // The delete-protection message interpolates the record's own name.
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin'])->id]);
        $this->actingAs($admin);

        $category = Category::create(['name' => '<script>alert("flash")</script>', 'active' => true]);

        Product::create([
            'category_id'   => $category->id,
            'name'          => 'Blocking Product',
            'sku'           => 'BLOCK-1',
            'price'         => 1,
            'minimum_stock' => 0,
            'active'        => true,
        ]);

        $this->delete(route('categories.destroy', $category))->assertRedirect();

        $this->followingRedirects()
            ->delete(route('categories.destroy', $category))
            ->assertOk()
            ->assertDontSee('<script>alert("flash")</script>', false);
    }

    // -----------------------------------------------------------------------
    // SQL injection
    // -----------------------------------------------------------------------

    public function test_sql_injection_is_prevented_in_search_and_filters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Category::create([
            'name'        => 'Electronics',
            'description' => 'Gadgets',
            'active'      => true,
        ]);

        $injectionPayload = "' OR '1'='1";
        $response = $this->get(route('categories.index', ['search' => $injectionPayload]));

        $response->assertOk();
        // The payload is compared as a literal string, so it matches nothing.
        $response->assertDontSee('Electronics');
    }

    public function test_a_drop_table_payload_leaves_the_schema_alone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::create(['name' => 'Survivor', 'active' => true]);

        Product::create([
            'category_id'   => $category->id,
            'name'          => 'Survivor Product',
            'sku'           => 'SURV-1',
            'price'         => 1,
            'minimum_stock' => 0,
            'active'        => true,
        ]);

        foreach (["'; DROP TABLE products; --", "1; DELETE FROM products", "%' UNION SELECT password FROM users --"] as $payload) {
            $this->get(route('products.index', ['search' => $payload]))->assertOk();
        }

        $this->assertSame(1, Product::count(), 'The products table did not survive the payload.');
        $this->assertDatabaseHas('products', ['sku' => 'SURV-1']);
    }

    public function test_an_injected_filter_id_is_rejected_by_validation_not_by_the_database(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Report filters are integers that must exist. A payload is neither, so
        // it fails validation long before it can reach a query.
        $this->get(route('reports.stock', ['warehouse_id' => '1 OR 1=1']))
            ->assertSessionHasErrors('warehouse_id');

        $this->get(route('reports.stock', ['category_id' => "1); DROP TABLE products; --"]))
            ->assertSessionHasErrors('category_id');

        $this->get(route('reports.movements', ['type' => "IN' OR '1'='1"]))
            ->assertSessionHasErrors('type');
    }

    public function test_an_injected_sort_or_page_parameter_cannot_reach_the_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Nothing in the app takes a column name from the request — ordering is
        // hard-coded. These would be the openings if it ever started to.
        $this->get(route('products.index', ['sort' => 'name; DROP TABLE products', 'page' => "1 OR 1=1"]))
            ->assertOk();

        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('products'),
            'The products table was dropped through a query parameter.'
        );
    }
}
