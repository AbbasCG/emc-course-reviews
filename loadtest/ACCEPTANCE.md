# Acceptance criteria (fixed BEFORE any load test is run)

Goal: the review form serves **500 students at the same time** on the Hostinger Business plan
(60 PHP workers, 120 max processes, 3072 MB RAM, per-user MySQL connections 75), with every submitted review
saved exactly once. A test is a PASS only if **all** rows below hold for its level. Changing a number after
seeing results invalidates the run.

## 1. Concurrent users are not requests per second

| Term | Meaning here |
|---|---|
| Concurrent users (VUs) | students with the page open at the same moment |
| Requests per second (RPS) | requests the server must answer each second |
| Relation | `RPS ≈ VUs ÷ (think time + response time)`. In the `steady` profile one iteration lasts ~24 s on average (70% only browse for 5-20 s, 30% fill the form for 25-70 s), so **500 steady users are ~30-35 requests/s** (not "10-20"; corrected after review, no threshold changed). The dangerous case is a spike: 500 students opening the link within 30 s is ~17 req/s of GET, then a similar wave of POST 1-2 minutes later. |
| Per-IP ceiling | PR-2 limits one IP to 300 submissions per minute. 500 students on one Wi-Fi who all submit within one minute **exceed it by design** and ~200 of them get HTTP 429. That is a product decision (see row 5), not a hosting limit. |

Three profiles are run for every level (`PROFILE=` in `review-load.js`):

| Profile | What it models | What it proves |
|---|---|---|
| `steady` | 50 / 100 / 250 / 500 students with the form open, realistic think times, 30% submit | sustained concurrency (sessions, memory, connections) |
| `burst` | everybody opens the link and submits within ~30 s - 3 min | the arrival spike (QR code shown in class) |
| `stress` | no think time | the maximum RPS and the **breaking point** (not an acceptance level) |

Network cases (`IP_MODE=`): `single` (one shared Wi-Fi/NAT IP for everybody), `groups:25` (25 shared networks),
`distinct` (every student on their own IP). `groups:25` and `distinct` must pass every row; `single` must pass every row
except the 429 count of row 5, which is reported as described there. Before any `groups`/`distinct` run, prove that the
target really resolves the client IP per mode (`/admin/diagnostics/client-ip`), otherwise it silently becomes `single`.

## 1b. Parameters are pinned

Level, profile, IP mode, `RAMP`, `HOLD`, `THINK_SCALE` and `SUBMIT_RATIO` must be the profile defaults of `review-load.js`
(steady: ramp 3m, hold >= 6m; burst: one wave spread over 30 s; stress: ramp 1m, hold 3m). `ALLOW_429` no longer exists.
Each level is its own k6 process (`LEVELS=<one number>`). Percentiles are only reported for a metric with n >= 200 samples
(at 50-100 steady users the POST p99 rests on a handful of samples: report it as "insufficient n", do not claim a pass).
Row 14 needs at least 3 repetitions per setting because shared-host noise exceeds 5%.

## 2. Pass/fail criteria per level (steady and burst profiles)

| # | Metric | Criterion | Source |
|---|---|---|---|
| 1 | GET review page latency | p95 < 1.5 s and p99 < 3 s | k6 `http_req_duration{name:GET_review_show}` |
| 2 | POST submit latency | p95 < 2.5 s and p99 < 5 s | k6 `{name:POST_review_store}` |
| 3 | GET success page latency | p95 < 1.5 s | k6 `{name:GET_review_success}` |
| 4 | Server errors (5xx, timeouts, status 0) | < 1% of requests, and **0** for 502/503/504 | k6 status counters + access log |
| 5 | HTTP 429 | **0** for `groups:N` and `distinct`. For `single`, report the number of 429s and the peak POSTs/minute from that IP; any 429 above the 300/min ceiling is a **product finding for the owner** (raise the ceiling or accept the rejection), never a hosting pass or fail, and is never hidden or excluded | k6 `http_reqs{status:429}` |
| 6 | Reviews saved vs successful submits | `loadtest:verify` PASS: saved == `submits_ok`, no duplicate (campaign,e-mail), one notification per review | `php artisan loadtest:verify <run> --expected=<submits_ok>` |
| 7 | Lost responses | saved > reported successful must be 0 (a review saved while the student saw an error) | same command |
| 8 | PHP workers (hPanel / `sample-resources.sh`) | peak < 48 of 60 (80%) | resource sampler |
| 9 | RAM | peak < 2457 MB of 3072 MB (80%) | resource sampler |
| 10 | CPU | sustained < 80% | resource sampler |
| 11 | MySQL connections | peak connections of the application's own MySQL user < 60 of 75 (80%): sample `SHOW PROCESSLIST` filtered by that user every few seconds. `Max_used_connections` is server-wide and since start, so it is only a cross-check | `sample-resources.sh` / `SHOW PROCESSLIST` |
| 12 | Slow SQL | no query > 300 ms and 0 deadlocks / lock-wait timeouts | `performance-*.log` (`slow_queries`), MySQL status |
| 13 | Every response has `X-Request-Id` | 100% | k6 `responses_without_request_id` |
| 14 | Monitoring overhead | latency with `MONITORING_ENABLED=true` within +5% of `false` at the same level | A/B run |

Stop conditions (the script aborts itself): GET p95 > 5 s or > 5% failed requests for 60 s.

## 3. Verdict rules

* **Sufficient for 500** = levels 50, 100, 250 **and 500** pass all rows for `steady` and `burst`, in `single` (except the
  reported 429 count) and `groups:25`, on the real plan (or an equivalent plan) with the real PHP/MySQL stack, with the
  load generator itself below 60% CPU (otherwise the run is invalid).
* **Sufficient with margin** = the same, and `stress` shows the breaking point (first level where row 1, 2 or 4 fails) at
  least 2x the `burst` peak RPS. `stress` is a closed loop (it slows down when the server slows), so it finds saturation but
  cannot show a queue collapse: report it as such.
* A result from any other environment (a developer machine, a different web server, a smaller database) is **evidence
  about application behaviour only** and must be labelled that way. It never proves the hosting plan is sufficient.
