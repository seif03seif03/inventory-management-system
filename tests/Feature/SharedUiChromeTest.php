<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared UI chrome: flash messages and empty states.
 *
 * Both used to be copied into every page that needed them — a success message
 * in twenty-two files, an empty row in nineteen. Copies drift, and two specific
 * things went wrong that no test would have caught:
 *
 *   1. The same message rendered in two different styles depending on which
 *      page you landed on, so weight stopped tracking importance.
 *   2. Moving them into layouts.app and a component means a leftover copy in a
 *      page now renders the message TWICE. Nothing about that throws, the page
 *      still returns 200, and assertSee() passes just as happily on two copies
 *      as on one — so the assertions below count occurrences rather than merely
 *      looking for them.
 *
 * The empty-state tests are here for the second reason too: an empty table is
 * the one state a CRUD test never leaves the app in, so it goes unexercised
 * exactly where the copy matters most.
 */
class SharedUiChromeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::create(['name' => 'Admin'])->id,
        ]);

        $this->actingAs($this->admin);
    }

    private function timesSeen(string $content, string $needle): int
    {
        return substr_count($content, e($needle));
    }

    public function test_a_success_message_is_rendered_once_and_as_a_banner(): void
    {
        $this->post(route('categories.store'), ['name' => 'Phones', 'active' => '1'])
            ->assertRedirect(route('categories.index'));

        $content = $this->get(route('categories.index'))->assertOk()->getContent();

        $this->assertSame(1, $this->timesSeen($content, 'Category created successfully.'),
            'A flash message must render exactly once — the layout renders it, so a leftover copy in the page doubles it.');

        // The banner, not the old green pill: a confirmation styled as a
        // footnote reads as a footnote.
        $this->assertStringContainsString('alert alert-success', $content);
    }

    public function test_an_error_message_is_rendered_once(): void
    {
        $category = Category::create(['name' => 'Phones', 'active' => true]);

        Product::factory()->create(['category_id' => $category->id]);

        // Deleting a category that still holds products is refused, and the
        // refusal comes back as a flash on the page redirected to.
        $this->delete(route('categories.destroy', $category))->assertRedirect();

        $content = $this->get(route('categories.index'))->assertOk()->getContent();

        $this->assertSame(1, $this->timesSeen($content, 'still has products and cannot be deleted'));
    }

    public function test_a_validation_summary_is_rendered_once_above_the_form(): void
    {
        $this->from(route('categories.create'))
            ->post(route('categories.store'), ['name' => ''])
            ->assertRedirect(route('categories.create'));

        $content = $this->get(route('categories.create'))->assertOk()->getContent();

        $this->assertSame(1, $this->timesSeen($content, 'Please correct the following:'));

        // The summary lists what went wrong; the inline @error beside the field
        // says which box to fix. Both, deliberately — but the field message
        // exists, so the page is not a summary alone.
        $this->assertStringContainsString('form-error', $content);
    }

    public function test_an_empty_index_says_what_the_page_is_for_and_where_to_start(): void
    {
        $content = $this->get(route('products.index'))->assertOk()->getContent();

        $this->assertStringContainsString('empty-state', $content);
        $this->assertStringContainsString('No products yet', $content);
        $this->assertStringContainsString(route('products.create'), $content);

        // Nothing was filtered, so offering to clear filters would be nonsense.
        $this->assertStringNotContainsString('Clear filters', $content);
    }

    public function test_filtering_down_to_nothing_says_so_and_offers_the_way_back(): void
    {
        Product::factory()->create(['name' => 'Kettle']);

        $content = $this->get(route('products.index', ['search' => 'no-such-product']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Nothing matches these filters', $content);
        $this->assertStringContainsString('Clear filters', $content);

        // The way back is this page without the query string.
        $this->assertStringContainsString('href="' . route('products.index') . '"', $content);

        // A user who filtered to nothing has records; telling them the
        // catalogue is empty would be wrong.
        $this->assertStringNotContainsString('No products yet', $content);
    }

    public function test_the_page_number_alone_does_not_count_as_a_filter(): void
    {
        // Paginating past the last page empties the table without the user
        // having filtered anything, so it must not claim they did.
        $content = $this->get(route('products.index', ['page' => 9]))->assertOk()->getContent();

        $this->assertStringContainsString('No products yet', $content);
        $this->assertStringNotContainsString('Nothing matches these filters', $content);
    }

    public function test_an_empty_state_does_not_offer_a_create_link_the_role_cannot_use(): void
    {
        // The transfers and adjustments create buttons are role-gated in the
        // page header. An empty state that linked to them anyway would send
        // people to a 403.
        foreach ([route('transfers.index'), route('adjustments.index')] as $url) {
            $content = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('empty-state', $content);
            $this->assertStringNotContainsString('btn btn-primary btn-sm', $content);
        }
    }

    public function test_an_empty_low_stock_report_reads_as_good_news(): void
    {
        $content = $this->get(route('reports.low-stock'))->assertOk()->getContent();

        // Nothing to restock is a result, not an absence.
        $this->assertStringContainsString('Nothing needs restocking', $content);
    }
}
