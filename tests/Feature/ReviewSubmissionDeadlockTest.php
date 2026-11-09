<?php

namespace Tests\Feature;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Laravel only retries a deadlocked transaction when it is the OUTERMOST one,
 * so these tests use DatabaseMigrations (no wrapping test transaction) to
 * behave like production.
 */
class ReviewSubmissionDeadlockTest extends TestCase
{
    use DatabaseMigrations;

    private ReviewCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->campaign = ReviewCampaign::factory()->create();
    }

    private function failInserts(int $times): void
    {
        $calls = 0;
        $this->app['events']->listen('eloquent.creating: ' . CourseReview::class, function () use (&$calls, $times) {
            if ($calls++ < $times) {
                throw new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction', 40001);
            }
        });
    }

    private function submit()
    {
        return $this->withCookie(config('session.cookie'), Str::random(40))->postJson(route('review.store', $this->campaign->token), [
            'student_name' => 'سارة أحمد', 'email' => 'sara@example.test',
            'overall_rating' => 5, 'course_content_rating' => 4, 'instructor_rating' => 5, 'organization_rating' => 4,
            'difficulty_level' => 'متوسط', 'what_did_you_like' => 'الشرح', 'what_can_be_improved' => 'الوقت', 'would_recommend' => true,
        ]);
    }

    public function test_deadlock_on_insert_is_retried_and_review_is_saved_once(): void
    {
        $this->failInserts(2); // attempts 1 and 2 deadlock, attempt 3 succeeds

        $this->submit()->assertOk()->assertJsonStructure(['redirect']);

        $this->assertDatabaseCount('course_reviews', 1);
        $this->assertDatabaseCount('admin_notifications', 1);
    }

    public function test_persistent_deadlock_gives_up_with_500_and_stores_nothing(): void
    {
        Log::spy();
        $this->failInserts(PHP_INT_MAX);

        $this->submit()->assertStatus(500)->assertJsonStructure(['message']);

        $this->assertDatabaseCount('course_reviews', 0);
        $this->assertDatabaseCount('admin_notifications', 0);
        Log::shouldHaveReceived('error')->once();
    }
}
