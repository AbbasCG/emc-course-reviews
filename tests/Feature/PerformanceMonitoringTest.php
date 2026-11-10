<?php

namespace Tests\Feature;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReadsPerformanceLog;
use Tests\TestCase;

/**
 * PR-4 monitoring regression tests: request ids, structured logging of 429/500/slow requests and slow SQL,
 * no personal data in the log, monitoring that can never break a request, and no information leaks.
 */
class PerformanceMonitoringTest extends TestCase
{
    use ReadsPerformanceLog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePerformanceLog();
        config(['monitoring.high_memory_mb' => 100000]);   // the PHPUnit process itself is far above the production threshold
        $this->withoutMiddleware([EncryptCookies::class]);
    }

    protected function tearDown(): void
    {
        $this->tearDownPerformanceLog();
        parent::tearDown();
    }

    private function review(ReviewCampaign $campaign, array $override = [])
    {
        return $this->withCredentials()
            ->withUnencryptedCookie(config('session.cookie'), substr(hash('sha256', serialize($override) . microtime()), 0, 40))
            ->postJson(route('review.store', $campaign->token), array_merge([
                'student_name' => 'سارة السري', 'email' => 'secret.student@example.test', 'overall_rating' => 5,
                'course_content_rating' => 4, 'instructor_rating' => 5, 'organization_rating' => 4,
                'difficulty_level' => 'متوسط', 'what_did_you_like' => 'نص خاص جداً', 'what_can_be_improved' => 'x', 'would_recommend' => true,
            ], $override));
    }

    // ───────────────────────── Request id ─────────────────────────

    public function test_every_response_carries_a_request_id(): void
    {
        $campaign = ReviewCampaign::factory()->create();

        foreach ([
            $this->get(route('review.show', $campaign->token)),
            $this->get(route('review.show', 'no-such-token')),     // 404
            $this->get('/up'),
        ] as $response) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]{8,64}$/', $response->headers->get('X-Request-Id'));
        }
    }

    public function test_a_harmless_incoming_request_id_is_kept_and_a_hostile_one_is_replaced(): void
    {
        $this->withHeaders(['X-Request-Id' => 'abc123-trace_ID.9'])->get('/up')->assertHeader('X-Request-Id', 'abc123-trace_ID.9');

        foreach (['short', str_repeat('a', 200), "bad id with spaces", 'drop;table', '<script>alert(1)</script>', "x\r\nSet-Cookie: a=b", "abcdefgh\n"] as $hostile) {
            $id = $this->withHeaders(['X-Request-Id' => $hostile])->get('/up')->headers->get('X-Request-Id');
            $this->assertNotSame($hostile, $id);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]{8,64}$/', $id);
        }
    }

    public function test_request_ids_are_unique_per_request(): void
    {
        $a = $this->get('/up')->headers->get('X-Request-Id');
        $b = $this->get('/up')->headers->get('X-Request-Id');

        $this->assertNotSame($a, $b);
    }

    public function test_the_php_version_header_is_not_exposed(): void
    {
        $this->get('/up')->assertHeaderMissing('X-Powered-By');
    }

    // ───────────────────────── Status codes ─────────────────────────

    public function test_http_429_is_logged_with_the_route_name_and_the_same_request_id(): void
    {
        config(['ratelimits.review_submit.per_ip' => 1, 'ratelimits.review_submit.per_session' => 50]);
        $campaign = ReviewCampaign::factory()->create();

        $this->review($campaign, ['email' => 'a@example.test'])->assertOk();
        $limited = $this->review($campaign, ['email' => 'b@example.test'])->assertStatus(429);

        $entries = $this->performanceEntries();
        $this->assertCount(1, $entries, 'only the 429 is exceptional');
        $this->assertSame('http.error', $entries[0]['message']);
        $this->assertSame(429, $entries[0]['context']['status']);
        $this->assertSame('review.store', $entries[0]['context']['route']);
        $this->assertSame($limited->headers->get('X-Request-Id'), $entries[0]['context']['request_id']);
        $this->assertSame('POST', $entries[0]['context']['method']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $entries[0]['context']['ip_hash']);
    }

    #[DataProvider('monitoredStatuses')]
    public function test_monitored_statuses_are_logged_as_errors(int $status): void
    {
        Route::get('/__status/' . $status, fn () => response('x', $status))->name('probe.status');

        $this->get('/__status/' . $status)->assertStatus($status);

        $entries = $this->performanceEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('http.error', $entries[0]['message']);
        $this->assertSame($status, $entries[0]['context']['status']);
        $this->assertSame($status >= 500 ? 'ERROR' : 'WARNING', $entries[0]['level_name']);
    }

    public static function monitoredStatuses(): array
    {
        return ['429' => [429], '500' => [500], '502' => [502], '503' => [503], '504' => [504]];
    }

    public function test_an_unhandled_exception_returns_500_and_is_logged(): void
    {
        config(['app.debug' => false]);
        Route::get('/__boom', fn () => throw new \RuntimeException('boom'));

        $this->get('/__boom')->assertStatus(500);

        $entries = $this->performanceEntries();
        $this->assertSame(500, $entries[0]['context']['status']);
        $this->assertSame('unnamed', $entries[0]['context']['route']);
    }

    public function test_normal_requests_write_nothing(): void
    {
        $campaign = ReviewCampaign::factory()->create();

        $this->get(route('review.show', $campaign->token))->assertOk();
        $this->get(route('review.show', 'unknown'))->assertNotFound();   // 404 is not a monitored status

        $this->assertSame([], $this->performanceEntries());
    }

    // ───────────────────────── Slow requests and slow SQL ─────────────────────────

    public function test_slow_requests_are_logged_with_timings_memory_and_query_counters(): void
    {
        config(['monitoring.slow_request_ms' => 0]);
        $campaign = ReviewCampaign::factory()->create();

        $this->get(route('review.show', $campaign->token))->assertOk();

        $entry = $this->performanceEntries()[0];
        $this->assertSame('http.slow', $entry['message']);
        $this->assertSame('review.show', $entry['context']['route']);
        $this->assertGreaterThanOrEqual(1, $entry['context']['queries']);
        $this->assertIsFloat($entry['context']['duration_ms'] + 0.0);
        $this->assertGreaterThan(0, $entry['context']['memory_peak_mb']);
        $this->assertArrayHasKey('query_ms', $entry['context']);
    }

    public function test_slow_queries_log_the_sql_template_but_never_the_bindings(): void
    {
        config(['monitoring.slow_query_ms' => 0]);
        $this->actingAs(User::factory()->create());
        $campaign = ReviewCampaign::factory()->create();
        CourseReview::factory()->create(['review_campaign_id' => $campaign->id]);

        $this->get(route('admin.campaigns.reviews', ['campaign' => $campaign, 'student_name' => 'TOP-SECRET-NAME', 'email' => 'private@leak.test']))->assertOk();

        $raw = $this->performanceLogRaw();
        $this->assertStringContainsString('slow_queries', $raw);
        $this->assertStringContainsString('course_reviews', $raw);
        $this->assertStringContainsString('like ?', $raw, 'the template keeps placeholders');
        $this->assertStringNotContainsString('TOP-SECRET-NAME', $raw);
        $this->assertStringNotContainsString('private@leak.test', $raw);
    }

    public function test_high_memory_requests_are_flagged_only_above_the_threshold(): void
    {
        config(['monitoring.high_memory_mb' => 100000]);
        $this->get('/up')->assertOk();
        $this->assertSame([], $this->performanceEntries(), 'a request under the threshold must not be flagged');

        config(['monitoring.high_memory_mb' => 1]);
        $this->get('/up')->assertOk();
        $this->assertSame('http.slow', $this->performanceEntries()[0]['message']);
    }

    public function test_the_monitor_runs_right_after_the_request_id_so_early_exits_are_seen(): void
    {
        $global = collect(app(\Illuminate\Contracts\Http\Kernel::class)->getGlobalMiddleware())->values();

        $this->assertSame(\App\Http\Middleware\RequestId::class, $global[0]);
        $this->assertSame(\App\Http\Middleware\PerformanceMonitor::class, $global[1]);
    }

    public function test_all_requests_are_logged_only_when_explicitly_enabled(): void
    {
        $this->get('/up')->assertOk();
        $this->assertSame([], $this->performanceEntries());

        config(['monitoring.log_all_requests' => true]);
        $this->get('/up')->assertOk();

        $entries = $this->performanceEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('http.request', $entries[0]['message']);
        $this->assertSame('INFO', $entries[0]['level_name']);
    }

    public function test_monitoring_can_be_switched_off(): void
    {
        config(['monitoring.enabled' => false, 'monitoring.log_all_requests' => true]);

        $this->get('/up')->assertOk();

        $this->assertSame([], $this->performanceEntries());
    }

    // ───────────────────────── Privacy ─────────────────────────

    public function test_log_lines_never_contain_student_data_tokens_urls_or_raw_ips(): void
    {
        config(['ratelimits.review_submit.per_ip' => 2, 'ratelimits.review_submit.per_session' => 50, 'monitoring.log_all_requests' => true, 'monitoring.slow_query_ms' => 0]); // the 422 below also counts
        $campaign = ReviewCampaign::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->withHeaders(['X-Hcdn-Request-Id' => 'cdnreq-123456'])
            ->withCredentials()->withUnencryptedCookie(config('session.cookie'), str_repeat('a', 40))
            ->postJson(route('review.store', $campaign->token) . '?utm=private-query', ['student_name' => 'سارة السري', 'email' => 'secret.student@example.test'])
            ->assertStatus(422);
        $this->review($campaign)->assertOk();
        $this->review($campaign, ['email' => 'other@example.test'])->assertStatus(429);

        $raw = $this->performanceLogRaw();
        $this->assertNotSame('', $raw);
        foreach (['سارة', 'secret.student', 'other@example', 'نص خاص', $campaign->token, '/review/', 'private-query', '203.0.113.77', 'laravel_session', 'emc-session'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $raw, "the monitoring log must not contain: $forbidden");
        }
        $this->assertStringContainsString('cdnreq-123456', $raw, 'a safe CDN request id helps support correlate requests');
    }

    public function test_the_request_id_reaches_application_log_lines_for_correlation(): void
    {
        $captured = [];
        \Illuminate\Support\Facades\Log::listen(function ($message) use (&$captured) {
            $captured[] = $message->context;
        });
        Route::get('/__logs', function () {
            \Illuminate\Support\Facades\Log::warning('something happened');

            return 'ok';
        });

        $id = $this->get('/__logs')->headers->get('X-Request-Id');

        $this->assertContains($id, array_column($captured, 'request_id'));
    }

    // ───────────────────────── Monitoring never breaks the app ─────────────────────────

    public function test_a_broken_log_channel_does_not_affect_the_response(): void
    {
        config(['monitoring.channel' => 'channel-that-does-not-exist', 'monitoring.log_all_requests' => true]);

        $this->get('/up')->assertOk();
    }

    public function test_the_query_counters_count_queries_of_the_current_request_only(): void
    {
        config(['monitoring.log_all_requests' => true]);
        $campaign = ReviewCampaign::factory()->create();

        $this->get(route('review.show', $campaign->token))->assertOk();
        $this->get('/up')->assertOk();

        [$show, $up] = $this->performanceEntries();
        $this->assertGreaterThanOrEqual(1, $show['context']['queries']);
        $this->assertSame(0, $up['context']['queries'] ?? 0, '/up runs no query: counters must not carry over from the previous request');
    }

    public function test_monitoring_adds_no_database_writes(): void
    {
        config(['monitoring.log_all_requests' => true]);
        $campaign = ReviewCampaign::factory()->create();
        DB::enableQueryLog();

        $this->get(route('review.show', $campaign->token))->assertOk();

        $writes = array_filter(DB::getQueryLog(), fn ($q) => preg_match('/^\s*(insert|update|delete)/i', $q['query']));
        $this->assertCount(0, array_filter($writes, fn ($q) => ! str_contains($q['query'], 'sessions')), 'only the session may be written');
    }
}
