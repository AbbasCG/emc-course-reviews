<?php

namespace App\Support\RateLimiting;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

/**
 * Proof that THIS session really loaded the review form of a campaign: the form page stores a timestamp in the session, and
 * a submission is "trusted" only when that timestamp exists and is at least ticket_min_seconds old.
 *
 * It is not a captcha and not authentication: a script can still load the page first. What it does is
 *  - split direct-POST floods (no ticket) from browser-like traffic, so the strict limit applies to the former only, and
 *  - make every trusted submission cost its sender a page load that is itself rate-limited (ticket_issue_per_ip).
 * The ticket lives in the server-side session, so it cannot be forged or edited by the client.
 */
final class FormTicket
{
    private const KEY = 'review_form_tickets';

    /** Session key of a campaign: derived from its public token, which therefore never sits in the session file itself. */
    public static function keyFor(string $token): string
    {
        return substr(hash_hmac('sha256', $token, (string) config('app.key')), 0, 16);
    }

    /** Called when the form of a campaign is rendered. */
    public static function issue(Session $session, string $campaignKey): void
    {
        $tickets = (array) $session->get(self::KEY, []);
        if (! isset($tickets[$campaignKey])) {   // refreshing the page must not reset the minimum fill time
            $tickets[$campaignKey] = Carbon::now()->getTimestamp();
            $session->put(self::KEY, array_slice($tickets, -20, null, true));   // bounded size
        }
    }

    public static function isTrusted(Session $session, string $campaignKey, int $minSeconds): bool
    {
        $issued = ((array) $session->get(self::KEY, []))[$campaignKey] ?? null;

        return is_int($issued) && Carbon::now()->getTimestamp() - $issued >= $minSeconds;
    }

    /** After a stored review the next submission of this session no longer counts as a fresh form view. */
    public static function consume(Session $session, string $campaignKey): void
    {
        $tickets = (array) $session->get(self::KEY, []);
        unset($tickets[$campaignKey]);
        $session->put(self::KEY, $tickets);
    }
}
