<?php

namespace App\Http\Middleware;

use App\Support\ClientNetwork;
use App\Support\RateLimiting\FormTicket;
use App\Support\RateLimiting\WindowCounter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Layered rate limit for POST /review/{token} (values and reasoning: config/ratelimits.php, docs/RATE-LIMITING.md).
 *
 * Layers, checked in this order; the first one that is over its limit answers 429 and the later ones are not counted:
 *  1. untrusted_per_ip   only for requests whose session never loaded the form (a bot posting directly), counted apart from
 *                        everybody else, so a flood cannot use up the budget of the students sharing its network;
 *  2. per_session        one browser/device;
 *  3. per_ip             all students behind one public address (IPv6: per /64);
 *  4. per_ip_campaign_hour  caps what one network can STORE on one campaign per hour (counts stored reviews only, checked first);
 *  5. per_campaign      all addresses together, protects the database from distributed floods (last on purpose: a request
 *                        refused by an IP layer never counts against the shared budget).
 * No layer replaces the IP: the session layer alone could be dodged by dropping the cookie, which is exactly what layers 1
 * and 3 are for. The IP is Laravel's resolved one, so X-Forwarded-For only counts from proxies in config/trustedproxy.php.
 */
class ThrottleReviewSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('ratelimits.review_submit');
        $counter = WindowCounter::make();

        $ip = ClientNetwork::bucket($request->ip());
        $token = (string) $request->route('token');
        $session = $request->hasSession() ? $request->session() : null;
        $trusted = $session !== null && FormTicket::isTrusted($session, FormTicket::keyFor($token), (int) $config['ticket_min_seconds']);

        $layers = [];
        if (! $trusted) {
            $layers[] = ['untrusted_per_ip', WindowCounter::key('rv-untrusted', $ip), (int) $config['untrusted_per_ip'], 60];
        }
        if ($session !== null && $session->getId()) {
            $layers[] = ['per_session', WindowCounter::key('rv-session', $session->getId()), (int) $config['per_session'], 60];
        }
        $layers[] = ['per_ip', WindowCounter::key('rv-ip', $ip), (int) $config['per_ip'], 60];
        // The shared, campaign-wide counter comes LAST: a request already refused by an IP layer must never count against it,
        // otherwise a few blocked networks could use up the budget of everybody else.
        $layers[] = ['per_campaign', WindowCounter::key('rv-campaign', $token), (int) $config['per_campaign'], 60];

        // Stored-review cap per network and campaign: only STORED reviews count (the controller adds them), so retries, validation
        // errors and replays can never lock a class out for an hour; the middleware only looks (peek) before the real layers.
        $hourKey = self::hourlyKey($ip, $token);
        if ($counter->peek($hourKey, 3600) >= (int) $config['per_ip_campaign_hour']) {
            $request->attributes->set('rate_limit_layer', 'per_ip_campaign_hour');

            return $this->tooManyRequests(3600);
        }

        foreach ($layers as [$name, $key, $limit, $window]) {
            $result = $counter->hit($key, $limit, $window);
            if (! $result['allowed']) {
                $request->attributes->set('rate_limit_layer', $name);   // read by PerformanceMonitor: which layer said no, never who

                return $this->tooManyRequests($result['retry_after']);
            }
        }

        return $next($request);
    }

    /** Counter of reviews STORED from one network on one campaign in the last hour (see ReviewController::storedResponse). */
    public static function hourlyKey(string $ipBucket, string $token): string
    {
        return WindowCounter::key('rv-stored-ip-campaign', $ipBucket . '|' . $token);
    }

    private function tooManyRequests(int $retryAfter): Response
    {
        return response()->json([
            'message'     => 'يرجى الانتظار قليلًا قبل المحاولة مرة أخرى.',   // one message for every layer: it does not reveal which one fired
            'retry_after' => $retryAfter,
        ], 429, ['Retry-After' => $retryAfter, 'Cache-Control' => 'no-store']);
    }
}
