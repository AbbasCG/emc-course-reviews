# Rate limiting (PR-5)

Applies to: the student review form (`POST /review/{token}`) and the admin login (`POST /login`).
Code: `app/Http/Middleware/Throttle*.php`, `app/Support/RateLimiting/`, numbers in `config/ratelimits.php`.

## 1. The problem and the evidence

PR-4's local lab (not Staging, not Hostinger) ran 500 students behind **one** public IP submitting within a minute:
only 600 reviews were stored in two minutes and 2,879 requests got HTTP 429, because one IP was limited to 300 submissions
per minute (PR-2). With the same students spread over 25 networks or distinct IPs there was no 429. Hosting capacity was not the cause:
the application answered at p99 under 110 ms at 110 requests/s.

## 2. What was audited (nothing assumed)

| Request | Limiter before | Needs | Now |
|---|---|---|---|
| `POST /review/{token}` | `throttle:review-submit`: 300/min per IP + 6/min per session | IP **and** session **and** campaign; a classroom shares one IP | layered limiter (section 3) |
| `GET /review/{token}`, `/success` | none | no 429 (cheap read, 32-character random token; a CDN is the place for page floods) | unchanged; the GET only issues the "form ticket" |
| `POST /login` | **none** (unlimited password guessing) | per account **and** per IP | `ThrottleLoginAttempts`: 8/min per e-mail+IP, 40/min per IP |
| `POST /admin/exports*` | `throttle:60,1` / `throttle:20,1` (per user) | authenticated, per user | unchanged (Laravel's limiter) |
| other admin routes | auth middleware only | authenticated admins | unchanged |

## 3. Design: layers instead of one key

A request is **trusted** when its session loaded this campaign's form at least `ticket_min_seconds` (3 s) earlier: the form page
stores a timestamp in the server-side session (`FormTicket`). It is not a captcha: a script can still load the page. It separates
direct-POST bots from browser-like traffic. It is always issued (capping it per IP would let one script demote a whole shared class to the strict lane).

Checked in this order; the first layer over its limit answers 429 and the later ones are not counted:

| Layer | Key | Default | Purpose |
|---|---|---|---|
| `untrusted_per_ip` | IP (IPv6: /64), only requests **without** a valid ticket | 20 / min | direct POST floods; counted apart, so a bot can **never use up the budget of the students** sharing its network |
| `per_session` | session id | 6 / min | double clicks, a script reusing its cookie |
| `per_ip` | IP (IPv6: /64) | 600 / min | one classroom behind one address |
| `per_ip_campaign_hour` | IP + campaign | 1800 **stored reviews** / hour | caps what one network can store on one campaign; only reviews that were really stored count (retries, 422s and replays do not), and it is checked before the other layers |
| `per_campaign` | campaign token, all IPs | 6000 / min (~100 requests/s, the PR-4 lab level) | distributed floods against the database; **last on purpose**: a request refused by an IP layer never counts against this shared budget |

Why these numbers (not "raised at random"): `max_class_size` 500 (the PR-4 target) x 1.2 (retries, double clicks) = **600/min per IP**;
hourly cap = 3 stored reviews per student of a full class; campaign cap = the request rate the lab served at p99 < 110 ms. Change `REVIEW_MAX_CLASS_SIZE` for a bigger venue; the rest follows.
`REVIEW_RATE_PER_IP` and the others can still be set individually. **If a server `.env` still contains `REVIEW_RATE_PER_IP=300`
it overrides the derived value** (check it before deploying).

Why not only a session key: a bot drops its cookie and starts again, so the IP layers stay. Why not only the IP: 500 students on one Wi-Fi
need a wide ceiling, which a direct POST flood must not be able to use: hence the separate, strict lane for requests without a ticket.

### Clients behind NAT, a proxy or the CDN

The IP is Laravel's resolved one: `X-Forwarded-For` only counts when the request comes from a proxy listed in `TRUSTED_PROXIES`
(`config/trustedproxy.php`, only that header is honoured). A forged header from anyone else changes nothing (tested). Check
`/admin/diagnostics/client-ip` from two networks before an event: if `resolved_ip` is the CDN's address, every student shares one bucket.

### Keys hold no personal data

Counter names are `scope-` + HMAC-SHA256(scope|value, `APP_KEY`): no IP, session id, token or e-mail appears in a file name, a file's
content or a log line (tested). The 429 log line (PR-4 monitoring) gets `rate_limit: <layer>` (which layer said no), never who.

## 4. The `CACHE_STORE=file` problem and why the counter is custom

Production uses `CACHE_STORE=file`. Laravel's file store increments a counter by reading it, adding one and writing it back **without
a lock**, so parallel PHP workers overwrite each other. Measured here (4 parallel PHP processes, 150 hits each on one key):
**600 hits expected, 158 counted: 74% lost.** (Wrapping every hit in a cache lock was exact but slow and timed out with 16 processes.) A
limiter on that store enforces roughly four times its limit exactly when a flood arrives, and it never reaches the limit on a busy IP.

`WindowCounter` keeps one small file per counter and does one **locked** read-modify-write (`flock`): with 6 parallel processes x 80
hits and a limit of 150 exactly 480 hits are counted and exactly 150 allowed (`WindowCounterTest`, real processes). One file operation per
layer instead of ~7 in Laravel's limiter: on the dev machine the old two-limit limiter cost 15 ms per request on the file store, the new
five layers 7.5 ms (Windows, NTFS: a relative number, not a Hostinger measurement).
It is independent of `CACHE_STORE`, so switching the cache later changes nothing. Single-server design (files on one disk), which is
what shared hosting is; behind several servers each would count alone.

Counters live in `storage/framework/ratelimit/` (override `RATELIMIT_STORE_PATH`). Files not touched for 2 hours (longer than any window)
are deleted by roughly every 1000th hit, scanning at most 5000 files. If the directory is not writable the limiter **fails open** (students are never
blocked by a storage problem) and logs one warning per minute per process: watch for it in `laravel.log`.

## 5. Responses

`429` with `Retry-After` (seconds until the window ends), `Cache-Control: no-store` and JSON `{message, retry_after}` (Arabic message, the same for every layer so it does not reveal which one fired).
The form shows the message plus the wait in seconds. The login answers with a real 429 whose body is the login form with the message (and `Retry-After`), or JSON for JSON clients.

## 6. Duplicates and races (unchanged, tested in parallel)

Rate limiting does not replace these: a unique index on (campaign, e-mail) and the anonymous replay guard (`ReviewSubmitter`). New
process-level tests start 5-12 PHP processes at the same moment (`ReviewSubmissionConcurrencyTest`): the same e-mail eight times stores one
review; the same anonymous submission five times stores one; the untrusted limit is exact under parallel hits.

## 7. Tests

`ReviewRateLimitingTest` (22: one browser, 500 students behind one IP in one minute, many networks, direct floods, rotating sessions or
`X-Forwarded-For`, ticket farming, IPv6, campaign and hourly caps, privacy of keys, fail-open, 429 format), `WindowCounterTest` (incl.
parallel processes), `LoginThrottleTest`, `ReviewSubmissionConcurrencyTest`. They are functional tests of the policy: they say nothing
about how many students Hostinger can serve at once (that needs Staging, see `loadtest/`).

## 8. Deployment and rollback plan (needs the owner's approval; nothing here was applied to any server)

Timing: deploy **outside lesson time**. A form page opened before the deploy has no ticket, so a class submitting right after the
deploy is served by the strict untrusted lane (20/min per IP) until students reload the page. Nobody loses data (the form shows the wait
and the student can press submit again), but a class of 500 would queue.

Before: check the server `.env` for `REVIEW_RATE_PER_IP`, `REVIEW_RATE_PER_SESSION` (an old explicit 300 would keep the old ceiling).
Deploy as usual (no migration, no new package, no env change required), then refresh the config cache (`php artisan config:cache`).
Verify: open a review link on a phone and submit (200); `storage/framework/ratelimit/` now holds files; `/admin/diagnostics/client-ip`;
`performance-*.log` shows `rate_limit` on any 429; the login rejects the 9th wrong password within a minute.
Rollback: redeploy the previous release (or revert the merge commit) and refresh the config cache. There is no data change; the
`ratelimit` directory and the new session key are harmless and can stay or be deleted. Emergency relief without a deploy: raise
`REVIEW_MAX_CLASS_SIZE` (or the single limit) in `.env` and refresh the config cache.

## 9. Remaining risks

* A bot that loads the form first (a ticket) can store up to 600 reviews per minute per IP and 1,800 per hour per IP and campaign; anonymous
  reviews have no verification. One script on a shared network can also use up its network's per-minute budget (an inherent cost of limiting by IP). This caps the damage per network but is not authentication; a captcha or e-mail confirmation is a product decision.
* Fixed windows allow up to twice the limit across a window boundary.
* Several networks at their IP ceiling can fill the per-campaign layer (6000/min) and make real students wait: that is the price of protecting the database.
* One counter file is created per distinct IP/session/campaign; a flood from many IPv6 /64s or a botnet creates many small files. Old files are pruned (every ~1000th hit,
  up to 5000 entries) but a very large flood can outpace it; watch the inode quota of the hosting account. If file creation fails the limiter fails open
  (a warning is logged), which would also switch the login throttle off.
* The login has no cross-IP limit per account: a botnet gets 8 guesses per IP per minute on one admin account.
* Rotating `APP_KEY` resets every counter and makes students who already opened the form untrusted until they reload.
* A flock on a network file system or a stalled lock holder can delay requests (the lock is blocking).
* The measured costs (7.5 ms per request, file operations) come from a Windows development machine; the real figure on Hostinger is unknown until Staging.
* If the whole `ratelimit` directory is unwritable the limiter fails open by design.
