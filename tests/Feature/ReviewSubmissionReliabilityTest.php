<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use App\Services\ReviewSubmitter;
use Illuminate\Database\QueryException;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR-1: a submitted review must never be lost or duplicated, and failures must
 * be answered with a meaningful status instead of a silent 500.
 * (Rate limiting is covered separately, so the throttle is disabled here.)
 */
class ReviewSubmissionReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private ReviewCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        // Throttle is covered separately; cookies are sent plain so a test can reuse one session id.
        $this->withoutMiddleware([ThrottleRequests::class, EncryptCookies::class]);
        $this->campaign = ReviewCampaign::factory()->create();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'student_name'          => 'سارة أحمد',
            'email'                 => 'sara@example.test',
            'overall_rating'        => 5,
            'course_content_rating' => 4,
            'instructor_rating'     => 5,
            'organization_rating'   => 4,
            'difficulty_level'      => 'متوسط',
            'what_did_you_like'     => 'الشرح العملي',
            'what_can_be_improved'  => 'وقت أطول',
            'would_recommend'       => true,
            'additional_notes'      => null,
        ], $override);
    }

    /** POST the review form as a given browser session (same id = same student device). */
    private function submit(array $override = [], ?string $sessionId = null, ?ReviewCampaign $campaign = null)
    {
        $sessionId ??= Str::random(40);

        return $this->withCredentials()->withUnencryptedCookie(config('session.cookie'), $sessionId)
            ->postJson(route('review.store', ($campaign ?? $this->campaign)->token), $this->payload($override));
    }

    private function failNextInsert(\Closure $exception, int $times = PHP_INT_MAX): void
    {
        $calls = 0;
        $this->app['events']->listen('eloquent.creating: ' . CourseReview::class, function () use (&$calls, $times, $exception) {
            if ($calls++ < $times) {
                throw $exception();
            }
        });
    }

    // ───────────────────────── Happy path ─────────────────────────

    public function test_successful_submission_stores_the_review_and_notifies_admins(): void
    {
        $this->submit()->assertOk()->assertJsonPath('redirect', route('review.success', $this->campaign->token));

        $this->assertDatabaseCount('course_reviews', 1);
        $this->assertDatabaseHas('course_reviews', ['email' => 'sara@example.test', 'review_campaign_id' => $this->campaign->id]);

        $notification = AdminNotification::firstOrFail();
        $this->assertSame('review', $notification->type);
        $this->assertSame($this->campaign->id, $notification->data['campaign_id']);
        $this->assertSame(CourseReview::firstOrFail()->id, $notification->data['review_id']);
    }

    // ───────────────────────── Review saved, notification failed ─────────────────────────

    public function test_review_is_kept_and_student_sees_success_when_notification_table_is_missing(): void
    {
        // Reproduces production: code deployed before the admin_notifications migration ran.
        Schema::drop('admin_notifications');
        Log::spy();

        $this->submit()->assertOk()->assertJsonStructure(['redirect']);

        $this->assertDatabaseCount('course_reviews', 1);
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Admin notification could not be created')
                && isset($context['review_id'], $context['campaign_id'])
                // no SQL bindings => no student name / answers in the log
                && !str_contains(json_encode($context, JSON_UNESCAPED_UNICODE), 'سارة');
        })->once();
    }

    public function test_retry_after_a_notification_failure_is_not_a_second_review(): void
    {
        Schema::drop('admin_notifications');

        $this->submit()->assertOk();
        $this->submit()->assertStatus(422)->assertJsonPath('errors.email.0', 'لقد قمت بتقديم تقييم من قبل باستخدام هذا البريد الإلكتروني');

        $this->assertDatabaseCount('course_reviews', 1);
    }

    // ───────────────────────── Duplicates ─────────────────────────

    public function test_second_submission_with_the_same_email_is_rejected(): void
    {
        $this->submit()->assertOk();
        $this->submit(['student_name' => 'اسم آخر'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'لقد قمت بتقديم تقييم من قبل باستخدام هذا البريد الإلكتروني');

        $this->assertDatabaseCount('course_reviews', 1);
        $this->assertDatabaseCount('admin_notifications', 1);
    }

    public function test_same_email_can_review_a_different_campaign(): void
    {
        $other = ReviewCampaign::factory()->create();

        $this->submit()->assertOk();
        $this->submit([], null, $other)->assertOk();

        $this->assertDatabaseCount('course_reviews', 2);
    }

    public function test_losing_the_duplicate_race_returns_422_instead_of_500(): void
    {
        // Another request stored the same email after our pre-check passed.
        CourseReview::factory()->for($this->campaign, 'campaign')->create(['email' => 'sara@example.test']);

        $this->app->bind(ReviewSubmitter::class, fn () => new class extends ReviewSubmitter {
            protected function emailAlreadyReviewed($campaign, string $email): bool
            {
                return false; // the check ran "before" the competing insert
            }
        });

        $this->submit()
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'لقد قمت بتقديم تقييم من قبل باستخدام هذا البريد الإلكتروني');

        $this->assertDatabaseCount('course_reviews', 1);
    }

    public function test_identical_anonymous_double_submit_is_stored_once(): void
    {
        $sid = Str::random(40);

        $first = $this->submit(['email' => null], $sid)->assertOk();
        $second = $this->submit(['email' => null], $sid)->assertOk();

        $this->assertSame($first->json('redirect'), $second->json('redirect'));
        $this->assertDatabaseCount('course_reviews', 1);
        $this->assertDatabaseCount('admin_notifications', 1);
    }

    public function test_browser_style_form_encoded_double_submit_is_stored_once(): void
    {
        // The real form posts FormData (all values are strings), not JSON.
        $form = ['student_name' => 'سارة', 'overall_rating' => '5', 'course_content_rating' => '4', 'instructor_rating' => '5',
                 'organization_rating' => '4', 'difficulty_level' => 'متوسط', 'what_did_you_like' => 'الشرح',
                 'what_can_be_improved' => 'الوقت', 'would_recommend' => '1'];
        $sid = Str::random(40);
        $url = route('review.store', $this->campaign->token);

        $this->withUnencryptedCookie(config('session.cookie'), $sid)->post($url, $form, ['Accept' => 'application/json'])->assertOk();
        $this->withUnencryptedCookie(config('session.cookie'), $sid)->post($url, $form, ['Accept' => 'application/json'])->assertOk();

        $this->assertDatabaseCount('course_reviews', 1);
    }

    public function test_anonymous_reviews_with_different_answers_or_sessions_are_all_kept(): void
    {
        $sid = Str::random(40);

        $this->submit(['email' => null], $sid)->assertOk();
        $this->submit(['email' => null, 'overall_rating' => 3], $sid)->assertOk();   // other answers, same device
        $this->submit(['email' => null])->assertOk();                                 // same answers, other device

        $this->assertDatabaseCount('course_reviews', 3);
    }

    public function test_anonymous_submission_still_being_processed_gets_409_and_stores_nothing(): void
    {
        $sid = Str::random(40);
        Cache::put(ReviewSubmitter::dedupeKey($this->campaign, $this->payload(['email' => null]), $sid), 'pending', 60);

        $this->submit(['email' => null], $sid)->assertStatus(409)->assertJsonStructure(['message']);

        $this->assertDatabaseCount('course_reviews', 0);
    }

    public function test_submissions_with_an_email_do_not_use_the_dedupe_cache(): void
    {
        $sid = Str::random(40);
        $this->submit([], $sid)->assertOk();

        $this->assertFalse(Cache::has(ReviewSubmitter::dedupeKey($this->campaign, $this->payload(), $sid)));
    }

    // ───────────────────────── Failures ─────────────────────────

    public function test_unexpected_database_error_returns_500_is_logged_without_personal_data_and_can_be_retried(): void
    {
        Log::spy();
        $this->failNextInsert(fn () => new QueryException(
            'sqlite',
            'insert into "course_reviews" (...) values (?)',
            ['سارة أحمد', 'sara@example.test'],
            new \PDOException('disk I/O error')
        ), 1);

        $sid = Str::random(40);
        $this->submit(['email' => null], $sid)->assertStatus(500)->assertJsonPath('message', 'حدث خطأ في تقديم التقييم. يرجى المحاولة مرة أخرى.');

        $this->assertDatabaseCount('course_reviews', 0);
        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) {
            $flat = json_encode($context, JSON_UNESCAPED_UNICODE);

            return $context['campaign_id'] === $this->campaign->id
                && $context['exception'] === QueryException::class
                && !str_contains($flat, 'سارة')
                && !str_contains($flat, 'sara@example.test');
        })->once();

        // The failed attempt must not block the student's retry (dedupe claim released).
        $this->submit(['email' => null], $sid)->assertOk();
        $this->assertDatabaseCount('course_reviews', 1);
    }

    public function test_cache_outage_does_not_block_anonymous_submissions(): void
    {
        Log::spy();
        Cache::partialMock()->shouldReceive('add')->andThrow(new \RuntimeException('cache table missing'));

        $this->submit(['email' => null])->assertOk();

        $this->assertDatabaseCount('course_reviews', 1);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, 'dedupe cache unavailable'))->once();
    }

    // ───────────────────────── Unchanged behaviour ─────────────────────────

    public function test_inactive_and_expired_campaigns_still_refuse_reviews(): void
    {
        $inactive = ReviewCampaign::factory()->inactive()->create();
        $expired = ReviewCampaign::factory()->expired()->create();

        $this->submit([], null, $inactive)->assertStatus(422)->assertJsonPath('errors.general.0', 'حملة التقييم غير نشطة');
        $this->submit([], null, $expired)->assertStatus(422)->assertJsonPath('errors.general.0', 'انتهت صلاحية هذه الحملة');

        $this->assertDatabaseCount('course_reviews', 0);
    }

    public function test_invalid_input_is_rejected_with_validation_errors(): void
    {
        $this->submit(['overall_rating' => 9, 'student_name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['overall_rating', 'student_name']);

        $this->assertDatabaseCount('course_reviews', 0);
    }

    public function test_form_and_success_pages_still_render(): void
    {
        $this->get(route('review.show', $this->campaign->token))->assertOk();
        $this->get(route('review.success', $this->campaign->token))->assertOk();
        $this->get(route('review.show', 'unknown-token'))->assertNotFound();
    }
}
