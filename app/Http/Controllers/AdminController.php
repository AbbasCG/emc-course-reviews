<?php

namespace App\Http\Controllers;

use App\Exports\ExportCriteria;
use App\Exports\ExportService;
use App\Exports\ReviewExportColumns;
use App\Http\Requests\StoreReviewCampaignRequest;
use App\Models\ExportLog;
use App\Models\ReviewCampaign;
use App\Models\CourseReview;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /** Dashboard campaign table page size (the list used to be unbounded). */
    private const CAMPAIGNS_PER_PAGE = 25;

    public function dashboard(Request $request)
    {
        $now = now();

        // ── Campaign counters: one pass instead of five queries ──
        $campaignStats = ReviewCampaign::query()->toBase()->selectRaw(
            'COUNT(*) AS total,
             SUM(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > ?) THEN 1 ELSE 0 END) AS active,
             SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
             SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < ? THEN 1 ELSE 0 END) AS expired,
             SUM(CASE WHEN is_active = 1 AND expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN 1 ELSE 0 END) AS expiring_soon',
            [$now, $now, $now, $now->copy()->addDays(7)]
        )->first();

        $totalCampaigns    = (int) $campaignStats->total;
        $activeCampaigns   = (int) $campaignStats->active;
        $inactiveCampaigns = (int) $campaignStats->inactive;
        $expiredCampaigns  = (int) $campaignStats->expired;
        $expiringSoonCount = (int) $campaignStats->expiring_soon;

        // ── Review totals, recommendation split and star breakdown: one pass instead of ten queries ──
        $reviewStats = CourseReview::query()->toBase()->selectRaw(
            'COUNT(*) AS total,
             AVG(overall_rating) AS avg_rating,
             SUM(CASE WHEN would_recommend = 1 THEN 1 ELSE 0 END) AS recommend_yes,
             SUM(CASE WHEN would_recommend = 0 THEN 1 ELSE 0 END) AS recommend_no,
             SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) AS star_5,
             SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) AS star_4,
             SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) AS star_3,
             SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) AS star_2,
             SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) AS star_1'
        )->first();

        $totalReviews  = (int) $reviewStats->total;
        $averageRating = round((float) ($reviewStats->avg_rating ?? 0), 2);
        $recommendYes  = (int) $reviewStats->recommend_yes;
        $recommendNo   = (int) $reviewStats->recommend_no;
        $globalRecommendPct = $totalReviews > 0 ? round($recommendYes / $totalReviews * 100) : 0;

        // ── Rating breakdown (5 → 1) ──
        $ratingBreakdown = collect([5, 4, 3, 2, 1])->mapWithKeys(
            fn ($s) => [$s => (int) $reviewStats->{'star_' . $s}]
        );

        $difficultyRaw = CourseReview::selectRaw('difficulty_level, COUNT(*) as count')
            ->groupBy('difficulty_level')
            ->pluck('count', 'difficulty_level');

        $latestReviews = CourseReview::latest()->take(5)->with('campaign')->get();

        // ── Campaign list (paginated; counts/averages are computed for the visible page only) ──
        $query = ReviewCampaign::withCount('reviews')
                               ->withAvg('reviews', 'overall_rating')
                               ->latest()
                               ->orderBy('id'); // same-second ties keep the order they always had (oldest id first)

        if ($request->filled('course_name')) {
            $query->where('course_name', 'like', '%' . $request->course_name . '%');
        }
        if ($request->filled('instructor_name')) {
            $query->where('instructor_name', 'like', '%' . $request->instructor_name . '%');
        }
        $filterStatus = $request->input('status', 'all');
        if ($filterStatus === 'active') {
            $query->where('is_active', 1)->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });
        } elseif ($filterStatus === 'inactive') {
            $query->where('is_active', 0);
        } elseif ($filterStatus === 'expired') {
            $query->where('expires_at', '<', $now);
        }
        $this->applyCreatedAtRange($query, $request);

        $campaigns = $query->paginate(self::CAMPAIGNS_PER_PAGE)->withQueryString();

        // A stale link / bookmark past the last page must not show the "no campaigns yet" empty state.
        if ($campaigns->isEmpty() && $campaigns->currentPage() > 1) {
            return redirect()->to($campaigns->url($campaigns->lastPage()));
        }
        $filters   = $request->only(['course_name', 'instructor_name', 'status', 'date_from', 'date_to']);

        // ── Analytics: monthly reviews (last 6 months) ──
        $arabicMonths = ['01'=>'يناير','02'=>'فبراير','03'=>'مارس','04'=>'أبريل','05'=>'مايو','06'=>'يونيو',
                         '07'=>'يوليو','08'=>'أغسطس','09'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'];
        $monthlyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i)->format('Y-m');
            $monthlyData[$month] = 0;
        }
        // Grouped in SQL (was: every review row of the period hydrated as a model and grouped in PHP).
        $monthExpr = match (DB::connection()->getDriverName()) {   // constant literals only, never user input
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",        // mysql / mariadb
        };
        $rawMonthly = CourseReview::query()->toBase()
            ->where('created_at', '>=', $now->copy()->subMonths(6)->startOfMonth())
            ->selectRaw("$monthExpr AS ym, COUNT(*) AS review_count")
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('review_count', 'ym')
            ->map(fn ($count) => (int) $count);
        $monthlyData   = $monthlyData->merge($rawMonthly);
        $monthlyLabels = $monthlyData->keys()->map(function ($m) use ($arabicMonths) {
            [$year, $month] = explode('-', $m);
            return ($arabicMonths[$month] ?? $month) . ' ' . $year;
        })->values();
        $monthlyValues = $monthlyData->values();

        // ── Quick insights: one grouped query instead of three correlated-subquery scans ──
        // Ties are resolved by the lowest campaign id so the result is deterministic.
        $perCampaign = CourseReview::query()->toBase()
            ->selectRaw('review_campaign_id, COUNT(*) AS review_count, AVG(overall_rating) AS avg_rating')
            ->groupBy('review_campaign_id')
            ->get()
            ->map(fn ($row) => (object) [
                'id'    => (int) $row->review_campaign_id,
                'count' => (int) $row->review_count,
                'avg'   => (float) $row->avg_rating,
            ]);
        $byRating = [['avg', 'desc'], ['id', 'asc']];
        $topRow    = $perCampaign->sortBy($byRating)->first();
        $lowestRow = $perCampaign->sortBy([['avg', 'asc'], ['id', 'asc']])->first();
        $mostRow   = $perCampaign->sortBy([['count', 'desc'], ['id', 'asc']])->first();

        $insightModels = ReviewCampaign::whereIn('id', array_filter(array_unique([$topRow?->id, $lowestRow?->id, $mostRow?->id])))->get()->keyBy('id');
        $insight = function (?object $row, bool $withAverage) use ($insightModels) {
            if ($row === null) {
                return null;
            }
            $model = clone $insightModels[$row->id];
            $model->setAttribute('reviews_count', $row->count);
            if ($withAverage) {
                $model->setAttribute('reviews_avg_overall_rating', $row->avg);
            }

            return $model;
        };
        $topRatedCampaign     = $insight($topRow, true);
        $lowestRatedCampaign  = $insight($lowestRow, true);
        $mostReviewedCampaign = $insight($mostRow, false);

        // ── Recent activity ──
        $latestCampaign = ReviewCampaign::latest()->orderBy('id')->first();

        return view('admin.dashboard', compact(
            'totalCampaigns', 'totalReviews', 'averageRating',
            'latestReviews', 'campaigns', 'filters',
            'monthlyLabels', 'monthlyValues', 'recommendYes', 'recommendNo', 'difficultyRaw',
            'activeCampaigns', 'inactiveCampaigns', 'expiredCampaigns', 'expiringSoonCount',
            'topRatedCampaign', 'lowestRatedCampaign', 'mostReviewedCampaign', 'globalRecommendPct',
            'ratingBreakdown', 'latestCampaign'
        ));
    }

    public function createCampaign()
    {
        return view('admin.campaigns.create');
    }

    public function storeCampaign(StoreReviewCampaignRequest $request)
    {
        $campaign = ReviewCampaign::create([
            'course_name'     => $request->course_name,
            'instructor_name' => $request->instructor_name,
            'activity_type'   => $this->resolveActivityType($request),
            'course_date'     => $request->course_date,
            'description'     => $request->description,
            'theme'           => $request->input('theme', 'default'),
            'expires_at'      => $this->computeExpiresAt($request),
            'token'           => Str::random(32),
        ]);

        return redirect()->route('admin.campaigns.show', $campaign)
                         ->with('success', 'تم إنشاء الحملة بنجاح');
    }

    public function showCampaign(ReviewCampaign $campaign, Request $request)
    {
        // One aggregate query instead of six.
        $stats = $campaign->reviews()->toBase()->selectRaw(
            'COUNT(*) AS total,
             AVG(overall_rating) AS avg_overall,
             AVG(course_content_rating) AS avg_content,
             AVG(instructor_rating) AS avg_instructor,
             AVG(organization_rating) AS avg_organization,
             SUM(CASE WHEN would_recommend = 1 THEN 1 ELSE 0 END) AS recommend_yes'
        )->first();

        $reviewCount               = (int) $stats->total;
        $averageRating             = round((float) ($stats->avg_overall ?? 0), 2);
        $averageContentRating      = round((float) ($stats->avg_content ?? 0), 2);
        $averageInstructorRating   = round((float) ($stats->avg_instructor ?? 0), 2);
        $averageOrganizationRating = round((float) ($stats->avg_organization ?? 0), 2);
        $recommendPct              = $reviewCount > 0 ? round((int) $stats->recommend_yes / $reviewCount * 100) : 0;

        $reviews       = $this->filteredReviews($campaign, $request)->paginate(10)->withQueryString();
        $reviewFilters = $request->only(['student_name', 'email', 'rating', 'recommend', 'difficulty', 'date_from', 'date_to']);

        return view('admin.campaigns.show', compact(
            'campaign', 'reviewCount', 'reviews',
            'averageRating', 'averageContentRating',
            'averageInstructorRating', 'averageOrganizationRating',
            'recommendPct', 'reviewFilters'
        ));
    }

    public function editCampaign(ReviewCampaign $campaign)
    {
        return view('admin.campaigns.edit', compact('campaign'));
    }

    public function updateCampaign(StoreReviewCampaignRequest $request, ReviewCampaign $campaign)
    {
        $campaign->update([
            'course_name'     => $request->course_name,
            'instructor_name' => $request->instructor_name,
            'activity_type'   => $this->resolveActivityType($request, $campaign),
            'course_date'     => $request->course_date,
            'description'     => $request->description,
            'theme'           => $request->input('theme', $campaign->theme),
            'expires_at'      => $this->computeExpiresAt($request, $campaign),
        ]);

        return redirect()->route('admin.campaigns.show', $campaign)
                         ->with('success', 'تم تحديث الحملة بنجاح');
    }

    public function destroyCampaign(ReviewCampaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('admin.dashboard')
                         ->with('success', 'تم حذف الحملة بنجاح');
    }

    public function toggleCampaign(ReviewCampaign $campaign)
    {
        $campaign->update(['is_active' => !$campaign->is_active]);
        $message = $campaign->is_active ? 'تم تفعيل الحملة بنجاح' : 'تم إيقاف الحملة بنجاح';

        return back()->with('success', $message);
    }

    public function showCampaignReviews(ReviewCampaign $campaign, Request $request)
    {
        $reviews       = $this->filteredReviews($campaign, $request)->paginate(15)->withQueryString();
        $reviewFilters = $request->only(['student_name', 'email', 'rating', 'recommend', 'difficulty', 'date_from', 'date_to']);

        return view('admin.campaigns.reviews', compact('campaign', 'reviews', 'reviewFilters'));
    }

    /** Filtered review list of a campaign, newest first (id breaks ties so pages never overlap). */
    private function filteredReviews(ReviewCampaign $campaign, Request $request)
    {
        $reviewQuery = $campaign->reviews()->latest()->orderByDesc('id');
        if ($request->filled('student_name')) {
            $reviewQuery->where('student_name', 'like', '%' . $request->student_name . '%');
        }
        if ($request->filled('email')) {
            $reviewQuery->where('email', 'like', '%' . $request->email . '%');
        }
        if ($request->filled('rating')) {
            $reviewQuery->where('overall_rating', (int) $request->rating);
        }
        if ($request->filled('recommend')) {
            $reviewQuery->where('would_recommend', $request->recommend === 'yes' ? 1 : 0);
        }
        if ($request->filled('difficulty')) {
            $reviewQuery->where('difficulty_level', $request->difficulty);
        }
        $this->applyCreatedAtRange($reviewQuery, $request);

        return $reviewQuery;
    }

    /**
     * date_from / date_to filters on created_at as plain range comparisons so the created_at index can be
     * used (whereDate() wraps the column in DATE(), which cannot). Same result: the end date is inclusive.
     * A value that is not a real Y-m-d date keeps the legacy whereDate() behaviour instead of failing.
     */
    private function applyCreatedAtRange($query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $from = $this->parseDay($request->date_from);
            $from
                ? $query->where('created_at', '>=', $from)
                : $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $to = $this->parseDay($request->date_to);
            $to
                ? $query->where('created_at', '<', $to->copy()->addDay())
                : $query->whereDate('created_at', '<=', $request->date_to);
        }
    }

    /** Start of the given Y-m-d day, or null when the value is not a valid date in that format. */
    private function parseDay(mixed $value): ?Carbon
    {
        // Years outside a sane window (e.g. 9999-12-31, whose "next day" is not a valid datetime) keep the
        // legacy whereDate() path, which treats them as plain far-past / far-future dates.
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || (int) substr($value, 0, 4) < 1900 || (int) substr($value, 0, 4) > 2100) {
            return null;
        }

        $day = Carbon::createFromFormat('!Y-m-d', $value);

        return $day && $day->format('Y-m-d') === $value ? $day : null;
    }

    public function exportCsv(ReviewCampaign $campaign, ExportService $exports)
    {
        return $this->campaignExport($campaign, $exports, 'detailed', 'csv', 'تقييمات ' . $campaign->course_name);
    }

    public function exportPdf(ReviewCampaign $campaign, ExportService $exports)
    {
        return $this->campaignExport($campaign, $exports, 'campaign', 'pdf', 'تقرير تقييمات ' . $campaign->course_name);
    }

    /**
     * Per-campaign quick exports run through the shared export engine so they
     * get the same Arabic PDF rendering, formula-injection protection and
     * export history as the Export Wizard.
     */
    private function campaignExport(ReviewCampaign $campaign, ExportService $exports, string $type, string $format, string $name)
    {
        Gate::authorize('export-reviews');

        $columns = ReviewExportColumns::LEGACY_CAMPAIGN_CSV;
        if (Gate::denies('export-personal-data')) {
            $columns = array_diff($columns, ReviewExportColumns::piiKeys());
        }

        $log = $exports->run($exports->createLog(request()->user(), ExportCriteria::fromArray([
            'report_type'    => $type,
            'format'         => $format,
            'campaign_scope' => 'selected',
            'campaign_ids'   => [$campaign->id],
            'columns'        => $columns,
            'name'           => $name,
        ])));

        if ($log->status !== ExportLog::STATUS_COMPLETED) {
            return back()->with('error', 'فشل التصدير: ' . $log->error);
        }

        return Storage::disk(config('exports.disk'))->download($log->file_path, $log->downloadName(), [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function resolveActivityType(StoreReviewCampaignRequest $request, ?ReviewCampaign $existing = null): string
    {
        $type = $request->input('activity_type');
        if ($type === 'other') {
            return trim((string) $request->input('activity_type_custom')) ?: ($existing?->activity_type ?? 'course');
        }

        return $type ?: ($existing?->activity_type ?? 'course');
    }

    private function computeExpiresAt(StoreReviewCampaignRequest $request, ?ReviewCampaign $existing = null): ?Carbon
    {
        $type = $request->input('expires_type', 'none');

        return match ($type) {
            '7'      => now()->addDays(7),
            '30'     => now()->addDays(30),
            'custom' => $request->input('expires_custom_date')
                            ? Carbon::parse($request->input('expires_custom_date'))
                            : $existing?->expires_at,
            default  => null,
        };
    }
}
