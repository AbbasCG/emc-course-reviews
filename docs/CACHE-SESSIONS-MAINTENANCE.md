# Cache and session maintenance (PR-6)

Commands: `php artisan cache:prune-expired` and `php artisan sessions:prune-expired`
(code: `app/Support/Maintenance/`, schedule: `routes/console.php`, switches: `config/maintenance.php`).

## 1. What the PR-4 lab showed, and what it does NOT prove

PR-4's local lab (database cache and sessions, **not** production) ended with 53,075 `cache` rows (52,690 expired) and 23,090 `sessions`
rows, and one session-cleanup query took 708 ms. **Production uses `SESSION_DRIVER=file` and `CACHE_STORE=file`**, so this was not assumed to
happen there. What is certain, from the Laravel source in `vendor/`:

| Store | Who removes expired data in Laravel | Gap |
|---|---|---|
| `cache`, database driver | only a `get` of the same key after it expired | a key nobody reads again stays forever (measured: 99% of the rows) |
| `cache`, file driver | the same: only when the key is read again | same; there is no collector at all |
| `sessions`, database driver | `DatabaseSessionHandler::gc`: ONE `delete ... where last_activity <= ?`, run by a lottery (2 in 100) **inside a visitor's request** | unbatched, delays that visitor (708 ms measured in the lab) |
| `sessions`, file driver | `FileSessionHandler::gc`: scans the directory, same lottery | works; no batching, but sessions do not pile up |

What actually lands in the file cache in production after PR-1..PR-5: the anonymous-review replay keys (`ReviewSubmitter`, TTL 2 minutes,
only for reviews without e-mail), the admin export throttles, and Laravel's lock files. That is a small number of tiny files, so **no
scheduled task is added for the file drivers**. The rate-limit counters of PR-5 are not in the cache; they clean themselves.

## 2. What the commands do

Both: `--dry-run` (count only), `--batch=1000` (1-5000), `--max-batches=0` (no limit), `--sleep=50` (ms between batches), `--grace`, `--json`.

* `cache:prune-expired [--store=]` for the **database** store (also `cache_locks`) and the **file** store. A row/file is removed only when
  it expired more than `--grace` (60) seconds ago. Database: `select key ... limit N` then `delete ... where key in (...) and expiration <= cutoff`
  (the condition is repeated, so an entry renewed in between survives; the `expiration` column is already indexed). File: only files laid out exactly like Laravel's file store (`<dir>/<aa>/<bb>/<40-hex name>`) whose first 10 characters are an expiry
  timestamp in the past; the expiry is read again under an exclusive lock and, on Linux, the file is deleted while still locked; foreign files (even a CSV that
  starts with ten digits), dot files and unreadable files are never touched. Entries that cannot be deleted (permissions: run it as the web server user) are
  counted as `failed`, printed as a warning, and make the command exit with a failure code, so a cron mistake is never silent.
* `sessions:prune-expired` for the **database** and **file** drivers. A session is expired when `last_activity` (file: modification time) is
  older than `session.lifetime` minutes **plus** a 300 s grace; such a session is already unusable, so an active session cannot be affected. The
  delete repeats the condition, so a session that became active again in between is kept.
* Other drivers (`array`, `redis`, `memcached`, `cookie`...) expire on their own and are refused with a message.
* Safety: the commands only know cache/session storage. A configured table that is a business table (`course_reviews`, `review_campaigns`,
  `export_*`, `admin_notifications`, `users`, `jobs`, ...) or lacks the expected columns is refused (tested: reviews stay untouched). Output
  contains counts only, never keys, ids or session data. The files under `storage/framework/ratelimit` (PR-5) are not touched.

## 3. Effect on performance (lab MariaDB, 43,842 expired sessions and 94,276 expired cache rows, same data as PR-4)

| | time |
|---|---|
| Laravel's collector, one `delete` of 43,842 sessions | 238 ms in one statement (inside a visitor's request, with its locks) |
| `sessions:prune-expired --batch=1000 --sleep=50` | 44 batches, 4.0 s wall clock including the pauses |
| `cache:prune-expired --batch=1000 --sleep=50` | 95 batches, 8.4 s wall clock including the pauses |
| both `--dry-run` | 1.2 s |

The point of batching is the lock time of each statement and keeping the work outside a student's request, not total speed. These are numbers
from a development machine, not from Hostinger.

## 4. When cleanup is needed, and the schedule

* **Database drivers** (the `.env.example` default): rows pile up, so the commands run **daily at 03:30 / 03:40** automatically (`withoutOverlapping`),
  batch 1000 with a 50 ms pause. Set `MAINTENANCE_SCHEDULE_CACHE=false` / `MAINTENANCE_SCHEDULE_SESSIONS=false` to turn that off.
* **File drivers** (production now): nothing is scheduled. Run `cache:prune-expired --store=file --dry-run` once in a while to see how much there is; if it ever matters, run
  it without `--dry-run` or set `MAINTENANCE_SCHEDULE_CACHE=true`.
* If you switch production to the database drivers later, no code change is needed: the schedule turns itself on (the Laravel scheduler already runs every minute there).

## 5. Deployment and rollback (needs the owner's approval; nothing was run on any server)

Deploy: no migration, no new package, no env change, and **no change of `SESSION_DRIVER` / `CACHE_STORE`**. With the current file drivers
no scheduled task is added. First use on the server, as a check: `php artisan cache:prune-expired --store=file --dry-run` and
`php artisan sessions:prune-expired --dry-run` (read-only), then without `--dry-run` if the counts look right.
Rollback: redeploy the previous release (the commands and the two scheduled entries disappear); nothing else changed.

## 6. Remaining risks

* On Linux the file is deleted under the lock, but a writer that already opened the same expired file and is still waiting for the lock writes into the deleted file: that write is lost.
  For an expired key that is a normal cache miss (the replay key of `ReviewSubmitter` would at worst allow one duplicate anonymous review in that window). The 60 s grace and the
  re-check under the lock make this very rare; the parallel test (a second process renewing expired keys while the pruner runs) found no lost entry.
* Windows cannot delete an open file, so there the lock is released just before the delete and a slightly larger window exists (development machines only).
* Pruning a very large file cache walks every file once (memory stays flat); schedule it off-peak.
* `--max-batches` and `--sleep` are the knobs if a run ever competes with real traffic.
