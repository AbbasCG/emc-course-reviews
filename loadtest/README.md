# Load testing the review form (PR-4)

**Never run any of this against production.** The scripts refuse production hosts and the `loadtest:*` commands refuse
anything that is not a throwaway environment (see `STAGING-SETUP.md`).

| File | Purpose |
|---|---|
| `ACCEPTANCE.md` | Pass/fail criteria, fixed **before** any run. Read first. |
| `STAGING-SETUP.md` | What must exist (and be permitted by the host) before a Staging run. |
| `review-load.js` | The k6 script (the real test for Staging). Flow: open link, load form, submit, success page. |
| `EVIDENCE-CHECKLIST.md` | What to collect from the server during a run (workers, RAM, MySQL, access log). |
| `sample-resources.sh` | Read-only CPU/RAM/PHP-process/MySQL sampler for the Linux host (SSH). |
| `count-http-errors.sh` | Counts 429/500/502/503/504 in a web-server access log (Laravel never sees 502/504). |
| `LAB-RESULTS.md` | Results of the local-lab runs (application behaviour only, NOT Staging/Hostinger) and findings. |
| `lab/` | **Local lab only** (Windows, no k6 needed): Node driver, sampler, matrix runner, log analyzer. |

## Monitoring that ships with the app

* Every response carries `X-Request-Id` (a hostile incoming id is replaced). `X-Powered-By` is removed.
* `storage/logs/performance-YYYY-MM-DD.log` (JSON lines, 14 days). By default it records **only** requests with status
  429/500/502/503/504, slow requests (> `MONITORING_SLOW_REQUEST_MS`, 1500), heavy requests (memory) and requests that ran
  a slow query. Fields: request id, CDN request id, method, route *name*, status, duration, peak memory, query count/time,
  slow SQL *templates* (no bindings), and a keyed hash of the client network. No names, e-mails, tokens, URLs, query
  strings, bodies or raw IPs. 502/504 are produced by the CDN/web server and cannot be seen by Laravel: use `count-http-errors.sh`.
* `php artisan monitoring:check [--json]` shows the effective settings and flags unsafe ones.
* Switches: `MONITORING_ENABLED` (default true), `MONITORING_LOG_ALL` (labs/Staging only: one line per request).
  Logging happens after the response is sent (terminable middleware) so it does not add latency for the student.

## Staging run (k6)

```
php artisan loadtest:prepare --json                      # synthetic campaign + token (Staging database only)
k6 run -e BASE_URL=https://staging.<domain> -e I_CONFIRM_STAGING=yes -e TOKENS=<token> -e RUN_ID=<run> \
       -e LEVELS=50 -e PROFILE=steady -e IP_MODE=single -e OUT_DIR=results loadtest/review-load.js
php artisan loadtest:verify <run> --expected=<submits_ok from the summary>
php artisan loadtest:cleanup <run>
```

Order: levels 50, 100, 250, 500; profiles `steady`, `burst`, `stress`; `IP_MODE=single` (one shared Wi-Fi), `groups:25`, `distinct`.
Concurrent users are **not** requests per second: see `ACCEPTANCE.md` section 1.

## Local lab (application behaviour only)

`lab/` exists because k6 and a Staging copy were not available when PR-4 was written. It runs the same student flow with a
Node driver against an **isolated** Apache + MariaDB on the developer machine. Its numbers describe how the Laravel code
behaves under concurrency; they say **nothing** about Hostinger (different CPU, LiteSpeed/lsphp instead of Apache threads,
shared CPU between generator and server). Results: `LAB-RESULTS.md`.

```
RESULTS=<dir> loadtest/lab/run-matrix.sh steady:50 burst:250:single stress:500    # profile:level[:ip_mode]
```

Lab calibration lessons (they apply to any new environment): confirm OPcache is actually loaded (a missing
`zend_extension=opcache` made every request ~8x slower), run `config:cache route:cache view:cache` like production, and
pass `.env` values explicitly when PHP runs threaded (a parse race produced random 500/419 responses).
