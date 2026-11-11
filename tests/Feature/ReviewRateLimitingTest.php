<?php

namespace Tests\Feature;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReadsPerformanceLog;
use Tests\TestCase;

/**
 * PR-5: smart, layered rate limiting of POST /review/{token} (config/ratelimits.php, docs/RATE-LIMITING.md).
 *
 * NOTE: these are FUNCTIONAL tests of the policy with simulated clients in a single PHP process. They do NOT measure server
 * capacity or latency; the real parallel behaviour of the counter is covered by WindowCounterTest and
 * ReviewSubmissionConcurrencyTest (separate PHP processes), and capacity by the Staging plan in loadtest/.
 */
class ReviewRateLimitingTest extends TestCase
{
    use ReadsPerformanceLog;
    use RefreshDatabase;

    private ReviewCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePerformanceLog();
        // Cookies are sent plain so each simulated student keeps its own session id.
        $this->withoutMiddleware([EncryptCookies::class]);
        $this->campaign = ReviewCampaign::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownPerformanceLog();
        parent::tearDown();
    }

    private function sessionId(int|string $student): string
    {
        return substr(hash('sha256', "student-session-$student"), 0, 40);
    }

    /**
     * In one test process the cookie jar and the session store are shared by every simulated request and merge the previous
     * request into the next. A real request starts with NO cookies and an empty store and loads only ITS session, so reset
     * both; otherwise the first student's cookie and ticket would leak to everybody after it (including the bots).
     */
    private function freshClient(): void
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->app['session.store']->flush();
    }

    private function client(int|string $student, string $ip, array $headers = [])
    {
        $this->freshClient();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders($headers)
            ->withCredentials()
            ->withUnencryptedCookie(config('session.cookie'), $this->sessionId($student));
    }

    /** The student opens the form (this is what gives the session its "trusted" ticket). */
    private function openForm(int|string $student, string $ip, ?ReviewCampaign $campaign = null, array $headers = [])
    {
        return $this->client($student, $ip, $headers)->get(route('review.show', ($campaign ?? $this->campaign)->token));
    }

    private function payload(string $email): array
    {
        return [
            'student_name' => 'طالب', 'email' => $email, 'overall_rating' => 5, 'course_content_rating' => 4, 'instructor_rating' => 5,
            'organization_rating' => 4, 'difficulty_level' => 'متوسط', 'what_did_you_like' => 'الشرح', 'what_can_be_improved' => 'الوقت', 'would_recommend' => true,
        ];
    }

    private function submit(int|string $student, string $ip, ?string $email = null, array $headers = [], ?ReviewCampaign $campaign = null)
    {
        return $this->client($student, $ip, $headers)
            ->postJson(route('review.store', ($campaign ?? $this->campaign)->token), $this->payload($email ?? "student$student@example.test"));
    }

    /** Opens the form for every student, lets the minimum fill time pass, then every student submits. @return array<int, \Illuminate\Testing\TestResponse> */
    private function classroomSubmits(array $students, callable $ipOf): array
    {
        foreach ($students as $s) {
            $this->openForm($s, $ipOf($s))->assertOk();
        }
        $this->travel(config('ratelimits.review_submit.ticket_min_seconds') + 1)->seconds();

        $responses = [];
        foreach ($students as $s) {
            $responses[] = $this->submit($s, $ipOf($s));
        }

        return $responses;
    }

    /** @return array<int,int> status => count */
    private function statuses(iterable $responses): array
    {
        $counts = [];
        foreach ($responses as $response) {
            $counts[$response->getStatusCode()] = ($counts[$response->getStatusCode()] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    // ───────────────────────── The PR-4 problem: a whole class behind one IP ─────────────────────────

    public function test_500_students_behind_one_ip_in_a_single_minute_are_all_accepted(): void
    {
        // PR-4 lab: with the old 300/min ceiling only 600 of the burst were stored and 2,879 requests got 429.
        $responses = $this->classroomSubmits(range(1, 500), fn () => '203.0.113.30');

        $this->assertSame([200 => 500], $this->statuses($responses));
        $this->assertDatabaseCount('course_reviews', 500);
    }

    public function test_the_default_ip_ceiling_is_derived_from_the_class_size_not_picked_at_random(): void
    {
        $this->assertSame(500, config('ratelimits.review_submit.max_class_size'));
        $this->assertSame(600, config('ratelimits.review_submit.per_ip'));                 // 500 x 1.2 for retries
        $this->assertSame(1800, config('ratelimits.review_submit.per_ip_campaign_hour'));  // 3 attempts per student per hour
        $this->assertSame(6000, config('ratelimits.review_submit.per_campaign'));          // ~100 requests per second, the PR-4 lab level
    }

    public function test_100_students_on_100_different_networks_and_500_on_25_shared_networks_are_accepted(): void
    {
        $a = $this->classroomSubmits(range(1, 100), fn ($i) => "198.51.100.$i");
        $this->assertSame([200 => 100], $this->statuses($a));

        $b = $this->classroomSubmits(range(101, 600), fn ($i) => '192.0.2.' . (($i % 25) + 1));
        $this->assertSame([200 => 500], $this->statuses($b));
        $this->assertDatabaseCount('course_reviews', 600);
    }

    // ───────────────────────── Floods are still stopped ─────────────────────────

    public function test_one_ip_flooding_with_real_form_views_is_limited_while_other_networks_stay_unaffected(): void
    {
        config(['ratelimits.review_submit.per_ip' => 50]);

        $flood = $this->classroomSubmits(range(1, 60), fn () => '203.0.113.40');
        $this->openForm(1000, '203.0.113.41');
        $this->travel(4)->seconds();
        $other = $this->submit(1000, '203.0.113.41');

        $this->assertSame([200 => 50, 429 => 10], $this->statuses($flood));
        $other->assertOk();
    }

    public function test_a_direct_post_flood_without_form_views_hits_the_strict_untrusted_limit(): void
    {
        $limit = config('ratelimits.review_submit.untrusted_per_ip');

        $responses = [];
        foreach (range(1, $limit + 5) as $i) {
            $this->freshClient();   // a bot has no cookie: it starts with an empty session
            $responses[] = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.95'])
                ->postJson(route('review.store', $this->campaign->token), $this->payload("bot$i@example.test"));
        }

        $this->assertSame([200 => $limit, 429 => 5], $this->statuses($responses));
    }

    public function test_a_bot_flooding_directly_cannot_use_up_the_budget_of_students_on_the_same_network(): void
    {
        $ip = '203.0.113.96';
        $students = range(1, 30);
        foreach ($students as $s) {
            $this->openForm($s, $ip)->assertOk();
        }
        $this->travel(4)->seconds();

        foreach (range(1, 200) as $i) {   // the bot hammers the same IP without ever opening the form
            $this->freshClient();
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('review.store', $this->campaign->token), $this->payload("bot$i@example.test"));
        }

        $results = [];
        foreach ($students as $s) {
            $results[] = $this->submit($s, $ip);
        }

        $this->assertSame([200 => 30], $this->statuses($results), 'every real student on the shared network still gets through');
    }

    public function test_rotating_sessions_does_not_evade_the_limits(): void
    {
        config(['ratelimits.review_submit.untrusted_per_ip' => 4]);

        $responses = [];
        foreach (range(1, 9) as $i) {
            $responses[] = $this->submit("rotating-$i", '203.0.113.97');   // a brand-new session id every time, no form view
        }

        $this->assertSame([200 => 4, 429 => 5], $this->statuses($responses));
    }

    public function test_a_flood_of_form_views_cannot_demote_the_class_to_the_untrusted_lane(): void
    {
        $ip = '203.0.113.98';
        foreach (range(1, 40) as $i) {   // a script on the shared network opens thousands of forms (40 here)
            $this->openForm("farm-$i", $ip)->assertOk();
        }
        $this->openForm('real-student', $ip)->assertOk();
        $this->travel(4)->seconds();

        config(['ratelimits.review_submit.untrusted_per_ip' => 0]);   // an untrusted request would now be refused at once

        $this->submit('real-student', $ip)->assertOk();   // the real student kept the trusted lane
    }

    public function test_failed_validations_and_retries_do_not_count_towards_the_hourly_cap(): void
    {
        config(['ratelimits.review_submit.per_ip_campaign_hour' => 3]);
        $ip = '203.0.113.99';
        foreach (range(1, 12) as $i) {
            $this->openForm($i, $ip);
        }
        $this->travel(4)->seconds();

        foreach (range(1, 5) as $i) {   // invalid answers: 422, nothing is stored
            $this->client($i, $ip)->postJson(route('review.store', $this->campaign->token), ['student_name' => ''])->assertStatus(422);
        }
        $stored = [];
        foreach (range(6, 8) as $i) {
            $stored[] = $this->submit($i, $ip);
        }
        $afterCap = $this->submit(9, $ip);

        $this->assertSame([200 => 3], $this->statuses($stored), 'the 422s did not use up the cap');
        $afterCap->assertStatus(429);
    }

    public function test_a_submission_right_after_opening_the_form_is_not_trusted_yet(): void
    {
        config(['ratelimits.review_submit.untrusted_per_ip' => 1]);

        $results = [];
        foreach ([1, 2, 3] as $s) {
            $this->openForm($s, '203.0.113.99');
            $results[] = $this->submit($s, '203.0.113.99');   // no time has passed: a human cannot fill four steps in 0 seconds
        }

        $this->assertSame([200 => 1, 429 => 2], $this->statuses($results));
    }

    public function test_the_ticket_is_used_up_by_a_stored_review(): void
    {
        config(['ratelimits.review_submit.untrusted_per_ip' => 0]);   // an untrusted request is refused at once

        $this->openForm(1, '203.0.113.100');
        $this->travel(4)->seconds();
        $this->submit(1, '203.0.113.100', 'one@example.test')->assertOk();
        $this->submit(1, '203.0.113.100', 'two@example.test')->assertStatus(429);   // same session, no new form view

        $this->openForm(1, '203.0.113.100');   // reloading the form earns a new ticket
        $this->travel(4)->seconds();
        $this->submit(1, '203.0.113.100', 'three@example.test')->assertOk();
    }

    public function test_one_browser_hammering_the_form_is_limited_without_affecting_neighbours(): void
    {
        $perSession = config('ratelimits.review_submit.per_session');
        $this->openForm(7777, '203.0.113.50');
        $this->openForm(8888, '203.0.113.50');
        $this->travel(4)->seconds();

        $hammer = [];
        foreach (range(1, $perSession + 4) as $attempt) {
            $hammer[] = $this->submit(7777, '203.0.113.50', "spam$attempt@example.test");   // a fresh e-mail each time: only the limiter can stop it
        }

        $neighbour = $this->submit(8888, '203.0.113.50');   // same Wi-Fi, different device

        $this->assertSame([200 => $perSession, 429 => 4], $this->statuses($hammer));
        $neighbour->assertOk();
    }

    public function test_rotating_x_forwarded_for_does_not_evade_the_limits_when_no_proxy_is_trusted(): void
    {
        config(['ratelimits.review_submit.per_ip' => 5, 'trustedproxy.proxies' => []]);

        foreach (range(1, 8) as $i) {
            $this->openForm($i, '203.0.113.60', null, ['X-Forwarded-For' => "198.51.100.$i"]);
        }
        $this->travel(4)->seconds();
        $responses = [];
        foreach (range(1, 8) as $i) {
            $responses[] = $this->submit($i, '203.0.113.60', null, ['X-Forwarded-For' => "198.51.100.$i"]);
        }

        $this->assertSame([200 => 5, 429 => 3], $this->statuses($responses));
    }

    public function test_ipv6_clients_are_limited_per_64_prefix_so_rotating_addresses_does_not_help(): void
    {
        config(['ratelimits.review_submit.per_ip' => 3]);

        $same64 = $this->classroomSubmits([1, 2, 3, 4], fn ($i) => "2001:db8:abcd:12::$i");   // 4 different addresses, one /64
        $this->openForm(50, '2001:db8:abcd:13::1');
        $this->travel(4)->seconds();
        $other64 = $this->submit(50, '2001:db8:abcd:13::1');

        $this->assertSame([200 => 3, 429 => 1], $this->statuses($same64));
        $other64->assertOk();
    }

    // ───────────────────────── Campaign-wide and hourly layers ─────────────────────────

    public function test_a_distributed_flood_against_one_campaign_is_stopped_but_other_campaigns_are_not(): void
    {
        config(['ratelimits.review_submit.per_campaign' => 10]);
        $other = ReviewCampaign::factory()->create();

        $responses = $this->classroomSubmits(range(1, 14), fn ($i) => "198.51.100.$i");   // 14 different networks
        $this->openForm(500, '192.0.2.9', $other);
        $this->travel(4)->seconds();
        $unaffected = $this->submit(500, '192.0.2.9', null, [], $other);

        $this->assertSame([200 => 10, 429 => 4], $this->statuses($responses));
        $unaffected->assertOk();
    }

    public function test_one_network_cannot_store_more_than_the_hourly_cap_on_a_campaign(): void
    {
        config(['ratelimits.review_submit.per_ip_campaign_hour' => 5]);

        $first = $this->classroomSubmits(range(1, 7), fn () => '203.0.113.110');
        $this->assertSame([200 => 5, 429 => 2], $this->statuses($first));

        $this->travel(61)->seconds();   // the per-minute windows are over, the hourly cap is not
        $this->openForm(20, '203.0.113.110');
        $this->travel(4)->seconds();
        $this->submit(20, '203.0.113.110')->assertStatus(429);

        $this->travel(3600)->seconds();
        $this->openForm(21, '203.0.113.110');
        $this->travel(4)->seconds();
        $this->submit(21, '203.0.113.110')->assertOk();
    }

    // ───────────────────────── Responses, windows, privacy ─────────────────────────

    public function test_limited_response_is_json_with_retry_after_and_is_not_cacheable(): void
    {
        config(['ratelimits.review_submit.per_ip' => 1]);

        $this->classroomSubmits([1], fn () => '203.0.113.70');
        $this->openForm(2, '203.0.113.70');
        $this->travel(4)->seconds();
        $response = $this->submit(2, '203.0.113.70');

        $response->assertStatus(429)
            ->assertJsonPath('message', 'يرجى الانتظار قليلًا قبل المحاولة مرة أخرى.')
            ->assertHeader('Retry-After')
            ->assertHeader('Cache-Control', 'no-store, private');
        $seconds = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThanOrEqual(1, $seconds);
        $this->assertLessThanOrEqual(60, $seconds);
        $this->assertSame($seconds, $response->json('retry_after'));
    }

    public function test_the_limit_resets_after_the_window(): void
    {
        config(['ratelimits.review_submit.per_ip' => 2]);

        $first = $this->classroomSubmits([1, 2, 3], fn () => '203.0.113.80');
        $this->assertSame([200 => 2, 429 => 1], $this->statuses($first));

        $this->travel(61)->seconds();

        $this->submit(3, '203.0.113.80')->assertOk();
    }

    public function test_counter_files_never_contain_ips_session_ids_tokens_or_e_mails_in_their_names_or_content(): void
    {
        $this->classroomSubmits([1, 2], fn () => '203.0.113.120');
        $this->post(route('login.post'), ['email' => 'admin@example.test', 'password' => 'x']);

        $files = glob($this->rateLimitDirectory() . '/*');
        $this->assertNotEmpty($files);
        $haystack = implode("\n", array_map(fn ($f) => basename($f) . "\n" . file_get_contents($f), $files));
        foreach (['203.0.113.120', '203-0-113', $this->sessionId(1), $this->campaign->token, 'student1@example.test', 'admin@example.test', 'example.test'] as $secret) {
            $this->assertStringNotContainsString($secret, $haystack);
        }
        $this->assertMatchesRegularExpression('/^[a-z-]+-[a-f0-9]{40}$/', basename($files[0]));
    }

    public function test_the_429_log_line_names_the_layer_but_not_the_visitor(): void
    {
        config(['ratelimits.review_submit.per_ip' => 1]);
        $this->classroomSubmits([1], fn () => '203.0.113.130');
        $this->openForm(2, '203.0.113.130');
        $this->travel(4)->seconds();
        $this->submit(2, '203.0.113.130')->assertStatus(429);

        $entry = collect($this->performanceEntries())->firstWhere('context.status', 429);
        $this->assertSame('per_ip', $entry['context']['rate_limit']);
        $this->assertSame('review.store', $entry['context']['route']);
        $raw = $this->performanceLogRaw();
        foreach (['203.0.113.130', $this->campaign->token, 'student2@example.test'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }
    }

    public function test_an_unwritable_counter_directory_fails_open_instead_of_blocking_students(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'emc-notadir');
        config(['ratelimits.store_path' => $file . '/nested']);   // a path below a regular file can never be created

        try {
            $responses = $this->classroomSubmits([1, 2, 3], fn () => '203.0.113.140');

            $this->assertSame([200 => 3], $this->statuses($responses));
        } finally {
            @unlink($file);
        }
    }

    // ───────────────────────── Wiring ─────────────────────────

    public function test_review_route_uses_the_layered_middleware_not_the_old_throttles(): void
    {
        $middleware = \Illuminate\Support\Facades\Route::getRoutes()->getByName('review.store')->gatherMiddleware();

        $this->assertContains(\App\Http\Middleware\ThrottleReviewSubmission::class, $middleware);
        $this->assertNotContains('throttle:review-submit', $middleware);
        $this->assertNotContains('throttle:5,1', $middleware);
    }

    public function test_viewing_the_form_and_the_success_page_is_never_rate_limited(): void
    {
        config(['ratelimits.review_submit.per_ip' => 1]);
        $this->classroomSubmits([1], fn () => '203.0.113.90');

        foreach (range(1, 5) as $i) {
            $this->openForm("viewer-$i", '203.0.113.90')->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.90'])->get(route('review.success', $this->campaign->token))->assertOk();

        $this->assertSame(1, CourseReview::count());
    }
}
