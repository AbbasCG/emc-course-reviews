<?php

/*
|--------------------------------------------------------------------------
| Rate limits (see docs/RATE-LIMITING.md for the reasoning and the evidence)
|--------------------------------------------------------------------------
|
| All limits are fixed windows counted by App\Support\RateLimiting\WindowCounter (atomic across PHP processes, independent
| of CACHE_STORE). Keys are HMAC hashes: no IP address, e-mail or session id is ever stored in clear.
|
| The numbers are DERIVED from one planning input, the largest class that may share one public IP (Wi-Fi / NAT / campus):
|
|   max_class_size  500   the target of PR-4 (500 students)
|   retry_factor    1.2   students retry / double-click: 500 students => at most ~600 attempts
|   => per_ip       600   attempts per minute from one IP (was 300, which a 500-student class exceeded: PR-4 lab, 2,879 x 429)
|
| Raise REVIEW_MAX_CLASS_SIZE if a larger venue is planned; do not raise the individual numbers on their own.
*/
// A limit is always >= 1: an empty or "0" environment value (a typo) falls back to the default instead of blocking every request.
$positive = fn (string $name, int $default): int => max(1, (int) (env($name) ?: $default));

$maxClass = $positive('REVIEW_MAX_CLASS_SIZE', 500);
$perIp = $positive('REVIEW_RATE_PER_IP', (int) ceil($maxClass * 1.2));

return [

    // Directory of the counter files (storage/framework/ratelimit). Must be writable by the web server user.
    'store_path' => env('RATELIMIT_STORE_PATH', storage_path('framework/ratelimit')),

    /*
    |--------------------------------------------------------------------------
    | Student review submission (POST /review/{token})
    |--------------------------------------------------------------------------
    |
    | A request is "trusted" when its session really loaded this campaign's form (App\Support\RateLimiting\FormTicket) at least
    | ticket_min_seconds ago. A bot that posts directly has no ticket and is "untrusted": it gets the strict untrusted_per_ip
    | limit, which is counted separately, so it can never use up the budget of the students sharing its network.
    |
    |   per_session        per browser/device, per minute        double clicks, a script reusing its cookie
    |   untrusted_per_ip   no valid form ticket, per IP, per minute   direct POST floods
    |   per_ip             all requests of one IP, per minute     a whole class behind one address (derived, see above)
    |   per_ip_campaign_hour  STORED reviews of one IP on one campaign, per hour   caps fake reviews per network
    |   per_campaign       one campaign, all IPs, per minute      distributed floods against the database
    */
    'review_submit' => [
        'max_class_size'       => $maxClass,
        'per_session'          => $positive('REVIEW_RATE_PER_SESSION', 6),
        'untrusted_per_ip'     => $positive('REVIEW_RATE_UNTRUSTED_PER_IP', 20),
        'per_ip'               => $perIp,
        // Counts only reviews that were STORED (retries, 422s and replays do not count): 3 per student of a full class per hour.
        'per_ip_campaign_hour' => $positive('REVIEW_RATE_PER_IP_CAMPAIGN_HOUR', $perIp * 3),
        // ~100 requests/s: what the PR-4 lab served at p99 < 110 ms. Five networks at their IP ceiling can fill it, by design.
        'per_campaign'         => $positive('REVIEW_RATE_PER_CAMPAIGN', $perIp * 10),

        // The form cannot be filled in faster than this; a "ticket" younger than this does not count as trusted.
        'ticket_min_seconds'   => $positive('REVIEW_TICKET_MIN_SECONDS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin login (POST /login): brute-force protection
    |--------------------------------------------------------------------------
    */
    'login' => [
        'per_account_ip' => $positive('LOGIN_RATE_PER_ACCOUNT_IP', 8),    // same e-mail from the same IP, per minute
        'per_ip'         => $positive('LOGIN_RATE_PER_IP', 40),          // all attempts of one IP, per minute
    ],
];
