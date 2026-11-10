<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PR-3 performance guards. They do not measure time (see the measurements in the PR); they pin the NUMBER
 * of queries and the query shapes so a future change cannot silently bring back N+1, per-row hydration,
 * unbounded lists or DATE(created_at) filters.
 */
class AdminQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function seedCampaigns(int $campaigns, int $reviewsEach): void
    {
        ReviewCampaign::factory()->count($campaigns)->create()->each(function ($campaign) use ($reviewsEach) {
            CourseReview::factory()->count($reviewsEach)->create(['review_campaign_id' => $campaign->id]);
        });
        AdminNotification::create(['type' => 'review', 'title' => 't', 'message' => 'm']);
    }

    /** @return array{0:int, 1:array<int,string>} number of queries and their SQL */
    private function queriesFor(string $url): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get($url)->assertOk();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return [count($log), array_column($log, 'query')];
    }

    // ───────────────────────── Number of queries ─────────────────────────

    public function test_dashboard_runs_a_small_fixed_number_of_queries_regardless_of_data_size(): void
    {
        $this->seedCampaigns(3, 4);
        [$small] = $this->queriesFor(route('admin.dashboard'));

        $this->seedCampaigns(60, 5);   // 63 campaigns (more than one page) and 312 reviews
        [$large, $sql] = $this->queriesFor(route('admin.dashboard'));

        $this->assertSame($small, $large, "dashboard query count must not grow with the data:\n" . implode("\n", $sql));
        $this->assertLessThanOrEqual(14, $large, "dashboard used too many queries:\n" . implode("\n", $sql));
    }

    public function test_campaign_page_runs_a_small_fixed_number_of_queries(): void
    {
        $this->seedCampaigns(1, 40);
        $campaign = ReviewCampaign::first();
        [$a] = $this->queriesFor(route('admin.campaigns.show', $campaign));

        CourseReview::factory()->count(150)->create(['review_campaign_id' => $campaign->id]);
        [$b, $sql] = $this->queriesFor(route('admin.campaigns.show', $campaign));

        $this->assertSame($a, $b);
        $this->assertLessThanOrEqual(7, $b, implode("\n", $sql));
    }

    public function test_reviews_list_and_notifications_query_counts_are_bounded(): void
    {
        $this->seedCampaigns(1, 50);
        $campaign = ReviewCampaign::first();

        [$reviews, $sql] = $this->queriesFor(route('admin.campaigns.reviews', $campaign));
        $this->assertLessThanOrEqual(6, $reviews, implode("\n", $sql));

        [$notifications, $sql] = $this->queriesFor(route('admin.notifications.index'));
        $this->assertLessThanOrEqual(6, $notifications, implode("\n", $sql));
    }

    public function test_dashboard_never_loads_review_rows_to_group_them_in_php(): void
    {
        $this->seedCampaigns(2, 10);
        [, $sql] = $this->queriesFor(route('admin.dashboard'));

        // Only aggregates may touch course_reviews, plus the 5 latest reviews (LIMIT).
        $reviewSelects = array_filter($sql, fn ($q) => str_contains($q, 'from "course_reviews"') && !str_contains(strtolower($q), 'count(') && !str_contains(strtolower($q), 'limit'));
        $this->assertSame([], array_values($reviewSelects), "a query loads review rows without aggregating or limiting:\n" . implode("\n", $reviewSelects));
    }

    // ───────────────────────── Date filters keep using ranges ─────────────────────────

    public function test_date_filters_are_range_comparisons_not_date_functions(): void
    {
        $this->seedCampaigns(2, 6);
        $campaign = ReviewCampaign::first();

        foreach ([
            route('admin.campaigns.show', ['campaign' => $campaign, 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']),
            route('admin.campaigns.reviews', ['campaign' => $campaign, 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']),
            route('admin.dashboard', ['date_from' => '2026-01-01', 'date_to' => '2026-12-31']),
        ] as $url) {
            [, $sql] = $this->queriesFor($url);

            // The user's filter must appear as a range on the table it filters (the monthly chart also has
            // `created_at >=`, so look at the right table: reviews for the review pages, campaigns for the dashboard).
            $table = str_contains($url, 'review-campaigns') ? 'from "course_reviews"' : 'from "review_campaigns"';
            $filtered = array_values(array_filter($sql, fn ($q) => str_contains($q, $table) && str_contains($q, '"created_at" >=') && str_contains($q, '"created_at" <')));

            $this->assertNotEmpty($filtered, "no range filter on $table for $url");
            foreach ($sql as $q) {
                $this->assertStringNotContainsString("strftime('%Y-%m-%d'", $q, 'whereDate() (DATE(created_at)) must not come back: ' . $url);
            }
        }
    }

    // ───────────────────────── Pagination of the campaign list ─────────────────────────

    public function test_dashboard_campaign_list_is_paginated_and_keeps_the_filters(): void
    {
        ReviewCampaign::factory()->count(60)->create(['instructor_name' => 'أحمد']);
        ReviewCampaign::factory()->count(5)->create(['instructor_name' => 'سارة']);

        $page1 = $this->get(route('admin.dashboard'))->assertOk();
        $this->assertCount(25, $page1->viewData('campaigns')->items());
        $this->assertSame(65, $page1->viewData('campaigns')->total());
        $page1->assertSee('65 حملات');

        $page3 = $this->get(route('admin.dashboard', ['page' => 3]))->assertOk()->viewData('campaigns');
        $this->assertCount(15, $page3->items());

        // filters survive in the pagination links and the total reflects the filter
        $filtered = $this->get(route('admin.dashboard', ['instructor_name' => 'أحمد']))->assertOk();
        $this->assertSame(60, $filtered->viewData('campaigns')->total());
        $filtered->assertSee('instructor_name=%D8%A3%D8%AD%D9%85%D8%AF', false);
        $this->assertCount(25, $this->get(route('admin.dashboard', ['instructor_name' => 'أحمد', 'page' => 2]))->viewData('campaigns')->items());
    }

    public function test_pages_of_the_campaign_list_never_overlap_even_with_identical_timestamps(): void
    {
        $same = now()->startOfSecond();
        $ids = ReviewCampaign::factory()->count(55)->create()->each(fn ($c) => DB::table('review_campaigns')->where('id', $c->id)->update(['created_at' => $same]))->pluck('id');

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $seen = array_merge($seen, collect($this->get(route('admin.dashboard', ['page' => $page]))->viewData('campaigns')->items())->pluck('id')->all());
        }

        $this->assertCount(55, $seen);
        $this->assertCount(55, array_unique($seen), 'a campaign appeared on two pages');
        $this->assertEqualsCanonicalizing($ids->all(), $seen);
    }

    public function test_campaign_and_review_lists_break_ties_by_id_in_sql(): void
    {
        $this->seedCampaigns(1, 3);
        $campaign = ReviewCampaign::first();

        [, $dashboard] = $this->queriesFor(route('admin.dashboard'));
        $this->assertNotEmpty(array_filter($dashboard, fn ($q) => str_contains($q, 'order by "created_at" desc, "id" asc')), 'campaign list needs a deterministic tie-break');

        [, $show] = $this->queriesFor(route('admin.campaigns.show', $campaign));
        $this->assertNotEmpty(array_filter($show, fn ($q) => str_contains($q, 'order by "created_at" desc, "id" desc')), 'review list needs a deterministic tie-break');
    }

    public function test_insights_break_ties_by_the_lowest_campaign_id(): void
    {
        $a = ReviewCampaign::factory()->create();
        $b = ReviewCampaign::factory()->create();
        foreach ([$b, $a] as $campaign) {     // inserted b first on purpose: ids must decide, not insertion order
            CourseReview::factory()->count(2)->create(['review_campaign_id' => $campaign->id, 'overall_rating' => 3]);
        }

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame($a->id, $r->viewData('topRatedCampaign')->id);
        $this->assertSame($a->id, $r->viewData('lowestRatedCampaign')->id);
        $this->assertSame($a->id, $r->viewData('mostReviewedCampaign')->id);
    }

    public function test_a_single_reviewed_campaign_is_top_lowest_and_most_reviewed(): void
    {
        $only = ReviewCampaign::factory()->create();
        ReviewCampaign::factory()->create();   // no reviews: must never be chosen
        CourseReview::factory()->count(2)->create(['review_campaign_id' => $only->id]);

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame($only->id, $r->viewData('topRatedCampaign')->id);
        $this->assertSame($only->id, $r->viewData('lowestRatedCampaign')->id);
        $this->assertSame($only->id, $r->viewData('mostReviewedCampaign')->id);
    }

    public function test_a_page_past_the_last_redirects_to_the_last_page_instead_of_the_empty_state(): void
    {
        ReviewCampaign::factory()->count(30)->create(['instructor_name' => 'أحمد']);

        $this->get(route('admin.dashboard', ['page' => 99, 'instructor_name' => 'أحمد']))
            ->assertRedirect(route('admin.dashboard', ['instructor_name' => 'أحمد', 'page' => 2]));

        $this->get(route('admin.dashboard', ['page' => 2, 'instructor_name' => 'أحمد']))->assertOk()->assertDontSee('لا توجد حملات');
    }

    public function test_an_empty_dashboard_still_shows_the_empty_state_without_redirecting(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('لا توجد حملات');
        // A stale ?page=3 on an empty list goes to page 1 (no redirect loop), which shows the empty state.
        $this->get(route('admin.dashboard', ['page' => 3]))->assertRedirect(route('admin.dashboard', ['page' => 1]));
        $this->get(route('admin.dashboard', ['page' => 1]))->assertOk()->assertSee('لا توجد حملات');
    }

    public function test_the_dashboard_shows_pagination_links_only_when_needed(): void
    {
        ReviewCampaign::factory()->count(3)->create();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('page=2');

        ReviewCampaign::factory()->count(40)->create();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('page=2', false);
    }

    // ───────────────────────── Indexes ─────────────────────────

    public function test_admin_query_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('course_reviews', 'reviews_campaign_created_idx'));
        $this->assertTrue(Schema::hasIndex('course_reviews', 'reviews_campaign_rating_recommend_idx'));
        $this->assertTrue(Schema::hasIndex('course_reviews', 'reviews_difficulty_idx'));
        $this->assertTrue(Schema::hasIndex('admin_notifications', 'notifications_created_idx'));
        $this->assertTrue(Schema::hasIndex('admin_notifications', 'notifications_read_idx'));
    }
}
