# Local lab results (PR-4)

> **This is NOT a Staging test and NOT a Hostinger test.** No 500-user test was run on Staging or on Hostinger: no Staging
> environment, written host permission or k6 existed when this PR was written. What follows is how the *application code*
> behaves under concurrency on a developer machine, using the same student flow as `review-load.js`.
> It does not prove (or disprove) that the Hostinger Business plan can serve 500 students.

## Environment (what was really measured)

| | |
|---|---|
| Machine | Windows 11, Intel i5-11600K (12 threads), 15.5 GB RAM. The load generator ran on the **same** machine and CPU as the server. |
| Web server | Isolated Apache 2.4 + threaded `mod_php` (PHP 8.3), 60 threads (emulating "60 workers"; it is **not** LiteSpeed/lsphp), OPcache on, `APP_ENV=production`, `APP_DEBUG=false`, `config:cache route:cache view:cache` |
| Database | Isolated MariaDB 10.4 on port 3307, own data directory, user limited to 75 connections, `sessions`/`cache`/`queue` in the database like production |
| Driver | `loadtest/lab/node-load.mjs` (Node, not k6), one process, keep-alive on, new cookie jar for every student, unique e-mails, 10% without e-mail |
| Data | Synthetic campaigns created by `loadtest:prepare`, removed by `loadtest:cleanup`. The developer's own database was not touched (it was not even running). Nothing touched production. |
| Durations | Shortened vs k6 defaults: steady 30 s ramp + 120 s hold, burst 15 s ramp + 90 s hold (resubmitting loop), stress 15 s + 60 s |
| Monitoring | `MONITORING_LOG_ALL=true` (one log line per request) for the matrix; A/B below |

Calibration lessons (found while building the lab; the first numbers were discarded): OPcache was silently not loaded
(every request ~8x slower: 236 ms vs 28 ms), `.env` parsing raced under threaded PHP (random 500/419), and Windows ran out of TCP
ports above ~200 requests/s (see stress).

## Results (one run each unless noted)

Latency in ms measured at the client. "saved" = reviews found in the database by `loadtest:verify`; "reported" = HTTP 200 + redirect.
In every one of the 17 runs **saved == reported, 0 duplicates, one notification per review, 100% of responses had `X-Request-Id`**
(except status-0 connection failures, which have no response).

| Case | users | requests | GET p95 / p99 | POST p95 / p99 | 429 | 5xx | saved = reported |
|---|---|---|---|---|---|---|---|
| steady, 1 shared IP | 50 | 502 | 42 / 45 | 62 / 131 | 0 | 0 | 99 |
| steady, 1 shared IP | 100 | 1,028 | 41 / 45 | 55 / 60 | 0 | 0 | 198 |
| steady, 1 shared IP | 250 | 2,612 | 40 / 43 | 52 / 59 | 0 | 0 | 509 |
| steady, 1 shared IP | 500 | 5,156 | 42 / 47 | 56 / 67 | **96** | 0 | 894 |
| steady, 25 networks | 500 | 5,254 | 42 / 135 | 57 / 69 | 0 | 0 | 995 |
| steady, distinct IPs | 500 | 5,249 | 42 / 45 | 58 / 65 | 0 | 0 | 978 |
| burst, 1 shared IP | 50 | 998 | 40 / 42 | 52 / 59 | 0 | 0 | 321 |
| burst, 1 shared IP | 100 | 1,923 | 41 / 44 | 56 / 63 | **25** | 0 | 600 |
| burst, 1 shared IP | 250 | 4,187 | 39 / 43 | 48 / 53 | **1,108** | 1 | 600 |
| burst, 1 shared IP | 500 | 7,907 | 39 / 42 | 40 / 53 | **2,879** | 0 | 600 |
| burst, 25 networks | 500 | 9,823 | 41 / 46 | 57 / 67 | 0 | 0 | 3,164 |
| burst, distinct IPs | 500 | 9,798 | 43 / 71 | 70 / 109 | 0 | 0 | 3,134 |

Requests per second (burst, 500 users): average 77, hold-phase average 91, peak 5-second window 106-112 req/s.
Steady 500 users: average 24 req/s (peak 41). That is the "concurrent users are not requests/second" point: 500 users held
the form open but produced only ~25-40 req/s.

Server side (Laravel, from the performance log): `review.show` 18-26 ms average, 4 queries; `review.store` 32-44 ms average, 20 queries
(~15-25 ms of it in MySQL); `review.success` 17-21 ms, 3 queries; peak memory 4-6 MB per request. The monitoring log recorded
every 429 (route name + request id + hashed network) and, in the 25-network run, 6 slow events caused by one session-cleanup query (below).

Resources (non-stress runs): web-server CPU 1-17% of the 12 cores, web-server working set 87-161 MB, database connections in use
at most 5 of 75, 0 slow queries above 300 ms except the one below. Whole-machine CPU numbers are not reported because other
programs were running on the machine.

### Stress (no think time) - not an acceptance level, finds the limit

| users (1 IP unless noted) | avg req/s | peak 5 s | 429 | 500 | connection failures | saved = reported |
|---|---|---|---|---|---|---|
| 50 | 242 | 501 | 4,958 | 1,304 | 32 | 517 |
| 100 | 257 | 480 | 5,229 | 1,208 | 923 | 474 |
| 250 | 289 | 483 | 5,177 | 3,922 | 1,077 | 344 |
| 500 | 360 | 500 | 5,270 | 6,235 | 3,753 | 306 |
| 100, distinct IPs | 256 | 408 | 0 | 1,648 | 545 | 4,342 |

Every one of those 500s was `SQLSTATE[HY000] [2002] Only one usage of each socket address ... is normally permitted`: the **Windows
host ran out of TCP ports** for new MySQL connections (one connection per request, 200-500 per second, TIME_WAIT). That is a limit of
this lab machine, not of the application or of Hostinger, so **no application breaking point was found**. Even then no review
was lost or duplicated: failed requests failed before anything was saved.

### Monitoring overhead (acceptance row 14), burst 500 users, distinct IPs, 2 runs each, interleaved

| monitoring | avg ms | p95 ms | p99 ms |
|---|---|---|---|
| off | 26 / 27 | 49 / 51 | 58 / 60 |
| default (errors/slow only) | 26 / 26 | 51 / 50 | 60 / 57 |
| log every request | 29 / 27 | 53 / 51 | 65 / 58 |

Default monitoring is within noise (< +5%) of "off". Logging every request is within ~5-10% and is meant for Staging analysis only.

## Findings that matter for the 500-student event

1. **The per-IP limit (300 submissions/minute, PR-2) is the first thing that fails for a class sharing one public IP.** With 500 students on
   one Wi-Fi in the burst case only 600 reviews were saved in two minutes; the rest received HTTP 429 (2,879 times). With students on
   different networks (25 groups or distinct IPs) there were zero 429. Whether 300/min is right for a 500-student room is a product
   decision for the owner; hosting capacity is not the cause. (Behind the CDN this also depends on `TRUSTED_PROXIES` being correct.)
2. **The `sessions` and `cache` tables grow without limit.** After the matrix: 23,090 session rows and 53,075 cache rows, of which 52,690
   are already expired and are never deleted (the database cache driver does not prune). Session garbage collection
   (`delete from sessions where last_activity <= ?`, 2% of requests) took up to 708 ms once the table was large and stalled the request
   that triggered it. Not changed in this PR (out of scope); recommended follow-up: scheduled pruning of expired cache rows and old sessions.
3. A review submission runs 20 queries. At the measured rates this is not a problem, but it is where most of the server time goes.
4. Application behaviour was correct under concurrency: no duplicate reviews, no lost reviews, no failed-but-saved submissions in any run.

## What this does and does not say about Hostinger Business (60 workers, 120 processes, 3 GB)

Measured: service time of ~20-45 ms per request and 4-6 MB peak PHP memory per request, with a local database.
By Little's law the burst of 500 students (peak ~110 req/s) keeps ~4 workers busy on this machine; even if Hostinger's CPU were 5x
slower the same load would need ~20 of the 60 workers (the acceptance limit is 48). **That is arithmetic on lab numbers, not a measurement of
Hostinger.** Not measured and therefore unknown: real lsphp memory per worker (the 3 GB limit), CPU quota of a shared account, CDN/WAF
behaviour under a spike, the real MySQL latency and connection limits, and noisy neighbours. The acceptance verdict
("sufficient for 500") stays **open** until the Staging runs in `STAGING-SETUP.md` are done with written permission from the host.
