<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PR-3 regression guard: the admin dashboard / campaign pages must keep returning EXACTLY the same
 * statistics and lists while their queries are optimised. Expected values come from an independent
 * oracle (plain PHP over the raw rows), not from the code under test. Time is frozen.
 */
class AdminStatsRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-07-15 12:00:00';

    private array $c = [];   // key => campaign id
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::NOW);
        $this->actingAs(User::factory()->create());
        $this->seedDataset();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ───────────────────────── Dataset ─────────────────────────

    private function campaign(string $key, array $attrs): void
    {
        $createdAt = $attrs['created_at'];
        unset($attrs['created_at']);
        $campaign = ReviewCampaign::factory()->create($attrs);
        DB::table('review_campaigns')->where('id', $campaign->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        $this->c[$key] = $campaign->id;
    }

    private function review(string $campaignKey, int $rating, string $createdAt, array $over = []): void
    {
        $n = ++$this->seq;
        $r = CourseReview::factory()->create(array_merge([
            'review_campaign_id'    => $this->c[$campaignKey],
            'student_name'          => "طالب $n",
            'email'                 => "r$n@example.test",
            'overall_rating'        => $rating,
            'course_content_rating' => (($rating + $n) % 5) + 1,
            'instructor_rating'     => (($rating + 2 * $n) % 5) + 1,
            'organization_rating'   => (($rating + 3 * $n) % 5) + 1,
            'difficulty_level'      => ['سهل', 'متوسط', 'صعب'][$n % 3],
            'would_recommend'       => $n % 4 !== 0,
        ], $over));
        DB::table('course_reviews')->where('id', $r->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
    }

    private function seedDataset(): void
    {
        $now = Carbon::parse(self::NOW);

        $this->campaign('c1', ['course_name' => 'دورة الذكاء الاصطناعي', 'instructor_name' => 'أحمد', 'is_active' => true, 'expires_at' => null, 'created_at' => '2026-01-10 10:00:00']);
        $this->campaign('c2', ['course_name' => 'ورشة التسويق', 'instructor_name' => 'سارة', 'is_active' => true, 'expires_at' => $now->copy()->addDays(2), 'created_at' => '2026-03-31 23:59:59']);
        $this->campaign('c3', ['course_name' => 'برنامج القيادة', 'instructor_name' => 'أحمد', 'is_active' => true, 'expires_at' => $now->copy()->addDays(20), 'created_at' => '2026-04-01 00:00:00']);
        $this->campaign('c4', ['course_name' => 'دورة الأمن', 'instructor_name' => 'خالد', 'is_active' => false, 'expires_at' => null, 'created_at' => '2026-05-05 09:00:00']);
        $this->campaign('c5', ['course_name' => 'دورة قديمة', 'instructor_name' => 'سارة', 'is_active' => true, 'expires_at' => $now->copy()->subDays(5), 'created_at' => '2026-02-02 08:00:00']);
        $this->campaign('c6', ['course_name' => 'ورشة موقوفة ومنتهية', 'instructor_name' => null, 'is_active' => false, 'expires_at' => $now->copy()->subDay(), 'created_at' => '2026-06-06 06:00:00']);
        $this->campaign('c7', ['course_name' => 'حدّ سبعة أيام', 'instructor_name' => 'خالد', 'is_active' => true, 'expires_at' => $now->copy()->addDays(7), 'created_at' => '2026-07-01 00:00:01']);
        $this->campaign('c8', ['course_name' => 'حدّ الآن', 'instructor_name' => 'خالد', 'is_active' => true, 'expires_at' => $now->copy(), 'created_at' => '2026-07-10 10:10:10']);
        $this->campaign('c9', ['course_name' => 'بلا تقييمات', 'instructor_name' => 'أحمد', 'is_active' => true, 'expires_at' => null, 'created_at' => '2026-07-14 14:00:00']);

        // c1: 22 reviews (pagination: 10/page on the show page, 15/page on the reviews page), spread over months & date boundaries.
        $c1 = [
            [4, '2025-12-31 23:59:59'], [3, '2026-01-01 00:00:00'], [5, '2026-01-20 10:00:00'], [4, '2026-02-03 11:00:00'], [2, '2026-02-25 09:30:00'],
            [5, '2026-03-15 23:59:59'], [3, '2026-03-16 00:00:00'], [4, '2026-03-20 12:00:00'], [4, '2026-04-02 08:00:00'], [1, '2026-04-30 18:00:00'],
            [5, '2026-05-10 10:10:10'], [3, '2026-05-11 10:10:10'], [4, '2026-06-01 00:00:00'], [4, '2026-06-15 15:15:15'], [2, '2026-06-30 23:59:59'],
            [5, '2026-07-01 00:00:00'], [3, '2026-07-05 05:05:05'], [4, '2026-07-10 10:00:00'], [5, '2026-07-12 12:12:12'], [4, '2026-07-13 13:13:13'],
            [3, '2026-07-14 09:09:09'], [5, '2026-07-15 11:00:00'],
        ];
        foreach ($c1 as [$rating, $at]) {
            $this->review('c1', $rating, $at);
        }
        foreach ([[5, '2026-02-10 10:00:00'], [5, '2026-03-12 10:00:00'], [4, '2026-04-12 10:00:00'], [5, '2026-05-12 10:00:00'], [4, '2026-06-12 10:00:00'], [5, '2026-07-02 10:00:00'], [4, '2026-07-03 10:00:00'], [5, '2026-07-04 10:00:00']] as [$r, $at]) {
            $this->review('c2', $r, $at);       // avg 4.625 = best
        }
        foreach ([[2, '2026-03-02 10:00:00'], [2, '2026-04-03 10:00:00'], [1, '2026-05-03 10:00:00'], [3, '2026-06-03 10:00:00'], [2, '2026-07-06 10:00:00'], [2, '2026-07-07 10:00:00']] as [$r, $at]) {
            $this->review('c3', $r, $at);       // avg 2.0 = worst
        }
        foreach ([[5, '2026-03-04 10:00:00'], [3, '2026-05-04 10:00:00'], [4, '2026-07-08 10:00:00']] as [$r, $at]) {
            $this->review('c4', $r, $at);
        }
        foreach ([[4, '2026-02-05 10:00:00'], [3, '2026-03-05 10:00:00'], [4, '2026-04-05 10:00:00'], [3, '2026-05-05 10:00:00'], [4, '2026-06-05 10:00:00']] as [$r, $at]) {
            $this->review('c5', $r, $at);
        }
        $this->review('c6', 3, '2026-06-20 10:00:00');
        $this->review('c6', 3, '2026-06-21 10:00:00');
        $this->review('c7', 4, '2026-07-09 10:00:00');
        $this->review('c8', 3, '2026-07-11 10:00:00');

        foreach (range(1, 12) as $i) {
            $n = AdminNotification::create(['type' => 'review', 'title' => "إشعار $i", 'message' => 'm', 'data' => ['campaign_id' => $this->c['c1'], 'review_id' => $i]]);
            DB::table('admin_notifications')->where('id', $n->id)->update(['created_at' => Carbon::parse(self::NOW)->subMinutes(100 - $i), 'read_at' => $i > 5 ? self::NOW : null]);
        }
    }

    // ───────────────────────── Helpers / oracle ─────────────────────────

    private function items(mixed $listing): array
    {
        return $listing instanceof AbstractPaginator ? $listing->items() : $listing->all();
    }

    private function listTotal(mixed $listing): int
    {
        return $listing instanceof AbstractPaginator ? $listing->total() : $listing->count();
    }

    private function ids(iterable $models): array
    {
        return collect($models)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function cid(string ...$keys): array
    {
        return array_map(fn ($k) => $this->c[$k], $keys);
    }

    /** Campaign rows sorted like latest(): created_at desc. */
    private function campaignsDesc(): \Illuminate\Support\Collection
    {
        return DB::table('review_campaigns')->get()->sortByDesc('created_at')->values();
    }

    /** Reviews of a campaign as a collection of stdClass, newest first. */
    private function reviewsOf(string $key)
    {
        return DB::table('course_reviews')->where('review_campaign_id', $this->c[$key])->get()->sortByDesc('created_at')->values();
    }

    // ───────────────────────── Dashboard: headline statistics ─────────────────────────

    public function test_dashboard_headline_statistics_match_the_oracle(): void
    {
        $reviews = DB::table('course_reviews')->get();
        $total = $reviews->count();
        $yes = $reviews->where('would_recommend', 1)->count();

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(9, $r->viewData('totalCampaigns'));
        $this->assertSame($total, $r->viewData('totalReviews'));
        $this->assertEquals(round($reviews->avg('overall_rating'), 2), $r->viewData('averageRating'));
        $this->assertSame($yes, $r->viewData('recommendYes'));
        $this->assertSame($total - $yes, $r->viewData('recommendNo'));
        $this->assertEquals(round($yes / $total * 100), $r->viewData('globalRecommendPct'));
    }

    public function test_dashboard_rating_breakdown_and_difficulty_match_the_oracle(): void
    {
        $reviews = DB::table('course_reviews')->get();
        $r = $this->get(route('admin.dashboard'))->assertOk();

        $expectedStars = collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($s) => [$s => $reviews->where('overall_rating', $s)->count()])->all();
        $this->assertSame($expectedStars, $r->viewData('ratingBreakdown')->all());

        $expectedDifficulty = $reviews->groupBy('difficulty_level')->map->count()->all();
        $actual = $r->viewData('difficultyRaw')->all();
        ksort($expectedDifficulty);
        ksort($actual);
        $this->assertEquals($expectedDifficulty, $actual);
    }

    public function test_dashboard_campaign_status_counts_match_the_oracle_including_boundaries(): void
    {
        $now = Carbon::parse(self::NOW);
        $rows = DB::table('review_campaigns')->get();
        $expires = fn ($row) => $row->expires_at ? Carbon::parse($row->expires_at) : null;

        $active = $rows->filter(fn ($c) => $c->is_active && (!$expires($c) || $expires($c)->gt($now)))->count();
        $inactive = $rows->filter(fn ($c) => !$c->is_active)->count();
        $expired = $rows->filter(fn ($c) => $expires($c) && $expires($c)->lt($now))->count();
        $soon = $rows->filter(fn ($c) => $c->is_active && $expires($c) && $expires($c)->gte($now) && $expires($c)->lte($now->copy()->addDays(7)))->count();

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame($active, $r->viewData('activeCampaigns'));
        $this->assertSame($inactive, $r->viewData('inactiveCampaigns'));
        $this->assertSame($expired, $r->viewData('expiredCampaigns'));
        $this->assertSame($soon, $r->viewData('expiringSoonCount'));
        // sanity: the dataset really exercises the edge cases (inactive+expired, exactly-now, exactly-7-days)
        $this->assertSame([5, 2, 2, 3], [$active, $inactive, $expired, $soon]);
    }

    public function test_dashboard_monthly_chart_matches_the_current_behaviour_including_the_seventh_month_quirk(): void
    {
        $now = Carbon::parse(self::NOW);
        $arabic = ['01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل', '05' => 'مايو', '06' => 'يونيو',
                   '07' => 'يوليو', '08' => 'أغسطس', '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'];

        // Oracle = the documented current behaviour: 6 zero-initialised months, then the rows since the start of
        // "now - 6 months" are counted per month and MERGED in, so a 7th (oldest) month is appended LAST when it has rows.
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthly[$now->copy()->subMonths($i)->format('Y-m')] = 0;
        }
        $since = $now->copy()->subMonths(6)->startOfMonth();
        foreach (DB::table('course_reviews')->get() as $row) {
            if (Carbon::parse($row->created_at)->gte($since)) {
                $key = substr($row->created_at, 0, 7);
                $monthly[$key] = ($monthly[$key] ?? 0) + 1;
            }
        }
        $labels = array_map(fn ($m) => $arabic[substr($m, 5, 2)] . ' ' . substr($m, 0, 4), array_keys($monthly));

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame($labels, $r->viewData('monthlyLabels')->all());
        $this->assertSame(array_values($monthly), $r->viewData('monthlyValues')->all());
        $this->assertCount(7, $labels, 'dataset has rows in the 7th month, so the existing quirk is part of the contract');
        $this->assertSame('يناير 2026', end($labels));
    }

    public function test_dashboard_monthly_chart_has_only_six_months_when_the_oldest_month_is_empty(): void
    {
        DB::table('course_reviews')->where('created_at', '<', '2026-02-01 00:00:00')->delete();

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(['فبراير 2026', 'مارس 2026', 'أبريل 2026', 'مايو 2026', 'يونيو 2026', 'يوليو 2026'], $r->viewData('monthlyLabels')->all());
        $this->assertCount(6, $r->viewData('monthlyValues'));
    }

    public function test_dashboard_insights_pick_the_expected_campaigns_with_their_numbers(): void
    {
        $r = $this->get(route('admin.dashboard'))->assertOk();

        $top = $r->viewData('topRatedCampaign');
        $low = $r->viewData('lowestRatedCampaign');
        $most = $r->viewData('mostReviewedCampaign');

        $this->assertSame($this->c['c2'], $top->id);
        $this->assertEqualsWithDelta(4.625, (float) $top->reviews_avg_overall_rating, 0.0001);
        $this->assertSame(8, (int) $top->reviews_count);
        $this->assertSame($this->c['c3'], $low->id);
        $this->assertEqualsWithDelta(2.0, (float) $low->reviews_avg_overall_rating, 0.0001);
        $this->assertSame($this->c['c1'], $most->id);
        $this->assertSame(22, (int) $most->reviews_count);
        $this->assertSame($this->c['c9'], $r->viewData('latestCampaign')->id);
    }

    public function test_dashboard_insights_are_null_without_reviews_and_stats_are_zero(): void
    {
        DB::table('course_reviews')->delete();

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertNull($r->viewData('topRatedCampaign'));
        $this->assertNull($r->viewData('lowestRatedCampaign'));
        $this->assertNull($r->viewData('mostReviewedCampaign'));
        $this->assertSame(0, $r->viewData('totalReviews'));
        $this->assertEquals(0, $r->viewData('averageRating'));
        $this->assertEquals(0, $r->viewData('globalRecommendPct'));
        $this->assertSame([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], $r->viewData('ratingBreakdown')->all());
        $this->assertSame(0, array_sum($r->viewData('monthlyValues')->all()));
    }

    public function test_dashboard_latest_reviews_are_the_five_newest_with_their_campaign(): void
    {
        $expected = DB::table('course_reviews')->orderByDesc('created_at')->limit(5)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame($expected, $this->ids($r->viewData('latestReviews')));
        $this->assertTrue($r->viewData('latestReviews')->every(fn ($rev) => $rev->relationLoaded('campaign')));
    }

    // ───────────────────────── Dashboard: campaign list & filters ─────────────────────────

    public function test_dashboard_campaign_list_is_newest_first_with_review_counts_and_averages(): void
    {
        $r = $this->get(route('admin.dashboard'))->assertOk();
        $list = $r->viewData('campaigns');

        $this->assertSame($this->ids($this->campaignsDesc()), $this->ids($this->items($list)));
        $this->assertSame(9, $this->listTotal($list));

        foreach ($this->items($list) as $campaign) {
            $reviews = DB::table('course_reviews')->where('review_campaign_id', $campaign->id)->get();
            $this->assertSame($reviews->count(), (int) $campaign->reviews_count);
            $this->assertEqualsWithDelta($reviews->count() ? $reviews->avg('overall_rating') : 0, (float) ($campaign->reviews_avg_overall_rating ?? 0), 0.0001);
        }
    }

    #[DataProvider('dashboardFilterProvider')]
    public function test_dashboard_filters_return_the_expected_campaigns(array $query, array $expectedKeys): void
    {
        $list = $this->get(route('admin.dashboard', $query))->assertOk()->viewData('campaigns');

        $expected = $this->campaignsDesc()->pluck('id')->map(fn ($i) => (int) $i)->filter(fn ($id) => in_array($id, $this->cid(...$expectedKeys), true))->values()->all();
        $this->assertSame($expected, $this->ids($this->items($list)));
        $this->assertSame(count($expectedKeys), $this->listTotal($list));
    }

    public static function dashboardFilterProvider(): array
    {
        return [
            'course name'                        => [['course_name' => 'دورة'], ['c1', 'c4', 'c5']],
            'instructor'                         => [['instructor_name' => 'أحمد'], ['c1', 'c3', 'c9']],
            'status active'                      => [['status' => 'active'], ['c1', 'c2', 'c3', 'c7', 'c9']],
            'status inactive'                    => [['status' => 'inactive'], ['c4', 'c6']],
            'status expired (any is_active)'     => [['status' => 'expired'], ['c5', 'c6']],
            'unknown status behaves as all'      => [['status' => 'whatever'], ['c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c7', 'c8', 'c9']],
            'date_from inclusive 00:00:00'       => [['date_from' => '2026-04-01'], ['c3', 'c4', 'c6', 'c7', 'c8', 'c9']],
            'date_to inclusive 23:59:59'         => [['date_to' => '2026-03-31'], ['c1', 'c2', 'c5']],
            'date range'                         => [['date_from' => '2026-03-31', 'date_to' => '2026-04-01'], ['c2', 'c3']],
            'combined'                           => [['status' => 'active', 'instructor_name' => 'أحمد', 'date_to' => '2026-06-30'], ['c1', 'c3']],
        ];
    }

    public function test_dashboard_with_an_invalid_date_does_not_crash(): void
    {
        $this->get(route('admin.dashboard', ['date_from' => 'garbage', 'date_to' => '31/12/2026']))->assertOk();
    }

    public function test_dashboard_shows_the_unread_notification_count_in_the_layout(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('unread: 5', false);
    }

    // ───────────────────────── Campaign page ─────────────────────────

    public function test_campaign_page_statistics_match_the_oracle(): void
    {
        $reviews = DB::table('course_reviews')->where('review_campaign_id', $this->c['c1'])->get();
        $yes = $reviews->where('would_recommend', 1)->count();

        $r = $this->get(route('admin.campaigns.show', $this->c['c1']))->assertOk();

        $this->assertSame($reviews->count(), $r->viewData('reviewCount'));
        $this->assertEquals(round($reviews->avg('overall_rating'), 2), $r->viewData('averageRating'));
        $this->assertEquals(round($reviews->avg('course_content_rating'), 2), $r->viewData('averageContentRating'));
        $this->assertEquals(round($reviews->avg('instructor_rating'), 2), $r->viewData('averageInstructorRating'));
        $this->assertEquals(round($reviews->avg('organization_rating'), 2), $r->viewData('averageOrganizationRating'));
        $this->assertEquals(round($yes / $reviews->count() * 100), $r->viewData('recommendPct'));
    }

    public function test_campaign_page_without_reviews_has_zero_statistics(): void
    {
        $r = $this->get(route('admin.campaigns.show', $this->c['c9']))->assertOk();

        $this->assertSame(0, $r->viewData('reviewCount'));
        $this->assertEquals(0, $r->viewData('averageRating'));
        $this->assertEquals(0, $r->viewData('recommendPct'));
        $this->assertCount(0, $r->viewData('reviews'));
    }

    #[DataProvider('reviewFilterProvider')]
    public function test_campaign_page_review_filters_and_pagination(array $query): void
    {
        $expected = $this->filterReviews($this->reviewsOf('c1'), $query)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $page1 = $this->get(route('admin.campaigns.show', ['campaign' => $this->c['c1']] + $query))->assertOk()->viewData('reviews');
        $this->assertSame(array_slice($expected, 0, 10), $this->ids($page1->items()), 'page 1');
        $this->assertSame(count($expected), $page1->total());
        $this->assertSame(10, $page1->perPage());

        if (count($expected) > 10) {
            $page2 = $this->get(route('admin.campaigns.show', ['campaign' => $this->c['c1'], 'page' => 2] + $query))->assertOk()->viewData('reviews');
            $this->assertSame(array_slice($expected, 10, 10), $this->ids($page2->items()), 'page 2');
        }
    }

    #[DataProvider('reviewFilterProvider')]
    public function test_campaign_reviews_page_filters_and_pagination(array $query): void
    {
        $expected = $this->filterReviews($this->reviewsOf('c1'), $query)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $page1 = $this->get(route('admin.campaigns.reviews', ['campaign' => $this->c['c1']] + $query))->assertOk()->viewData('reviews');
        $this->assertSame(array_slice($expected, 0, 15), $this->ids($page1->items()), 'page 1');
        $this->assertSame(count($expected), $page1->total());
        $this->assertSame(15, $page1->perPage());

        if (count($expected) > 15) {
            $page2 = $this->get(route('admin.campaigns.reviews', ['campaign' => $this->c['c1'], 'page' => 2] + $query))->assertOk()->viewData('reviews');
            $this->assertSame(array_slice($expected, 15, 15), $this->ids($page2->items()), 'page 2');
        }
    }

    public static function reviewFilterProvider(): array
    {
        return [
            'no filter'                  => [[]],
            'rating'                     => [['rating' => '5']],
            'recommend yes'              => [['recommend' => 'yes']],
            'recommend no'               => [['recommend' => 'no']],
            'difficulty'                 => [['difficulty' => 'سهل']],
            'student name like'          => [['student_name' => 'طالب 1']],
            'email like'                 => [['email' => 'r2']],
            'date_from inclusive'        => [['date_from' => '2026-03-16']],
            'date_to inclusive 23:59:59' => [['date_to' => '2026-03-15']],
            'date range one day'         => [['date_from' => '2026-06-30', 'date_to' => '2026-06-30']],
            'range + rating'             => [['date_from' => '2026-02-01', 'date_to' => '2026-07-31', 'rating' => '4']],
            'combined'                   => [['rating' => '3', 'recommend' => 'yes', 'difficulty' => 'متوسط', 'date_from' => '2026-01-01']],
            'no matches'                 => [['student_name' => 'لا يوجد أحد بهذا الاسم']],
        ];
    }

    private function filterReviews($reviews, array $q)
    {
        return $reviews->filter(function ($r) use ($q) {
            $day = substr($r->created_at, 0, 10);

            return (!isset($q['student_name']) || str_contains($r->student_name, $q['student_name']))
                && (!isset($q['email']) || str_contains($r->email, $q['email']))
                && (!isset($q['rating']) || $r->overall_rating == (int) $q['rating'])
                && (!isset($q['recommend']) || (int) $r->would_recommend === ($q['recommend'] === 'yes' ? 1 : 0))
                && (!isset($q['difficulty']) || $r->difficulty_level === $q['difficulty'])
                && (!isset($q['date_from']) || $day >= $q['date_from'])
                && (!isset($q['date_to']) || $day <= $q['date_to']);
        })->values();
    }

    public function test_far_past_and_far_future_dates_behave_like_plain_dates(): void
    {
        $all = $this->reviewsOf('c1')->count();

        foreach (['admin.campaigns.show', 'admin.campaigns.reviews'] as $route) {
            $inclusive = $this->get(route($route, ['campaign' => $this->c['c1'], 'date_from' => '0001-01-01', 'date_to' => '9999-12-31']))->assertOk()->viewData('reviews');
            $this->assertSame($all, $inclusive->total(), "$route: wide range must keep every review");

            $none = $this->get(route($route, ['campaign' => $this->c['c1'], 'date_from' => '9999-01-01']))->assertOk()->viewData('reviews');
            $this->assertSame(0, $none->total(), "$route: a start date in the far future matches nothing");
        }

        $list = $this->get(route('admin.dashboard', ['date_to' => '9999-12-31']))->assertOk()->viewData('campaigns');
        $this->assertSame(9, $this->listTotal($list));
    }

    public function test_review_pages_with_an_invalid_date_do_not_crash(): void
    {
        $this->get(route('admin.campaigns.show', ['campaign' => $this->c['c1'], 'date_from' => 'garbage']))->assertOk();
        $this->get(route('admin.campaigns.reviews', ['campaign' => $this->c['c1'], 'date_to' => 'not-a-date']))->assertOk();
    }

    // ───────────────────────── Notifications ─────────────────────────

    public function test_notifications_page_lists_newest_first_and_marks_everything_read(): void
    {
        $expected = DB::table('admin_notifications')->orderByDesc('created_at')->limit(20)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $r = $this->get(route('admin.notifications.index'))->assertOk();

        $this->assertSame($expected, $this->ids($r->viewData('notifications')->items()));
        $this->assertSame(0, DB::table('admin_notifications')->whereNull('read_at')->count());
    }
}
