# Staging setup plan (nothing here touches production)

Load testing production is forbidden. These steps prepare an isolated Staging copy; do not start any test until
every box is checked.

## 0. Permission and capacity (decide first)

- [ ] **Written permission from Hostinger** for load testing the plan. Their hosting agreement has no load-testing
  clause and prohibits activity that loads the server "beyond a reasonable level" and lets them suspend the account at
  their discretion. Ask support, keep the answer, and tell them the test window and peak (500 virtual users, minutes).
- [ ] **Where Staging runs.** PHP workers, RAM and CPU limits are **per hosting plan**, so a subdomain on the SAME plan
  shares the 60 workers / 3 GB with production. Either (a) run the test in a window with no real students and with
  production traffic checked in the hPanel graphs first, or (b) use a second Business plan (preferred; this is also the
  only way to see the plan limits without risking the live site).
- [ ] The load generator runs from **another machine/network** (not the Hostinger account). Mind its own CPU/bandwidth.

## 1. Isolated environment

1. Create a subdomain `staging.<your-domain>` (name must start with `staging`, `stage`, `test`, `dev`, `qa` or `loadtest`;
   `review-load.js` refuses any other `edumc.nl` host).
2. Create a **new MySQL database and a new MySQL user** that has privileges only on it (name containing `staging` or
   `test`). Never reuse production credentials. `php artisan loadtest:*` refuse databases without those words.
3. Deploy the same code (same branch/commit as production) to a separate folder. Do **not** copy real student data.
4. `.env` for Staging:
   ```
   APP_ENV=staging          # required by loadtest:* commands
   APP_DEBUG=false
   APP_URL=https://staging.<your-domain>
   DB_*=<the new database and user>
   SESSION_DRIVER=database  CACHE_STORE=database  QUEUE_CONNECTION=database   # same as production
   MAIL_MAILER=log
   LOG_LEVEL=warning
   MONITORING_ENABLED=true
   MONITORING_LOG_ALL=true  # staging only: one log line per request for the analysis
   # only for IP_MODE=distinct / groups:N. Behind the CDN, REMOTE_ADDR is the CDN edge, so trust the SAME
   # ranges as production (config/trustedproxy.php) and make sure the generator's X-Forwarded-For is honoured;
   # prove it per IP_MODE with /admin/diagnostics/client-ip before the run.
   TRUSTED_PROXIES=<same as production, plus the generator IP only if it connects directly>
   ```
5. `php artisan migrate --force` (staging database only), then `php artisan optimize` (config + route + view + event caches, as in production).
   Also enable the same cron (`schedule:run` every minute) and queue worker as production, otherwise the result is flattered;
   record the `jobs` backlog at the end of each run.
6. `php artisan monitoring:check` must show no FAIL.
7. Match production settings: same PHP version, same `memory_limit` / OPcache values (hPanel > PHP Configuration).

8. Put Staging behind the same CDN/WAF rules as production; allow-list the generator's IP so it is not blocked, and record the CDN
   cache status of the responses. `MONITORING_LOG_ALL=true` adds one log write per request that production does not have: use it
   for the analysis runs and repeat the key level once with it `false` (acceptance row 14).
9. Load generator: another network, CPU < 60% and no saturated NIC during the run (watch `http_req_blocked`); otherwise the
   run is invalid. Record its location and round-trip time to Staging.
10. `mkdir results`, and keep the resource CSVs outside any web-reachable folder.
11. Abort rule: stop at once if the hosting panel shows Staging and production sharing the same plan and production traffic is
    present, if Hostinger asks to stop, or on the k6 abort thresholds. `loadtest:cleanup` does not remove `sessions`, `cache` or
    throttle rows: truncate them on **Staging** between runs and log the row counts.

## 2. Per run

```
php artisan loadtest:prepare --json                    # prints run label + campaign token (synthetic campaign)
k6 run -e BASE_URL=https://staging.<your-domain> -e I_CONFIRM_STAGING=yes \
       -e TOKENS=<token> -e RUN_ID=<run> -e LEVELS=50 -e PROFILE=steady -e IP_MODE=single \
       -e OUT_DIR=results loadtest/review-load.js      # ONE level per run; repeat for 100, 250, 500 and profiles steady/burst/stress
php artisan loadtest:verify <run> --expected=<submits_ok from the k6 summary>
php artisan loadtest:cleanup <run>                     # removes only that run's synthetic data
```

While k6 runs, on the server (SSH, read-only): `loadtest/sample-resources.sh results/resources-<run>.csv`.
Afterwards: `php artisan monitoring:check --json`, `loadtest/count-http-errors.sh <access.log>`, and the hPanel
"Resource Usage" screenshots (CPU, RAM, PHP workers, IO, max processes, faults).

## 3. Not allowed

Running anything against the production host, creating synthetic data in the production database, reusing production
DB credentials, `loadtest:*` with `APP_ENV=production`, or changing production settings to make Staging pass.
