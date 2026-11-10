# Read-only production evidence checklist (run by the owner; nothing here writes or restarts)

SSH on Hostinger shared/cloud: port 65002, available on Premium and above (not Single); sudo disabled; password = FTP password of the main domain (https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/). Replace APP with `/home/u155427795/domains/reviews.edumc.nl/public_html` (or the Laravel root if it sits one level up).
Collect during or right after a real peak (or the failed day), not on a quiet day. Record UTC times: hPanel graphs are GMT+0.

## 1. hPanel (no SSH needed)
| Screen | Export | Proves / disproves |
|---|---|---|
| Hosting > Details (plan card) | screenshot of plan name, renewal tier, "limits reached" banner | Which row of the limits table applies (UNVERIFIED which plan is in use). Banner = limits reached. |
| Websites > Manage > Resource Usage, range 24h / 7d / 30d + custom range around the incident | screenshots of CPU, Memory, Throughput (I/O), PHP Workers, IOPS, Max processes; note red limit line and "Faults" | PHP Workers flat on the red line + "Faults" = worker exhaustion (queued/slow requests, 503). CPU > 80% sustained = CPU-bound. Memory at limit = 503 risk. IOPS/I-O at limit = disk-bound (sessions/cache/log writes). Max processes at limit = process cap. Data refresh lag 10-15 min. |
| Websites > Analytics / Access logs, filter last 24h/7d, 5xx tab | "Download logs"; screenshot of 5xx table (time, status, request, response time) | Count and status mix of 5xx (500/502/503/504/508); response time of the slow requests; which URL (GET vs POST); top IPs (is one IP/proxy dominating = throttle bucket sharing). |
| Advanced > PHP Configuration (PHP Info tab + PHP options) | screenshot of version, memory_limit, max_execution_time, max_input_vars, opcache section | Real values for this plan (OPcache defaults are UNVERIFIED in docs). OPcache off/small = higher CPU per request. |
| Databases > Management > phpMyAdmin / Database usage | screenshot of DB size and usage | Table bloat (sessions/cache). |
| Advanced > Cron Jobs | screenshot | Confirms whether schedule:run runs every minute (queue worker spawn each minute). |
| File Manager > `.logs/error_log_<domain>` (enable logErrors first; earlier errors are not recorded) | download | PHP-level fatals (memory/timeouts). Does not contain resource-limit or DB errors. |

## 2. SSH: plan and PHP
```
cd APP/..
php -v
php -m | grep -i -E 'opcache|pdo_mysql|redis|apcu'
php -i | grep -E 'memory_limit|max_execution_time|max_input|opcache.enable|opcache.memory_consumption|opcache.max_accelerated_files|opcache.validate_timestamps|post_max_size'
php -r 'echo PHP_SAPI, PHP_EOL;'      # cli values differ from web FPM/LiteSpeed values: compare with hPanel PHP Info
nproc; free -m; uptime; df -h . ; df -i .
```
Proves: effective limits vs table. NOTE the CLI php.ini can differ from the web one; trust hPanel PHP Info for web.
(`df -i` shows inode use; documented inode limits: Premium 400 000, Unlimited 600 000.)

## 3. SSH: process / resource snapshots (sample 3 times, 10 s apart, during load)
```
top -b -n1 -u $USER | head -30
ps -u $USER -o pid,ppid,etime,%cpu,%mem,rss,cmd --sort=-%cpu | head -40
ps -u $USER -o cmd | grep -c -E 'php|lsphp'    # number of PHP processes
ps -u $USER -o cmd | grep -E 'artisan' | grep -v grep   # stacked schedule:run / queue:work?
free -m; uptime
```
Proves: number of simultaneous lsphp processes vs PHP worker cap (Single 20 / Premium 40 / Unlimited 60; Cloud Startup 100 / Professional 200 / Enterprise 300); several `artisan queue:work` alive at once = scheduler overlap eating workers/RAM.

## 4. Laravel logs (read-only greps, last 7 days)
```
cd APP
ls -la storage/logs/
tail -n 200 storage/logs/laravel.log
grep -c 'SQLSTATE' storage/logs/laravel.log
grep -E -o 'SQLSTATE\[[0-9A-Z]+\]: [^(]{0,80}' storage/logs/laravel.log | sort | uniq -c | sort -rn | head
grep -c -i 'Allowed memory' storage/logs/laravel.log
grep -c -i 'Maximum execution' storage/logs/laravel.log
grep -c -i 'too many connections\|max_user_connections\|Connection refused\|MySQL server has gone away\|Lock wait timeout\|Deadlock' storage/logs/laravel.log
grep -c 'admin_notifications' storage/logs/laravel.log
grep -c -i 'TokenMismatch\|CSRF token mismatch\|419' storage/logs/laravel.log
grep -E '^\[20' storage/logs/laravel.log | cut -c2-14 | sort | uniq -c | sort -rn | head -20   # errors per hour
ls -la --time=birth storage/logs/ ; du -sh storage/logs
grep -E '^APP_DEBUG|^LOG_LEVEL|^SESSION_|^CACHE_|^QUEUE_|^APP_ENV' .env | sed 's/=.*/=<set>/;'   # prints keys only if you prefer; values for non-secret keys are fine
```
Proves: SQLSTATE 1040/1226 = connection cap (`max_user_connections` 25/50/75 web, 100/125/150 cloud); 1213/1205 = deadlock/lock waits on sessions/cache/notifications; "Allowed memory" = per-request memory exhaustion; "Maximum execution" = slow DB or CPU; 419 spike = session expiry/overload loses sessions; `admin_notifications` errors = insert failure on submit path. A huge laravel.log with LOG_LEVEL=debug + APP_DEBUG=true = log I/O cost on every request.

## 5. Web server logs
Hostinger exposes access logs through hPanel (Analytics > Access logs, "Download logs") rather than a raw nginx/apache path (UNVERIFIED for your plan). If a raw file is present:
```
ls -la APP/../logs 2>/dev/null; ls -la ~/domains/reviews.edumc.nl/logs 2>/dev/null
# on a downloaded access log:
awk '{print $9}' access.log | sort | uniq -c | sort -rn        # status mix (field index depends on format)
grep -E '" (500|502|503|504|508) ' access.log | awk '{print substr($4,2,14)}' | uniq -c   # 5xx per minute
grep 'POST /review/' access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head   # submits per IP -> 429 buckets
```
Proves: which minute failed; whether 5xx are GET (page rendering/session) or POST (insert); one IP with hundreds of POSTs (shared NAT/proxy) = throttle 429s (a 429 is not a server failure; a flood of 429 = users blocked by the old `throttle:5,1` (replaced by the per-IP 300/min + per-session 6/min limiter in PR-2; a 429 now means the new ceilings were hit)).

## 6. MySQL (credentials from `.env`; use `mysql -h HOST -u USER -p` interactively, SELECT/SHOW only)
```
SHOW VARIABLES LIKE 'max_connections';
SHOW VARIABLES LIKE 'max_user_connections';
SHOW VARIABLES LIKE 'wait_timeout';
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';
SHOW VARIABLES LIKE 'slow_query_log%';
SHOW VARIABLES LIKE 'long_query_time';
SHOW GLOBAL STATUS LIKE 'Threads_connected';
SHOW GLOBAL STATUS LIKE 'Threads_running';
SHOW GLOBAL STATUS LIKE 'Max_used_connections';
SHOW GLOBAL STATUS LIKE 'Aborted_connects';
SHOW GLOBAL STATUS LIKE 'Connection_errors_max_connections';
SHOW GLOBAL STATUS LIKE 'Innodb_row_lock_waits';
SHOW GLOBAL STATUS LIKE 'Slow_queries';
SHOW GLOBAL STATUS LIKE 'Uptime';
SHOW FULL PROCESSLIST;            -- during load; look for many "Sleep" and locked UPDATE/INSERT on sessions/cache
SELECT table_name, table_rows, ROUND((data_length+index_length)/1048576,1) AS mb
  FROM information_schema.tables WHERE table_schema = DATABASE()
  AND table_name IN ('sessions','cache','cache_locks','jobs','failed_jobs','job_batches','course_reviews','admin_notifications','review_campaigns','export_logs');
SELECT COUNT(*) FROM sessions;  SELECT COUNT(*) FROM sessions WHERE last_activity > UNIX_TIMESTAMP()-3600;
SELECT COUNT(*) FROM cache;  SELECT COUNT(*) FROM cache_locks;  SELECT COUNT(*) FROM jobs;  SELECT COUNT(*) FROM failed_jobs;
SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') m, COUNT(*) FROM course_reviews WHERE created_at > NOW() - INTERVAL 3 DAY GROUP BY m ORDER BY 2 DESC LIMIT 15;  -- actual submit rate per minute
SELECT COUNT(*) FROM admin_notifications;
```
Proves: `Max_used_connections` near the per-user cap + `Threads_connected` pinned = connection exhaustion (global max_connections is 500 on all web/cloud plans per Hostinger, per-user cap is what bites); `Aborted_connects` / `Connection_errors_max_connections` > 0 = refused connections; many sessions rows (>> students) and huge `cache` = DB-driver overhead; `jobs` backlog = queue worker not draining; submits-per-minute shows whether 500 students really got through (if peak is ~5/min total, the throttle bucket is shared). Slow query log availability on shared hosting is UNVERIFIED.

## 7. Artisan (read-only)
```
php artisan monitoring:check                # APP_DEBUG, drivers, PHP + MySQL limits (prints no secrets); add --json to keep the output
php artisan about --only=environment,cache,drivers
php artisan migrate:status            # reads migrations table only
php artisan schedule:list
php artisan queue:monitor database:default --max=100   # read-only size check (do NOT run queue:work/retry/flush)
php artisan route:list --path=review
php artisan config:show session cache queue 2>/dev/null
```
Proves: caches (config/route) are built (`about` shows "Config … CACHED"); pending migrations, e.g. a missing index; scheduler registered; drivers = database.
Avoid `php artisan tinker`, `optimize`, `cache:clear`, `queue:*` mutating commands.

## 8. How to read the whole picture
| Observation | Conclusion |
|---|---|
| PHP Workers on red line + 503/508 in 5xx table + CPU/RAM not at limit | Worker (concurrency) exhaustion: slow requests (DB sessions/cache, notifications) hold workers; capacity tied to plan row. |
| RAM on limit + 503 | Memory cap (Premium 2 GB, Unlimited 3 GB, Cloud Startup 4 GB per Hostinger limits page). |
| `max_user_connections` errors in laravel.log / `Max_used_connections` = cap | MySQL per-user connection cap (25/50/75 web). Each PHP worker holds 1 connection for session + cache + app. |
| 5xx quiet, huge 429 count, one IP dominates POST | Not an outage: the old `throttle:5,1` (replaced by the per-IP 300/min + per-session 6/min limiter in PR-2; a 429 now means the new ceilings were hit) blocked students sharing an IP (or Laravel sees the proxy IP). |
| Only PHP "Maximum execution"/"Allowed memory" entries | Code/query problem rather than plan size. |
| Many `artisan` processes alive | Cron/queue overlap consumes workers/RAM. |
| Nothing wrong server-side, 5xx absent, complaints persist | Client side: third-party Google Fonts / network / campus Wi-Fi. |

## 9. Request monitoring log (after PR-4 is deployed)
`storage/logs/performance-YYYY-MM-DD.log` holds one JSON line per exceptional request (429/500/502/503/504, slow request, high memory, slow SQL). It never contains URLs, tokens, names, e-mails, bodies or raw IPs.
```
cd APP
ls -la storage/logs/performance-*.log
grep -h -o '"status":[0-9]*' storage/logs/performance-*.log | sort | uniq -c | sort -rn                 # errors by status
grep -h '"message":"http.slow' storage/logs/performance-*.log | tail -n 20                              # slow requests / slow SQL templates
grep -h '"status":429' storage/logs/performance-*.log | grep -o '"ip_hash":"[0-9a-f]*"' | sort | uniq -c | sort -rn | head   # one network dominating the 429s?
grep -h "<request id shown in the page header X-Request-Id>" storage/logs/*.log                          # follow ONE request across logs
```
Proves: which route/status failed, how slow it was, how many queries it ran, and whether many failures came from one network (same ip_hash). 502 and 504 are produced by the CDN/web server and never appear here: count them with `loadtest/count-http-errors.sh`.
