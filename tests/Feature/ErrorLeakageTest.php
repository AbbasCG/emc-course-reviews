<?php

namespace Tests\Feature;

use App\Models\ReviewCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * With APP_DEBUG=false a visitor must never see server, code or database details, whatever fails.
 */
class ErrorLeakageTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN = [
        'RuntimeException', 'QueryException', 'PDOException', 'SQLSTATE', 'Stack trace', 'vendor/', 'vendor\\', 'app/Http', 'app\\Http',
        'D:\\', 'D:/', 'C:\\', '/home/', 'DB_PASSWORD', 'APP_KEY', 'APP_DEBUG', 'sqlite', 'mysql', 'MariaDB', 'Illuminate', 'PHP/',
        'SECRET-INTERNAL-HOST', 'nonexistent_table',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    private function assertNothingLeaks($response): void
    {
        $body = $response->getContent();
        foreach (self::FORBIDDEN as $needle) {
            $this->assertStringNotContainsString($needle, $body, "the response leaks: $needle");
        }
        $this->assertNull($response->headers->get('X-Powered-By'));
        $this->assertNull($response->headers->get('Server-Timing'));
    }

    public function test_an_exception_with_a_secret_message_does_not_reach_the_visitor(): void
    {
        Route::get('/__leak/exception', fn () => throw new \RuntimeException('connection to SECRET-INTERNAL-HOST failed'));

        $html = $this->get('/__leak/exception')->assertStatus(500);
        $json = $this->getJson('/__leak/exception')->assertStatus(500);

        $this->assertNothingLeaks($html);
        $this->assertNothingLeaks($json);
        $json->assertExactJson(['message' => 'Server Error']);
    }

    public function test_a_failing_sql_query_does_not_leak_the_query_or_the_driver(): void
    {
        Route::get('/__leak/sql', fn () => DB::select('select * from nonexistent_table'));

        $this->assertNothingLeaks($this->get('/__leak/sql')->assertStatus(500));
        $this->assertNothingLeaks($this->getJson('/__leak/sql')->assertStatus(500));
    }

    public function test_a_failing_query_is_logged_without_its_bindings(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        Route::get('/__leak/binding', fn () => DB::select('select * from nonexistent_table where email = ?', ['secret.student@example.test']));

        $this->get('/__leak/binding')->assertStatus(500);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->withArgs(function ($message, $context = []) {
            $logged = $message . json_encode($context);

            return str_contains($logged, 'nonexistent_table')                    // the SQL template is kept for diagnosis
                && ! str_contains($logged, 'secret.student@example.test');       // the student's e-mail never reaches the log
        })->atLeast()->once();
    }

    public function test_a_review_submission_that_fails_in_the_database_answers_with_a_generic_message(): void
    {
        $campaign = ReviewCampaign::factory()->create();
        \Illuminate\Support\Facades\Schema::drop('course_reviews');

        $response = $this->postJson(route('review.store', $campaign->token), [
            'student_name' => 'x', 'email' => 'a@example.test', 'overall_rating' => 5, 'course_content_rating' => 4, 'instructor_rating' => 5,
            'organization_rating' => 4, 'difficulty_level' => 'متوسط', 'what_did_you_like' => 'a', 'what_can_be_improved' => 'b', 'would_recommend' => true,
        ]);

        $this->assertNothingLeaks($response);
    }

    public function test_unknown_pages_and_methods_do_not_leak(): void
    {
        $this->assertNothingLeaks($this->get('/this/does/not/exist')->assertNotFound());
        $this->assertNothingLeaks($this->get(route('review.show', 'no-such-token'))->assertNotFound());
        $this->assertNothingLeaks($this->delete('/login')->assertStatus(405));
        $this->assertNothingLeaks($this->get('/admin/diagnostics/client-ip')->assertRedirect());
    }

    public function test_the_health_endpoint_reveals_nothing(): void
    {
        $body = $this->get('/up')->assertOk()->getContent();

        foreach (['version', 'php', 'laravel', 'database', 'mysql', 'env'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, strip_tags($body));
        }
    }

    public function test_the_shipped_defaults_are_safe_for_production(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        // .env.example is for developers; the monitoring:check command is what flags APP_DEBUG=true in production.
        $this->assertStringContainsString('APP_DEBUG=', $example);
        $this->assertSame([], config('trustedproxy.proxies'), 'nobody is trusted by default');
        $this->assertFalse((bool) config('monitoring.log_all_requests'), 'per-request logging must be off by default');
    }
}
