<?php

namespace App\Exports;

use App\Models\ReviewCampaign;

/**
 * Aggregated statistics for an export, computed with SQL aggregates so it
 * stays cheap on large datasets.
 */
class ReportStatistics
{
    public static function compute(ExportCriteria $c): array
    {
        $totals = ReviewExportQuery::build($c)->toBase()->selectRaw('
            COUNT(*) as total,
            COUNT(DISTINCT review_campaign_id) as campaigns,
            AVG(overall_rating) as overall_avg,
            AVG(course_content_rating) as content_avg,
            AVG(instructor_rating) as instructor_avg,
            AVG(organization_rating) as organization_avg,
            SUM(CASE WHEN would_recommend = 1 THEN 1 ELSE 0 END) as recommend_yes,
            MIN(created_at) as first_at,
            MAX(created_at) as last_at
        ')->first();

        $total = (int) $totals->total;

        $stars = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach (ReviewExportQuery::build($c)->toBase()
                     ->selectRaw('overall_rating, COUNT(*) as c')
                     ->groupBy('overall_rating')->get() as $row) {
            if (isset($stars[(int) $row->overall_rating])) {
                $stars[(int) $row->overall_rating] = (int) $row->c;
            }
        }

        $difficulty = ReviewExportQuery::build($c)->toBase()
            ->selectRaw('difficulty_level, COUNT(*) as c')
            ->groupBy('difficulty_level')->orderByDesc('c')
            ->pluck('c', 'difficulty_level')->map(fn ($v) => (int) $v)->all();

        $recommendYes = (int) $totals->recommend_yes;

        return [
            'total'            => $total,
            'campaigns'        => (int) $totals->campaigns,
            'overall_avg'      => self::avg($totals->overall_avg),
            'content_avg'      => self::avg($totals->content_avg),
            'instructor_avg'   => self::avg($totals->instructor_avg),
            'organization_avg' => self::avg($totals->organization_avg),
            'recommend_yes'    => $recommendYes,
            'recommend_no'     => $total - $recommendYes,
            'recommend_pct'    => $total ? (int) round($recommendYes / $total * 100) : 0,
            'stars'            => $stars,
            'difficulty'       => $difficulty,
            'first_at'         => $totals->first_at,
            'last_at'          => $totals->last_at,
        ];
    }

    /** Per-campaign performance rows, best average first. */
    public static function campaigns(ExportCriteria $c): array
    {
        $rows = ReviewExportQuery::build($c)->toBase()->selectRaw('
                review_campaign_id,
                COUNT(*) as total,
                AVG(overall_rating) as overall_avg,
                AVG(course_content_rating) as content_avg,
                AVG(instructor_rating) as instructor_avg,
                AVG(organization_rating) as organization_avg,
                SUM(CASE WHEN would_recommend = 1 THEN 1 ELSE 0 END) as recommend_yes
            ')
            ->groupBy('review_campaign_id')
            ->get();

        $campaigns = ReviewCampaign::whereIn('id', $rows->pluck('review_campaign_id'))->get()->keyBy('id');

        return $rows->map(function ($r) use ($campaigns) {
            $campaign = $campaigns[$r->review_campaign_id] ?? null;
            $total    = (int) $r->total;

            return [
                'id'               => (int) $r->review_campaign_id,
                'course_name'      => $campaign?->course_name ?? '—',
                'instructor_name'  => $campaign?->instructor_name ?: '—',
                'activity_type'    => $campaign?->activityTypeLabel() ?? '—',
                'status'           => $campaign?->statusLabel() ?? '—',
                'course_date'      => $campaign?->course_date?->format('Y-m-d'),
                'total'            => $total,
                'overall_avg'      => self::avg($r->overall_avg),
                'content_avg'      => self::avg($r->content_avg),
                'instructor_avg'   => self::avg($r->instructor_avg),
                'organization_avg' => self::avg($r->organization_avg),
                'recommend_pct'    => $total ? (int) round($r->recommend_yes / $total * 100) : 0,
            ];
        })->sortByDesc('overall_avg')->values()->all();
    }

    private static function avg($value): float
    {
        return $value === null ? 0.0 : round((float) $value, 2);
    }
}
