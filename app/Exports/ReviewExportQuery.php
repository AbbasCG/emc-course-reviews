<?php

namespace App\Exports;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the read-only review query for an export. Campaign-level filters
 * are applied through a sub-select so aggregates never need joins and each
 * review appears exactly once.
 */
class ReviewExportQuery
{
    public static function build(ExportCriteria $c): Builder
    {
        [$from, $to] = $c->range();

        $query = CourseReview::query();

        $campaigns = self::campaignQuery($c, $from, $to);
        if ($campaigns !== null) {
            $query->whereIn('review_campaign_id', $campaigns->select('id'));
        }

        if ($c->dateBasis === 'submitted') {
            if ($from) {
                $query->where('created_at', '>=', $from);
            }
            if ($to) {
                $query->where('created_at', '<=', $to);
            }
        }

        if ($c->ratings) {
            $query->whereIn('overall_rating', $c->ratings);
        }
        if ($c->difficulties) {
            $query->whereIn('difficulty_level', $c->difficulties);
        }
        if ($c->recommend === 'yes') {
            $query->where('would_recommend', true);
        } elseif ($c->recommend === 'no') {
            $query->where('would_recommend', false);
        }

        return $query;
    }

    /** Campaign sub-query, or null when no campaign-level filter applies. */
    public static function campaignQuery(ExportCriteria $c, $from = null, $to = null): ?Builder
    {
        $q       = ReviewCampaign::query();
        $applied = false;

        if ($c->campaignIds) {
            $q->whereIn('id', $c->campaignIds);
            $applied = true;
        }
        if ($c->activityTypes) {
            $q->whereIn('activity_type', $c->activityTypes);
            $applied = true;
        }
        if ($c->instructors) {
            $q->whereIn('instructor_name', $c->instructors);
            $applied = true;
        }
        if ($c->campaignStatus !== 'any') {
            $q->withStatus($c->campaignStatus);
            $applied = true;
        }
        if ($c->dateBasis === 'course' && ($from || $to)) {
            if ($from) {
                $q->whereDate('course_date', '>=', $from->toDateString());
            }
            if ($to) {
                $q->whereDate('course_date', '<=', $to->toDateString());
            }
            $applied = true;
        }

        return $applied ? $q : null;
    }
}
