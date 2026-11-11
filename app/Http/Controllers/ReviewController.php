<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\ReviewCampaign;
use App\Http\Middleware\ThrottleReviewSubmission;
use App\Services\ReviewSubmitter;
use App\Support\ClientNetwork;
use App\Support\RateLimiting\FormTicket;
use App\Support\RateLimiting\WindowCounter;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function show(Request $request, string $token)
    {
        $campaign = ReviewCampaign::where('token', $token)->firstOrFail();

        if ($campaign->isExpired()) {
            return view('review.expired', compact('campaign'));
        }

        if (!$campaign->is_active) {
            return view('review.inactive', compact('campaign'));
        }

        $this->issueFormTicket($request, $token);

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
            ReviewSubmitter::CREATED => $this->storedResponse($request, $token, consumeTicket: true),
            // A replay (double click, retry after a lost response) must keep the trusted lane: the review is already saved.
            ReviewSubmitter::REPLAYED => $this->storedResponse($request, $token, consumeTicket: false),

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

    /**
     * Proof that this session really loaded the form (see FormTicket): it earns the wide "trusted" rate-limit lane. It is always
     * issued: capping it per IP would let one script on a shared network demote the whole class to the strict untrusted lane.
     * Page floods are the CDN's job; what a trusted session can then submit is limited by the layers in ThrottleReviewSubmission.
     */
    private function issueFormTicket(Request $request, string $token): void
    {
        if ($request->hasSession()) {
            FormTicket::issue($request->session(), FormTicket::keyFor($token));
        }
    }

    private function storedResponse(Request $request, string $token, bool $consumeTicket)
    {
        if ($consumeTicket) {
            FormTicket::consume($request->session(), FormTicket::keyFor($token));
            // Only a really stored review counts towards the hourly cap of this network on this campaign.
            WindowCounter::make()->hit(
                ThrottleReviewSubmission::hourlyKey(ClientNetwork::bucket($request->ip()), $token),
                PHP_INT_MAX,
                3600
            );
        }

        return response()->json(['redirect' => route('review.success', $token)]);
    }

    public function success(string $token)
    {
        $campaign = ReviewCampaign::where('token', $token)->firstOrFail();
        return view('review.success', compact('campaign'));
    }
}
