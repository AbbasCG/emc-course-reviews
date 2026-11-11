<?php

namespace App\Support\Maintenance;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Removes EXPIRED cache entries only (never a live one, never anything but cache storage).
 *
 *  - Laravel does not do this itself: a cache entry is removed only when somebody reads it again after it expired (file and
 *    database stores). Keys that are never read again (rate-limit hits of a visitor who left, one-off dedupe keys) stay forever.
 *  - "Expired" = expiration <= now - grace. The grace (default 60 s) keeps the pruner away from entries that are being renewed
 *    at this very moment.
 *  - Deletions are done in small batches with a pause in between, so locks are short and the live site is not slowed down.
 *
 * Supported stores: database and file. Anything else (array, redis, memcached) has its own expiry and is refused.
 */
final class CachePruner
{
    /** Tables that must never be touched even if a configuration mistake points the cache at them. */
    private const PROTECTED_TABLES = ['users', 'course_reviews', 'review_campaigns', 'export_logs', 'export_templates', 'admin_notifications', 'jobs', 'failed_jobs', 'job_batches', 'migrations'];

    /** Upper bound of --batch: stays below the placeholder limits of MySQL (65,535) and old SQLite (999 is avoided by the default). */
    public const MAX_BATCH = 5000;

    /** @var array{expired: int, kept: int, deleted: int, batches: int, failed: int} */
    private array $result = ['expired' => 0, 'kept' => 0, 'deleted' => 0, 'batches' => 0, 'failed' => 0];

    public function __construct(
        private readonly string $store,
        private readonly bool $dryRun = false,
        private readonly int $batchSize = 1000,
        private readonly int $maxBatches = 0,
        private readonly int $sleepMs = 50,
        private readonly int $graceSeconds = 60,
    ) {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH || $graceSeconds < 0 || $maxBatches < 0 || $sleepMs < 0) {
            throw new InvalidArgumentException('batch must be between 1 and ' . self::MAX_BATCH . ' and grace, max-batches and sleep must not be negative.');
        }
    }

    /** @return array{store: string, driver: string, dry_run: bool, expired: int, kept: int, deleted: int, batches: int, locks_deleted: int} */
    public function run(): array
    {
        $config = config("cache.stores.{$this->store}");
        if (! is_array($config) || ! isset($config['driver'])) {
            throw new InvalidArgumentException("Unknown or incomplete cache store '{$this->store}'.");
        }

        $cutoff = Carbon::now()->getTimestamp() - $this->graceSeconds;
        $locksDeleted = 0;

        match ($config['driver'] ?? '') {
            'database' => $locksDeleted = $this->pruneDatabase($config, $cutoff),
            'file' => $this->pruneFiles((string) ($config['path'] ?? ''), $cutoff),
            default => throw new InvalidArgumentException("The '{$config['driver']}' cache driver expires its own entries; nothing to prune."),
        };

        return ['store' => $this->store, 'driver' => $config['driver'], 'dry_run' => $this->dryRun, 'locks_deleted' => $locksDeleted] + $this->result;
    }

    // ───────────────────────── database store ─────────────────────────

    private function pruneDatabase(array $config, int $cutoff): int
    {
        $connection = DB::connection($config['connection'] ?? null);
        $table = (string) ($config['table'] ?? 'cache');
        $this->assertCacheTable($connection, $table, ['key', 'value', 'expiration']);

        $this->result['kept'] = $connection->table($table)->where('expiration', '>', $cutoff)->count();
        $this->result['expired'] = $connection->table($table)->where('expiration', '<=', $cutoff)->count();
        $this->result['deleted'] = $this->deleteInBatches($connection, $table, 'key', 'expiration', $cutoff);

        $locksTable = (string) ($config['lock_table'] ?? 'cache_locks');
        $locksConnection = DB::connection($config['lock_connection'] ?? ($config['connection'] ?? null));
        if (! Schema::connection($locksConnection->getName())->hasTable($locksTable)) {
            return 0;
        }
        $this->assertCacheTable($locksConnection, $locksTable, ['key', 'owner', 'expiration']);

        return $this->deleteInBatches($locksConnection, $locksTable, 'key', 'expiration', $cutoff, countBatches: false);
    }

    /**
     * Deletes rows whose $timeColumn <= $cutoff, $batchSize at a time. The delete repeats the condition, so a row that was renewed
     * between the select and the delete is NOT removed.
     */
    private function deleteInBatches(ConnectionInterface $connection, string $table, string $keyColumn, string $timeColumn, int $cutoff, bool $countBatches = true): int
    {
        if ($this->dryRun) {
            return 0;   // a dry run only counts, see pruneDatabase()
        }

        $deleted = 0;
        $batches = 0;
        while ($this->maxBatches === 0 || $batches < $this->maxBatches) {
            $keys = $connection->table($table)->where($timeColumn, '<=', $cutoff)->limit($this->batchSize)->pluck($keyColumn)->all();
            if ($keys === []) {
                break;
            }
            $deleted += $connection->table($table)->whereIn($keyColumn, $keys)->where($timeColumn, '<=', $cutoff)->delete();
            $batches++;
            if (count($keys) < $this->batchSize) {
                break;
            }
            $this->pause();
        }
        if ($countBatches) {
            $this->result['batches'] = $batches;
        }

        return $deleted;
    }

    /** @param  array<int, string>  $columns */
    private function assertCacheTable(ConnectionInterface $connection, string $table, array $columns): void
    {
        if (in_array(strtolower($table), self::PROTECTED_TABLES, true)) {
            throw new InvalidArgumentException("Refusing to prune '$table': it is not a cache table.");
        }
        if (! Schema::connection($connection->getName())->hasColumns($table, $columns)) {
            throw new InvalidArgumentException("'$table' does not look like a cache table (needs columns " . implode(', ', $columns) . ').');
        }
    }

    // ───────────────────────── file store ─────────────────────────

    /**
     * A file store entry is a file whose first 10 characters are the expiry timestamp. Only files that match that format AND are
     * expired (plus grace) are removed; the expiry is read again under an exclusive lock right before deleting.
     */
    private function pruneFiles(string $directory, int $cutoff): void
    {
        if ($directory === '' || ! is_dir($directory)) {
            return;
        }

        $pending = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (! $this->isCacheEntryPath($file->getPathname())) {
                continue;   // anything that is not exactly where FileStore puts its entries is never touched
            }
            $expiry = $this->readExpiry($file->getPathname());
            if ($expiry === null) {
                continue;   // not a cache entry (or being written right now): never touched
            }
            if ($expiry > $cutoff) {
                $this->result['kept']++;

                continue;
            }

            $this->result['expired']++;
            if ($this->dryRun) {
                continue;
            }
            if (! $this->deleteIfStillExpired($file->getPathname(), $cutoff)) {
                $this->result['failed']++;   // could not open/delete (permissions?), or renewed meanwhile: reported, never silent

                continue;
            }
            {
                $this->result['deleted']++;
                if (++$pending >= $this->batchSize) {
                    $pending = 0;
                    $this->result['batches']++;
                    if ($this->maxBatches !== 0 && $this->result['batches'] >= $this->maxBatches) {
                        return;
                    }
                    $this->pause();
                }
            }
        }
        if ($pending > 0) {
            $this->result['batches']++;
        }
    }

    /**
     * FileStore::path() layout: <dir>/<sha1[0:2]>/<sha1[2:4]>/<sha1>. Only files exactly like that are cache entries; a CSV or any
     * other file that merely starts with ten digits, in a folder that was configured by mistake, is left alone.
     */
    private function isCacheEntryPath(string $path): bool
    {
        $name = basename($path);
        $second = basename(dirname($path));
        $first = basename(dirname($path, 2));

        return preg_match('/^[0-9a-f]{40}$/', $name) === 1 && substr($name, 0, 2) === $first && substr($name, 2, 2) === $second;
    }

    private function readExpiry(string $path): ?int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = (string) @fread($handle, 10);   // on Windows a file locked by a writer cannot be read: treated as "not an entry right now"
        fclose($handle);

        return strlen($head) === 10 && ctype_digit($head) ? (int) $head : null;
    }

    private function deleteIfStillExpired(string $path, int $cutoff): bool
    {
        $handle = @fopen($path, 'rb');   // a read handle is enough for flock and works when cron and the web server are different users
        if ($handle === false) {
            return false;
        }
        $deleted = false;
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            $head = (string) @fread($handle, 10);   // on Windows a file locked by a writer cannot be read: treated as "not an entry right now"
            $stillExpired = strlen($head) === 10 && ctype_digit($head) && (int) $head <= $cutoff;   // re-checked under the lock
            if (DIRECTORY_SEPARATOR === '/') {
                $deleted = $stillExpired && @unlink($path);   // deleted while still locked: a writer cannot slip in between
                flock($handle, LOCK_UN);
                fclose($handle);
            } else {
                flock($handle, LOCK_UN);
                fclose($handle);   // Windows cannot delete an open file: a tiny window remains there (documented)
                $deleted = $stillExpired && @unlink($path);
            }
            $handle = null;
        }
        if (is_resource($handle)) {
            fclose($handle);
        }

        return $deleted;
    }

    private function pause(): void
    {
        if ($this->sleepMs > 0) {
            usleep($this->sleepMs * 1000);
        }
    }
}
