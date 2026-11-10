<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringCommandsTest extends TestCase
{
    use RefreshDatabase;

    // ───────────────────────── monitoring:check ─────────────────────────

    public function test_check_reports_ok_for_a_safe_configuration_and_prints_no_secrets(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false]);

        $this->artisan('monitoring:check', ['--json' => true])->assertSuccessful();
        $json = json_decode($this->runCheckJson(), true);

        $this->assertTrue($json['ok']);
        $checks = collect($json['checks'])->keyBy('check');
        $this->assertSame('ok', $checks['APP_DEBUG']['status']);
        $this->assertSame('false', $checks['APP_DEBUG']['value']);

        $raw = json_encode($json);
        $this->assertStringNotContainsString((string) config('app.key'), $raw);
        $this->assertStringNotContainsString('password', strtolower($raw));
    }

    public function test_check_fails_when_debug_is_on_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        $this->artisan('monitoring:check')->assertFailed();
    }

    public function test_check_warns_about_wildcard_proxies_and_per_request_logging(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'trustedproxy.proxies' => '*', 'monitoring.log_all_requests' => true]);
        $checks = collect(json_decode($this->runCheckJson(), true)['checks'])->keyBy('check');

        $this->assertSame('WARN', $checks['trusted proxies']['status']);
        $this->assertSame('WARN', $checks['monitoring logs every request']['status']);
    }

    private function runCheckJson(): string
    {
        \Illuminate\Support\Facades\Artisan::call('monitoring:check', ['--json' => true]);

        return \Illuminate\Support\Facades\Artisan::output();
    }

    // ───────────────────────── loadtest:* guards ─────────────────────────

    public function test_load_test_helpers_refuse_production_and_non_throwaway_databases(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('loadtest:prepare')->assertFailed();
        $this->artisan('loadtest:verify', ['run' => 'abc123'])->assertFailed();
        $this->artisan('loadtest:cleanup', ['run' => 'abc123'])->assertFailed();
        $this->assertSame(0, ReviewCampaign::count());

        $this->app['env'] = 'staging';
        config(['database.connections.sqlite.database' => '/var/db/u155427795_reviews_prod.sqlite']);
        $this->artisan('loadtest:prepare')->assertFailed();
        $this->assertSame(0, ReviewCampaign::count());
    }

    /** @return array<string, array{string}> */
    public static function lookalikeDatabases(): array
    {
        return [
            'prod with a test suffix'     => ['/var/db/u1_reviews_prod_test.sqlite'],
            'contains "test" inside word' => ['/var/db/latest.sqlite'],
            'folder name is not enough'   => ['/var/www/releases/latest/reviews.sqlite'],
            'contest'                     => ['/var/db/contest.sqlite'],
            'table not a token'           => ['/var/db/u123_table.sqlite'],
            'collab'                      => ['/var/db/emc_collab.sqlite'],
            'production word'             => ['/var/db/reviews-live-lab.sqlite'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lookalikeDatabases')]
    public function test_the_guard_rejects_databases_that_only_look_throwaway(string $path): void
    {
        $this->app['env'] = 'staging';
        config(['database.connections.sqlite.database' => $path]);

        $this->artisan('loadtest:prepare')->assertFailed();
        $this->assertSame(0, ReviewCampaign::count());
    }

    public function test_the_guard_rejects_a_remote_database_unless_it_is_named_explicitly(): void
    {
        $this->app['env'] = 'staging';
        config([
            'database.default'                  => 'mysql',
            'database.connections.mysql.host'     => 'db.example.com',
            'database.connections.mysql.database' => 'emc_staging',
        ]);

        try {   // the default connection must be restored before RefreshDatabase tears down
            $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('loadtest:prepare'));
            $this->assertStringContainsString('not on this machine', \Illuminate\Support\Facades\Artisan::output());
        } finally {
            config(['database.default' => 'sqlite']);
            \Illuminate\Support\Facades\DB::purge('mysql');
        }
    }

    public function test_the_guard_checks_the_live_connection_not_only_the_config(): void
    {
        $this->app['env'] = 'staging';
        // config claims a throw-away name while the live connection (e.g. created from DB_URL) points elsewhere
        $live = \Illuminate\Support\Facades\DB::connection();
        $live->setDatabaseName('/var/db/u1_reviews_prod.sqlite');
        config(['database.connections.sqlite.database' => ':memory:']);

        try {
            $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('loadtest:prepare'));
        } finally {
            $live->setDatabaseName(':memory:');
        }
    }

    public function test_load_test_helpers_work_on_an_isolated_database(): void
    {
        $this->app['env'] = 'staging';
        config(['database.connections.sqlite.database' => ':memory:']);

        \Illuminate\Support\Facades\Artisan::call('loadtest:prepare', ['--run' => 'abc123', '--json' => true]);
        $prepared = json_decode(\Illuminate\Support\Facades\Artisan::output(), true);
        $campaign = ReviewCampaign::findOrFail($prepared['campaign_id']);
        $this->assertSame($prepared['token'], $campaign->token);
        $this->assertSame('LOADTEST abc123', $campaign->course_name);

        // pretend k6 saved 3 reviews, one more review belongs to somebody else
        foreach (['lt-abc123-1-1@loadtest.invalid', 'lt-abc123-2-1@loadtest.invalid', 'lt-abc123-3-1@loadtest.invalid'] as $email) {
            $review = CourseReview::factory()->create(['review_campaign_id' => $campaign->id, 'email' => $email]);
            AdminNotification::create(['type' => 'review', 'title' => 't', 'message' => 'm', 'data' => ['review_id' => $review->id]]);
        }
        // ~10% of synthetic students leave the e-mail empty: they are saved reviews too and must be counted
        foreach ([null, ''] as $i => $email) {
            $review = CourseReview::factory()->create(['review_campaign_id' => $campaign->id, 'email' => $email, 'student_name' => 'Load Test 9-' . $i]);
            AdminNotification::create(['type' => 'review', 'title' => 't', 'message' => 'm', 'data' => ['review_id' => $review->id]]);
        }
        $other = CourseReview::factory()->create(['review_campaign_id' => $campaign->id, 'email' => 'real.student@example.test']);
        $realNoEmail = CourseReview::factory()->create(['review_campaign_id' => $campaign->id, 'email' => null, 'student_name' => 'Real Tester']);   // a person who left the e-mail empty

        $this->artisan('loadtest:verify', ['run' => 'abc123', '--expected' => 5])->assertSuccessful();
        $this->artisan('loadtest:verify', ['run' => 'abc123', '--expected' => 3])->assertFailed();   // 5 saved, 3 reported: lost responses
        $this->artisan('loadtest:verify', ['run' => 'abc123', '--expected' => 6])->assertFailed();   // reported more than saved

        $this->artisan('loadtest:cleanup', ['run' => 'abc123'])->assertSuccessful();
        $this->assertSame(0, CourseReview::where('email', 'like', 'lt-abc123-%')->count());
        $this->assertSame(1, CourseReview::whereNull('email')->orWhere('email', '')->count(), 'only the real review without an e-mail is left');
        $this->assertSame(0, AdminNotification::count());
        $this->assertNotNull(CourseReview::find($other->id), 'cleanup must never touch real reviews');
        $this->assertNotNull(CourseReview::find($realNoEmail->id), 'a real review without an e-mail is not a load-test review either');
        $this->assertSame(1, ReviewCampaign::where('course_name', 'LOADTEST abc123')->count(), 'a campaign that still holds other reviews is kept');
    }

    public function test_the_run_label_is_validated(): void
    {
        $this->artisan('loadtest:prepare', ['--run' => '../etc'])->assertFailed();
        $this->artisan('loadtest:cleanup', ['run' => "x'; drop table"])->assertFailed();
    }
}
