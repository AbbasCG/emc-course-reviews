<?php

namespace Tests\Feature;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PR-2: the review form must not block different students just because they share
 * a Wi-Fi/NAT address, while one browser (or one IP) flooding it is still stopped.
 *
 * NOTE: these are FUNCTIONAL tests of the limiter policy with simulated clients in a
 * single PHP process. They do NOT measure server capacity or latency under real
 * concurrent load; that is what the Staging k6 plan (PR-4) is for.
 */
class ReviewRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private ReviewCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        // Cookies are sent plain so each simulated student keeps its own session id.
        $this->withoutMiddleware([EncryptCookies::class]);
        $this->campaign = ReviewCampaign::factory()->create();
    }

    private function sessionId(int $student): string
    {
        return substr(hash('sha256', "student-session-$student"), 0, 40);
    }

    /** One student (own device/session) submitting the form from a given public IP. */
    private function submitAs(int $student, string $ip, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders($headers)
            ->withCredentials()
            ->withUnencryptedCookie(config('session.cookie'), $this->sessionId($student))
            ->postJson(route('review.store', $this->campaign->token), [
                'student_name'          => "طالب $student",
                'email'                 => "student$student@example.test",
                'overall_rating'        => 5,
                'course_content_rating' => 4,
                'instructor_rating'     => 5,
                'organization_rating'   => 4,
                'difficulty_level'      => 'متوسط',
                'what_did_you_like'     => 'الشرح',
                'what_can_be_improved'  => 'الوقت',
                'would_recommend'       => true,
            ]);
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

    // ───────────────────────── Different networks ─────────────────────────

    public function test_100_students_on_100_different_networks_are_all_accepted(): void
    {
        $responses = [];
        foreach (range(1, 100) as $i) {
            $responses[] = $this->submitAs($i, "198.51.100.$i");
        }

        $this->assertSame([200 => 100], $this->statuses($responses));
        $this->assertDatabaseCount('course_reviews', 100);
    }

    public function test_500_students_on_500_different_networks_are_all_accepted(): void
    {
        $responses = [];
        foreach (range(1, 500) as $i) {
            $responses[] = $this->submitAs($i, '10.' . intdiv($i, 250) . '.' . ($i % 250) . '.1');
        }

        $this->assertSame([200 => 500], $this->statuses($responses));
        $this->assertDatabaseCount('course_reviews', 500);
    }

    // ───────────────────────── Shared networks ─────────────────────────

    public function test_100_different_students_sharing_one_wifi_are_all_accepted(): void
    {
        // The old throttle:5,1 would have rejected 95 of them.
        $responses = [];
        foreach (range(1, 100) as $i) {
            $responses[] = $this->submitAs($i, '203.0.113.10');
        }

        $this->assertSame([200 => 100], $this->statuses($responses));
        $this->assertDatabaseCount('course_reviews', 100);
    }

    public function test_500_students_on_25_shared_networks_are_all_accepted(): void
    {
        $responses = [];
        foreach (range(1, 500) as $i) {
            $responses[] = $this->submitAs($i, '192.0.2.' . (($i % 25) + 1)); // 20 students per network
        }

        $this->assertSame([200 => 500], $this->statuses($responses));
        $this->assertDatabaseCount('course_reviews', 500);
    }

    public function test_500_students_behind_one_ip_are_accepted_when_they_submit_over_two_minutes(): void
    {
        $responses = [];
        foreach (range(1, 250) as $i) {
            $responses[] = $this->submitAs($i, '203.0.113.20');
        }
        $this->travel(61)->seconds(); // next rate-limit window
        foreach (range(251, 500) as $i) {
            $responses[] = $this->submitAs($i, '203.0.113.20');
        }

        $this->assertSame([200 => 500], $this->statuses($responses));
        $this->assertDatabaseCount('course_reviews', 500);
    }

    public function test_documented_boundary_500_students_behind_one_ip_in_a_single_minute_hit_the_ip_ceiling(): void
    {
        $ceiling = config('ratelimits.review_submit.per_ip');
        $responses = [];
        foreach (range(1, 500) as $i) {
            $responses[] = $this->submitAs($i, '203.0.113.30');
        }

        // The first $ceiling are stored, the rest wait for the next minute (raise REVIEW_RATE_PER_IP if a venue needs more).
        $this->assertSame([200 => $ceiling, 429 => 500 - $ceiling], $this->statuses($responses));
        $this->assertDatabaseCount('course_reviews', $ceiling);
    }

    // ───────────────────────── Spam / repeated-request protection ─────────────────────────

    public function test_one_ip_flooding_is_limited_while_other_networks_stay_unaffected(): void
    {
        config(['ratelimits.review_submit.per_ip' => 50]);

        $flood = [];
        foreach (range(1, 60) as $i) {
            $flood[] = $this->submitAs($i, '203.0.113.40');
        }
        $other = $this->submitAs(1000, '203.0.113.41');

        $this->assertSame([200 => 50, 429 => 10], $this->statuses($flood));
        $other->assertOk();
    }

    public function test_one_browser_hammering_the_form_is_limited_without_affecting_neighbours(): void
    {
        $perSession = config('ratelimits.review_submit.per_session');
        $attacker = 7777;

        $hammer = [];
        foreach (range(1, $perSession + 4) as $attempt) {
            // same session, a fresh email each time (so only the limiter can stop it)
            $hammer[] = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
                ->withCredentials()
                ->withUnencryptedCookie(config('session.cookie'), $this->sessionId($attacker))
                ->postJson(route('review.store', $this->campaign->token), [
                    'student_name' => 'x', 'email' => "spam$attempt@example.test", 'overall_rating' => 5, 'course_content_rating' => 4,
                    'instructor_rating' => 5, 'organization_rating' => 4, 'difficulty_level' => 'متوسط',
                    'what_did_you_like' => 'a', 'what_can_be_improved' => 'b', 'would_recommend' => true,
                ]);
        }
        $neighbour = $this->submitAs(8888, '203.0.113.50'); // same Wi-Fi, different device

        $this->assertSame([200 => $perSession, 429 => 4], $this->statuses($hammer));
        $neighbour->assertOk();
    }

    public function test_rotating_x_forwarded_for_does_not_evade_the_ip_limit_when_no_proxy_is_trusted(): void
    {
        config(['ratelimits.review_submit.per_ip' => 5, 'trustedproxy.proxies' => []]);

        $responses = [];
        foreach (range(1, 8) as $i) {
            $responses[] = $this->submitAs($i, '203.0.113.60', ['X-Forwarded-For' => "198.51.100.$i"]);
        }

        $this->assertSame([200 => 5, 429 => 3], $this->statuses($responses));
    }

    public function test_cookieless_requests_cannot_dodge_the_ip_ceiling_with_a_fresh_session_each_time(): void
    {
        // The per-session limit only stops naive repeat-submits; the IP ceiling is the flood control.
        config(['ratelimits.review_submit.per_ip' => 4]);

        $responses = [];
        foreach (range(1, 7) as $i) {
            $responses[] = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.95']) // no cookie => new session per request
                ->postJson(route('review.store', $this->campaign->token), [
                    'student_name' => "c$i", 'email' => "c$i@example.test", 'overall_rating' => 5, 'course_content_rating' => 4,
                    'instructor_rating' => 5, 'organization_rating' => 4, 'difficulty_level' => 'متوسط',
                    'what_did_you_like' => 'a', 'what_can_be_improved' => 'b', 'would_recommend' => true,
                ]);
        }

        $this->assertSame([200 => 4, 429 => 3], $this->statuses($responses));
    }

    public function test_ipv6_clients_are_limited_per_64_prefix_so_rotating_addresses_does_not_help(): void
    {
        config(['ratelimits.review_submit.per_ip' => 3]);

        $same64 = [];
        foreach ([1, 2, 3, 4] as $i) {
            $same64[] = $this->submitAs($i, "2001:db8:abcd:12::$i"); // 4 different addresses, one /64
        }
        $other64 = $this->submitAs(50, '2001:db8:abcd:13::1');

        $this->assertSame([200 => 3, 429 => 1], $this->statuses($same64));
        $other64->assertOk();
    }

    public function test_limited_response_is_json_with_retry_after(): void
    {
        config(['ratelimits.review_submit.per_ip' => 1]);

        $this->submitAs(1, '203.0.113.70')->assertOk();
        $this->submitAs(2, '203.0.113.70')
            ->assertStatus(429)
            ->assertJsonPath('message', 'يرجى الانتظار قليلًا قبل المحاولة مرة أخرى.')
            ->assertHeader('Retry-After');
    }

    public function test_the_limit_resets_after_the_window(): void
    {
        config(['ratelimits.review_submit.per_ip' => 2]);

        $this->submitAs(1, '203.0.113.80')->assertOk();
        $this->submitAs(2, '203.0.113.80')->assertOk();
        $this->submitAs(3, '203.0.113.80')->assertStatus(429);

        $this->travel(61)->seconds();

        $this->submitAs(3, '203.0.113.80')->assertOk();
    }

    // ───────────────────────── Wiring ─────────────────────────

    public function test_review_route_uses_the_named_limiter_not_the_old_five_per_minute_throttle(): void
    {
        $middleware = Route::getRoutes()->getByName('review.store')->gatherMiddleware();

        $this->assertContains('throttle:review-submit', $middleware);
        $this->assertNotContains('throttle:5,1', $middleware);
    }

    public function test_viewing_the_form_is_not_rate_limited_by_the_submission_limiter(): void
    {
        config(['ratelimits.review_submit.per_ip' => 1]);
        $this->submitAs(1, '203.0.113.90')->assertOk();
        $this->submitAs(2, '203.0.113.90')->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.90'])
            ->get(route('review.show', $this->campaign->token))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.90'])
            ->get(route('review.success', $this->campaign->token))->assertOk();

        $this->assertSame(1, CourseReview::count());
    }
}
