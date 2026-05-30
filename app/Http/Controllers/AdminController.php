<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewCampaignRequest;
use App\Models\ReviewCampaign;
use App\Models\CourseReview;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $totalCampaigns = ReviewCampaign::count();
        $totalReviews   = CourseReview::count();
        $averageRating  = round(CourseReview::avg('overall_rating') ?? 0, 2);
        $latestReviews  = CourseReview::latest()->take(5)->with('campaign')->get();

        $query = ReviewCampaign::withCount('reviews')
                               ->withAvg('reviews', 'overall_rating')
                               ->latest();

        if ($request->filled('course_name')) {
            $query->where('course_name', 'like', '%' . $request->course_name . '%');
        }
        if ($request->filled('instructor_name')) {
            $query->where('instructor_name', 'like', '%' . $request->instructor_name . '%');
        }
        $filterStatus = $request->input('status', 'all');
        if ($filterStatus === 'active') {
            $query->where('is_active', 1)->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        } elseif ($filterStatus === 'inactive') {
            $query->where('is_active', 0);
        } elseif ($filterStatus === 'expired') {
            $query->where('expires_at', '<', now());
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $campaigns = $query->get();
        $filters   = $request->only(['course_name', 'instructor_name', 'status', 'date_from', 'date_to']);

        // Analytics: monthly reviews (last 6 months)
        $arabicMonths = ['01'=>'يناير','02'=>'فبراير','03'=>'مارس','04'=>'أبريل','05'=>'مايو','06'=>'يونيو',
                         '07'=>'يوليو','08'=>'أغسطس','09'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'];
        $monthlyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $monthlyData[$month] = 0;
        }
        $rawMonthly = CourseReview::where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->get(['created_at'])
            ->groupBy(function ($r) { return $r->created_at->format('Y-m'); })
            ->map->count();
        $monthlyData   = $monthlyData->merge($rawMonthly);
        $monthlyLabels = $monthlyData->keys()->map(function ($m) use ($arabicMonths) {
            [$year, $month] = explode('-', $m);
            return ($arabicMonths[$month] ?? $month) . ' ' . $year;
        })->values();
        $monthlyValues = $monthlyData->values();

        $recommendYes  = CourseReview::where('would_recommend', 1)->count();
        $recommendNo   = CourseReview::where('would_recommend', 0)->count();
        $difficultyRaw = CourseReview::selectRaw('difficulty_level, COUNT(*) as count')
            ->groupBy('difficulty_level')
            ->pluck('count', 'difficulty_level');

        // ── Campaign status breakdown ──
        $activeCampaigns   = ReviewCampaign::where('is_active', 1)
            ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
            ->count();
        $inactiveCampaigns  = ReviewCampaign::where('is_active', 0)->count();
        $expiredCampaigns   = ReviewCampaign::whereNotNull('expires_at')
            ->where('expires_at', '<', now())->count();
        $expiringSoonCount  = ReviewCampaign::where('is_active', 1)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();

        // ── Quick insights ──
        $topRatedCampaign     = ReviewCampaign::withCount('reviews')
            ->withAvg('reviews', 'overall_rating')
            ->whereHas('reviews')
            ->orderByDesc('reviews_avg_overall_rating')
            ->first();
        $lowestRatedCampaign  = ReviewCampaign::withCount('reviews')
            ->withAvg('reviews', 'overall_rating')
            ->whereHas('reviews')
            ->orderBy('reviews_avg_overall_rating')
            ->first();
        $mostReviewedCampaign = ReviewCampaign::withCount('reviews')
            ->whereHas('reviews')
            ->orderByDesc('reviews_count')
            ->first();
        $globalRecommendPct   = $totalReviews > 0 ? round($recommendYes / $totalReviews * 100) : 0;

        // ── Rating breakdown (5 → 1) ──
        $ratingBreakdown = collect([5, 4, 3, 2, 1])->mapWithKeys(
            fn ($s) => [$s => CourseReview::where('overall_rating', $s)->count()]
        );

        // ── Recent activity ──
        $latestCampaign = ReviewCampaign::latest()->first();

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
        $reviewCount               = $campaign->reviews()->count();
        $averageRating             = round($campaign->reviews()->avg('overall_rating') ?? 0, 2);
        $averageContentRating      = round($campaign->reviews()->avg('course_content_rating') ?? 0, 2);
        $averageInstructorRating   = round($campaign->reviews()->avg('instructor_rating') ?? 0, 2);
        $averageOrganizationRating = round($campaign->reviews()->avg('organization_rating') ?? 0, 2);
        $recommendCount            = $campaign->reviews()->where('would_recommend', 1)->count();
        $recommendPct              = $reviewCount > 0 ? round($recommendCount / $reviewCount * 100) : 0;

        $reviewQuery = $campaign->reviews()->latest();
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
        if ($request->filled('date_from')) {
            $reviewQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $reviewQuery->whereDate('created_at', '<=', $request->date_to);
        }
        $reviews       = $reviewQuery->paginate(10)->withQueryString();
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
        $reviewQuery = $campaign->reviews()->latest();
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
        if ($request->filled('date_from')) {
            $reviewQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $reviewQuery->whereDate('created_at', '<=', $request->date_to);
        }
        $reviews       = $reviewQuery->paginate(15)->withQueryString();
        $reviewFilters = $request->only(['student_name', 'email', 'rating', 'recommend', 'difficulty', 'date_from', 'date_to']);

        return view('admin.campaigns.reviews', compact('campaign', 'reviews', 'reviewFilters'));
    }

    public function exportCsv(ReviewCampaign $campaign): StreamedResponse
    {
        $reviews  = $campaign->reviews()->get();
        $filename = 'تقييمات_' . $campaign->course_name . '_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($reviews) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'اسم الطالب', 'البريد الإلكتروني', 'التقييم العام',
                'المحتوى', 'المدرب', 'التنظيم', 'مستوى الصعوبة',
                'يوصي بالدورة', 'ما أعجبه', 'ما يمكن تحسينه',
                'ملاحظات إضافية', 'تاريخ الإرسال',
            ]);

            foreach ($reviews as $review) {
                fputcsv($handle, [
                    $review->student_name,
                    $review->email ?? '',
                    $review->overall_rating,
                    $review->course_content_rating,
                    $review->instructor_rating,
                    $review->organization_rating,
                    $review->difficulty_level,
                    $review->would_recommend ? 'نعم' : 'لا',
                    $review->what_did_you_like,
                    $review->what_can_be_improved,
                    $review->additional_notes ?? '',
                    $review->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(ReviewCampaign $campaign)
    {
        try {
            $reviews = $campaign->reviews()->latest()->get();
            $total   = $reviews->count();
            $recYes  = $reviews->where('would_recommend', true)->count();
            $recNo   = $total - $recYes;

            $stats = [
                'total_reviews'        => $total,
                'overall_avg'          => $total ? round($reviews->avg('overall_rating'), 1) : 0,
                'content_avg'          => $total ? round($reviews->avg('course_content_rating'), 1) : 0,
                'instructor_avg'       => $total ? round($reviews->avg('instructor_rating'), 1) : 0,
                'organization_avg'     => $total ? round($reviews->avg('organization_rating'), 1) : 0,
                'recommend_percentage' => $total ? round($recYes / $total * 100) : 0,
                'recommend_yes'        => $recYes,
                'recommend_no'         => $recNo,
                'difficulty_data'      => $reviews->groupBy('difficulty_level')->map->count(),
            ];

            $html = view('admin.campaigns.pdf-report', compact('campaign', 'reviews', 'stats'))->render();

            $mpdf = new Mpdf([
                'mode'             => 'utf-8',
                'format'           => 'A4',
                'autoScriptToLang' => true,
                'autoLangToFont'   => true,
                'margin_top'       => 0,
                'margin_bottom'    => 10,
                'margin_left'      => 0,
                'margin_right'     => 0,
            ]);

            $mpdf->SetDirectionality('rtl');
            $mpdf->WriteHTML($html);

            $filename = 'تقرير-تقييمات-' . $campaign->id . '-' . now()->format('Y-m-d') . '.pdf';

            return response($mpdf->Output($filename, 'S'), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'فشل تصدير PDF: ' . $e->getMessage());
        }
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
