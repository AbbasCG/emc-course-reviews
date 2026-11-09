<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stores a student review reliably.
 *
 * - The review is the authoritative record. It is written in a single
 *   transaction that is retried on deadlocks / lock-wait timeouts.
 * - The admin notification is best-effort: if it fails (for example the
 *   admin_notifications table is missing because a migration has not run yet)
 *   the review stays saved and the student still sees a success page.
 * - Duplicates: reviews with an email are protected by the unique index
 *   (campaign + email); a lost race is reported as a normal "already
 *   submitted" answer instead of a 500. Reviews WITHOUT an email have no
 *   unique key, so an identical anonymous submission from the same session is
 *   treated as a retry (double click, lost response) and stored once.
 */
class ReviewSubmitter
{
    public const CREATED = 'created';
    public const REPLAYED = 'replayed';
    public const IN_FLIGHT = 'in_flight';
    public const DUPLICATE_EMAIL = 'duplicate_email';
    public const FAILED = 'failed';

    /** Seconds a finished anonymous submission is remembered for retry detection. */
    public const DEDUPE_TTL = 120;

    /**
     * Seconds an in-progress claim lives. Short on purpose: if the process dies
     * between claiming and saving, the student must be able to retry quickly.
     */
    public const PENDING_TTL = 30;

    /** Attempts for the review insert when MySQL reports a deadlock / lock wait. */
    public const TRANSACTION_ATTEMPTS = 3;

    public function submit(ReviewCampaign $campaign, array $data, string $sessionId): string
    {
        $hasEmail = !empty($data['email']);

        if ($hasEmail && $this->emailAlreadyReviewed($campaign, $data['email'])) {
            return self::DUPLICATE_EMAIL;
        }

        $claimKey = null;
        if (!$hasEmail) {
            [$early, $claimKey] = $this->claimAnonymous($campaign, $data, $sessionId);
            if ($early !== null) {
                return $early;
            }
        }

        try {
            $review = DB::transaction(
                fn () => $campaign->reviews()->create($data),
                self::TRANSACTION_ATTEMPTS
            );
        } catch (UniqueConstraintViolationException $e) {
            $this->release($claimKey);

            if ($hasEmail) {
                // Another request stored this email between our check and insert.
                return self::DUPLICATE_EMAIL;
            }

            $this->logFailure('Review insert violated a unique key', $campaign, $e);

            return self::FAILED;
        } catch (Throwable $e) {
            // Known limitation: if COMMIT itself fails ambiguously (timeout / dropped
            // connection after the server committed), a retry of an anonymous review
            // can store a second row. Emailed reviews stay protected by the unique key.
            $this->release($claimKey);
            $this->logFailure('Review could not be saved', $campaign, $e);

            return self::FAILED;
        }

        $this->remember($claimKey);
        $this->notifyAdmins($campaign, $review, $data);

        return self::CREATED;
    }

    /** Cache key used to recognise a repeated anonymous submission. */
    public static function dedupeKey(ReviewCampaign $campaign, array $data, string $sessionId): string
    {
        return 'review-submit:' . sha1($sessionId . '|' . $campaign->id . '|' . json_encode(Arr::sortRecursive($data)));
    }

    protected function emailAlreadyReviewed(ReviewCampaign $campaign, string $email): bool
    {
        return CourseReview::where('review_campaign_id', $campaign->id)
            ->where('email', $email)
            ->exists();
    }

    /**
     * @return array{0: ?string, 1: ?string} [early outcome or null, claimed key or null]
     */
    private function claimAnonymous(ReviewCampaign $campaign, array $data, string $sessionId): array
    {
        try {
            $key = self::dedupeKey($campaign, $data, $sessionId);

            if (Cache::add($key, 'pending', self::PENDING_TTL)) {
                return [null, $key];
            }

            return [Cache::get($key) === 'done' ? self::REPLAYED : self::IN_FLIGHT, null];
        } catch (Throwable $e) {
            // Fail open: never block a student because the cache store is unavailable.
            Log::warning('Review dedupe cache unavailable; continuing without it.', $this->safeContext($e, $campaign));

            return [null, null];
        }
    }

    private function remember(?string $key): void
    {
        if ($key === null) {
            return;
        }

        try {
            Cache::put($key, 'done', self::DEDUPE_TTL);
        } catch (Throwable $e) {
            Log::warning('Review dedupe cache could not be updated.', ['error' => class_basename($e)]);
        }
    }

    private function release(?string $key): void
    {
        if ($key === null) {
            return;
        }

        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            Log::warning('Review dedupe cache could not be released.', ['error' => class_basename($e)]);
        }
    }

    private function notifyAdmins(ReviewCampaign $campaign, CourseReview $review, array $data): void
    {
        try {
            AdminNotification::create([
                'type'    => 'review',
                'title'   => 'تم استلام تقييم جديد',
                'message' => 'تم استلام تقييم جديد لدورة: ' . $campaign->course_name,
                'data'    => [
                    'campaign_id'  => $campaign->id,
                    'review_id'    => $review->id,
                    'student_name' => $data['student_name'] ?? null,
                    'rating'       => $data['overall_rating'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning(
                'Admin notification could not be created; the review was saved.',
                $this->safeContext($e, $campaign) + ['review_id' => $review->id]
            );
        }
    }

    private function logFailure(string $message, ReviewCampaign $campaign, Throwable $e): void
    {
        Log::error($message, $this->safeContext($e, $campaign));
    }

    /**
     * Log context without SQL bindings: a QueryException message embeds the
     * student's name, email and free-text answers.
     */
    private function safeContext(Throwable $e, ReviewCampaign $campaign): array
    {
        $context = ['campaign_id' => $campaign->id, 'exception' => get_class($e), 'at' => basename($e->getFile()) . ':' . $e->getLine()];

        if ($e instanceof QueryException) {
            $context['sqlstate'] = $e->errorInfo[0] ?? $e->getCode();
            $context['driver_code'] = $e->errorInfo[1] ?? null;
        } else {
            $context['message'] = $e->getMessage();
        }

        return $context;
    }
}
