<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\ReviewCampaign;
use App\Services\ReviewSubmitter;

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

    public function store(StoreReviewRequest $request, string $token, ReviewSubmitter $submitter)
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

        $outcome = $submitter->submit($campaign, $request->validated(), $request->session()->getId());

        return match ($outcome) {
            ReviewSubmitter::CREATED,
            ReviewSubmitter::REPLAYED => response()->json(['redirect' => route('review.success', $token)]),

            ReviewSubmitter::DUPLICATE_EMAIL => response()->json([
                'errors' => ['email' => ['لقد قمت بتقديم تقييم من قبل باستخدام هذا البريد الإلكتروني']]
            ], 422),

            ReviewSubmitter::IN_FLIGHT => response()->json([
                'message' => 'جارٍ معالجة تقييمك، يرجى الانتظار لحظات.'
            ], 409),

            default => response()->json([
                'message' => 'حدث خطأ في تقديم التقييم. يرجى المحاولة مرة أخرى.'
            ], 500),
        };
    }

    public function success(string $token)
    {
        $campaign = ReviewCampaign::where('token', $token)->firstOrFail();
        return view('review.success', compact('campaign'));
    }
}
