<?php

namespace App\Support\Maintenance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Removes EXPIRED sessions only. A session is expired when it was last active more than session.lifetime minutes ago
 * (plus a grace); such a session is already unusable, Laravel itself would delete it with its own garbage collector, so an
 * active session can never be affected.
 *
 * Why this exists next to Laravel's collector: Laravel runs its collector inside a visitor's request (lottery 2/100, see
 * config/session.php): with the database driver that is ONE unbatched `delete ... where last_activity <= ?` (up to 708 ms in the
 * PR-4 lab once the table was large) and with the file driver a directory scan. This pruner does the same job in small batches
 * outside the request path.
 *
 * Supported drivers: database and file. Others have their own expiry and are refused.
 */
final class SessionPruner
{
    private const PROTECTED_TABLES = ['users', 'course_reviews', 'review_campaigns', 'export_logs', 'export_templates', 'admin_notifications', 'jobs', 'failed_jobs', 'job_batches', 'migrations', 'cache', 'cache_locks'];

    /** @var array{expired: int, kept: int, deleted: int, batches: int, failed: int} */
    private array $result = ['expired' => 0, 'kept' => 0, 'deleted' => 0, 'batches' => 0, 'failed' => 0];

    public function __construct(
        private readonly bool $dryRun = false,
        private readonly int $batchSize = 1000,
        private readonly int $maxBatches = 0,
        private readonly int $sleepMs = 50,
        private readonly int $graceSeconds = 300,
    ) {
        if ($batchSize < 1 || $batchSize > CachePruner::MAX_BATCH || $graceSeconds < 0 || $maxBatches < 0 || $sleepMs < 0) {
            throw new InvalidArgumentException('batch must be between 1 and ' . CachePruner::MAX_BATCH . ' and grace, max-batches and sleep must not be negative.');
        }
    }

    /** @return array{driver: string, dry_run: bool, lifetime_minutes: int, expired: int, kept: int, deleted: int, batches: int} */
    public function run(): array
    {
        $driver = (string) config('session.driver');
        $lifetime = (int) config('session.lifetime');
        $cutoff = Carbon::now()->getTimestamp() - $lifetime * 60 - $this->graceSeconds;

        match ($driver) {
            'database' => $this->pruneDatabase($cutoff),
            'file' => $this->pruneFiles((string) config('session.files'), $cutoff),
            default => throw new InvalidArgumentException("The '$driver' session driver expires its own data; nothing to prune."),
        };

        return ['driver' => $driver, 'dry_run' => $this->dryRun, 'lifetime_minutes' => $lifetime] + $this->result;
    }

    private function pruneDatabase(int $cutoff): void
    {
        $connection = DB::connection(config('session.connection'));
        $table = (string) config('session.table', 'sessions');
        if (in_array(strtolower($table), self::PROTECTED_TABLES, true)) {
            throw new InvalidArgumentException("Refusing to prune '$table': it is not the sessions table.");
        }
        if (! Schema::connection($connection->getName())->hasColumns($table, ['id', 'payload', 'last_activity'])) {
            throw new InvalidArgumentException("'$table' does not look like a sessions table (needs id, payload, last_activity).");
        }

        $this->result['kept'] = $connection->table($table)->where('last_activity', '>', $cutoff)->count();
        $this->result['expired'] = $connection->table($table)->where('last_activity', '<=', $cutoff)->count();
        if ($this->dryRun) {
            return;
        }

        // The delete repeats the condition, so a session that became active again between the select and the delete is kept.
        while ($this->maxBatches === 0 || $this->result['batches'] < $this->maxBatches) {
            $ids = $connection->table($table)->where('last_activity', '<=', $cutoff)->limit($this->batchSize)->pluck('id')->all();
            if ($ids === []) {
                break;
            }
            $this->result['deleted'] += $connection->table($table)->whereIn('id', $ids)->where('last_activity', '<=', $cutoff)->delete();
            $this->result['batches']++;
            if (count($ids) < $this->batchSize) {
                break;
            }
            $this->pause();
        }
    }

    private function pruneFiles(string $directory, int $cutoff): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $pending = 0;
        foreach (new \DirectoryIterator($directory) as $file) {
            // Only plain session files: never dot files such as .gitignore, never sub-directories.
            if (! $file->isFile() || str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            if ($file->getMTime() > $cutoff) {
                $this->result['kept']++;

                continue;
            }

            $this->result['expired']++;
            if ($this->dryRun) {
                continue;
            }
            clearstatcache(true, $file->getPathname());
            if (@filemtime($file->getPathname()) > $cutoff) {
                continue;   // a request touched it just now: it is active again
            }
            if (! @unlink($file->getPathname())) {
                $this->result['failed']++;   // permissions? reported, never silent

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

    private function pause(): void
    {
        if ($this->sleepMs > 0) {
            usleep($this->sleepMs * 1000);
        }
    }
}
