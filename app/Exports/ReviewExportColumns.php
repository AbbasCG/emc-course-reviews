<?php

namespace App\Exports;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;

/**
 * Registry of exportable review columns. Only fields that really exist in
 * course_reviews / review_campaigns are listed here.
 */
class ReviewExportColumns
{
    public const GROUPS = [
        'student'  => 'بيانات الطالب',
        'activity' => 'بيانات النشاط',
        'review'   => 'بيانات التقييم',
    ];

    /**
     * key => [label, group, pii, type, xlsx width]
     * type: text | int | date | datetime
     */
    public const COLUMNS = [
        'student_name'          => ['الاسم الكامل', 'student', true, 'text', 24],
        'email'                 => ['البريد الإلكتروني', 'student', true, 'text', 28],

        'course_name'           => ['اسم الدورة / النشاط', 'activity', false, 'text', 30],
        'activity_type'         => ['نوع النشاط', 'activity', false, 'text', 14],
        'instructor_name'       => ['اسم المدرب', 'activity', false, 'text', 20],
        'course_date'           => ['تاريخ الدورة', 'activity', false, 'date', 13],
        'campaign_id'           => ['رقم الحملة', 'activity', false, 'int', 10],
        'campaign_status'       => ['حالة الحملة', 'activity', false, 'text', 12],

        'review_id'             => ['رقم التقييم', 'review', false, 'int', 10],
        'overall_rating'        => ['التقييم العام', 'review', false, 'int', 12],
        'course_content_rating' => ['تقييم المحتوى', 'review', false, 'int', 12],
        'instructor_rating'     => ['تقييم المدرب', 'review', false, 'int', 12],
        'organization_rating'   => ['تقييم التنظيم', 'review', false, 'int', 12],
        'difficulty_level'      => ['مستوى الصعوبة', 'review', false, 'text', 12],
        'would_recommend'       => ['التوصية', 'review', false, 'text', 10],
        'what_did_you_like'     => ['ما أعجب الطالب', 'review', false, 'text', 45],
        'what_can_be_improved'  => ['اقتراحات التحسين', 'review', false, 'text', 45],
        'additional_notes'      => ['الملاحظات الإضافية', 'review', false, 'text', 40],
        'submitted_at'          => ['تاريخ ووقت التقييم', 'review', false, 'datetime', 18],
    ];

    /** Column set used by the per-campaign CSV button (matches the legacy export). */
    public const LEGACY_CAMPAIGN_CSV = [
        'student_name', 'email', 'overall_rating', 'course_content_rating', 'instructor_rating',
        'organization_rating', 'difficulty_level', 'would_recommend', 'what_did_you_like',
        'what_can_be_improved', 'additional_notes', 'submitted_at',
    ];

    public static function keys(): array
    {
        return array_keys(self::COLUMNS);
    }

    public static function piiKeys(): array
    {
        return array_keys(array_filter(self::COLUMNS, fn ($c) => $c[2]));
    }

    public static function label(string $key): string
    {
        return self::COLUMNS[$key][0];
    }

    public static function type(string $key): string
    {
        return self::COLUMNS[$key][3];
    }

    public static function width(string $key): int
    {
        return self::COLUMNS[$key][4];
    }

    /** Keep registry order and drop unknown keys. */
    public static function normalize(array $keys): array
    {
        return array_values(array_intersect(self::keys(), $keys));
    }

    public static function forUi(): array
    {
        $out = [];
        foreach (self::COLUMNS as $key => [$label, $group, $pii]) {
            $out[] = ['key' => $key, 'label' => $label, 'group' => $group, 'pii' => $pii];
        }

        return $out;
    }

    /** Raw (typed) value of a column for one review. */
    public static function value(CourseReview $review, string $key): int|string|null
    {
        /** @var ReviewCampaign|null $campaign */
        $campaign = $review->campaign;

        return match ($key) {
            'student_name'          => $review->student_name,
            'email'                 => $review->email,
            'course_name'           => $campaign?->course_name,
            'activity_type'         => $campaign?->activityTypeLabel(),
            'instructor_name'       => $campaign?->instructor_name,
            'course_date'           => $campaign?->course_date?->format('Y-m-d'),
            'campaign_id'           => $campaign?->id,
            'campaign_status'       => $campaign?->statusLabel(),
            'review_id'             => $review->id,
            'overall_rating'        => (int) $review->overall_rating,
            'course_content_rating' => (int) $review->course_content_rating,
            'instructor_rating'     => (int) $review->instructor_rating,
            'organization_rating'   => (int) $review->organization_rating,
            'difficulty_level'      => $review->difficulty_level,
            'would_recommend'       => $review->would_recommend ? 'نعم' : 'لا',
            'what_did_you_like'     => $review->what_did_you_like,
            'what_can_be_improved'  => $review->what_can_be_improved,
            'additional_notes'      => $review->additional_notes,
            'submitted_at'          => $review->created_at?->format('Y-m-d H:i'),
        };
    }

    public static function row(CourseReview $review, array $keys): array
    {
        return array_map(fn ($k) => self::value($review, $k), $keys);
    }
}
