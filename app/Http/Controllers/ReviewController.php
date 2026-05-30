<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\AdminNotification;
use App\Models\ReviewCampaign;
use App\Models\CourseReview;
use Illuminate\Database\QueryException;

class ReviewController extends Controller
{
    public function show(string $token)
    {
        $campaign = ReviewCampaign::where('token', $token)->firstOrFail();

        if ($campaign->isExpired()) {
            return view('review.expired', compact('campaign'));
        }

        if (!$campaign->is_active) {
            return view('review.inactive', compact('campaign'));
        }

        return view('review.show', compact('campaign'));
    }

    public function store(StoreReviewRequest $request, string $token)
    {
        $campaign = ReviewCampaign::where('token', $token)->firstOrFail();

        if ($campaign->isExpired()) {
            return response()->json([
                'errors' => ['general' => ['انتهت صلاحية هذه الحملة']]
            ], 422);
        }

        if (!$campaign->is_active) {
            return response()->json([
                'errors' => ['general' => ['حملة التقييم غير نشطة']]
            ], 422);
        }

        if ($request->email) {
            $existingReview = CourseReview::where('review_campaign_id', $campaign->id)
                ->where('email', $request->email)
                ->exists();

            if ($existingReview) {
                return response()->json([
                    'errors' => ['email' => ['لقد قمت بتقديم تقييم من قبل باستخدام هذا البريد الإلكتروني']]
                ], 422);
            }
        }

        try {
            $review = $campaign->reviews()->create($request->validated());

            AdminNotification::create([
                'type'    => 'review',
                'title'   => 'تم استلام تقييم جديد',
                'message' => 'تم استلام تقييم جديد لدورة: ' . $campaign->course_name,
                'data'    => [
                    'campaign_id'  => $campaign->id,
                    'review_id'    => $review->id,
                    'student_name' => $request->student_name,
                    'rating'       => $request->overall_rating,
                ],
            ]);

            return response()->json(['redirect' => route('review.success', $token)]);
        } catch (QueryException $e) {
            if ($request->email && str_contains($e->getMessage(), 'unique_review_per_email_campaign')) {
                return response()->json([
                    'errors' => ['email' => ['لقد قمت بتقديم تقييم من قبل باستخدام هذا البريد الإلكتروني']]
                ], 422);
            }
            return response()->json(['message' => 'حدث خطأ في تقديم التقييم. يرجى المحاولة مرة أخرى.'], 500);
        }
    }

    public function success(string $token)
    {
        $campaign = ReviewCampaign::where('token', $token)->firstOrFail();
        return view('review.success', compact('campaign'));
    }
}
