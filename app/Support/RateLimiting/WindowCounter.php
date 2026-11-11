<?php

namespace App\Support\RateLimiting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fixed-window counter that is ATOMIC across PHP processes (flock), one small file per counter.
 *
 * Why not Laravel's RateLimiter on CACHE_STORE=file? Its FileStore::increment() reads the value, adds one and writes it back
 * without holding a lock, so parallel workers overwrite each other: measured on this project with 4 parallel PHP processes
 * hammering one key, 74% of the hits were lost, i.e. the limit was enforced at roughly four times its value exactly when a
 * flood arrives (see docs/RATE-LIMITING.md). One locked read-modify-write per counter fixes that and costs one file
 * operation per limit instead of ~7.
 *
 * File name = HMAC of "scope|value" with the application key, so neither an IP address nor an e-mail address ever appears
 * in a file name or a log line, and the names cannot be precomputed by an attacker.
 *
 * It is a single-server design (files on one disk), which matches the shared-hosting deployment. On failure it FAILS OPEN
 * (the student is never blocked because a counter file is unwritable) and logs a warning at most once per minute per process.
 */
final class WindowCounter
{
    /** Counter files untouched for longer than this are deleted (the longest window in use is one hour). */
    public const STALE_AFTER = 7200;

    /** Roughly one hit in this many also removes stale files (bounded work, see prune()). */
    private const PRUNE_ONE_IN = 1000;

    private static int $lastWarning = 0;

    public function __construct(private readonly string $directory) {}

    public static function make(): self
    {
        return new self((string) config('ratelimits.store_path', storage_path('framework/ratelimit')));
    }

    /** Opaque, non-reversible counter name for "scope" + "value" (an IP bucket, a session id, an e-mail...). */
    public static function key(string $scope, string $value): string
    {
        return $scope . '-' . substr(hash_hmac('sha256', $scope . '|' . $value, (string) config('app.key')), 0, 40);
    }

    /**
     * Counts one attempt in the current window and says whether it is still within the limit.
     *
     * @return array{allowed: bool, count: int, retry_after: int}
     */
    public function hit(string $key, int $limit, int $windowSeconds): array
    {
        $now = Carbon::now()->getTimestamp();

        try {
            $path = $this->path($key);
            $handle = $this->open($path);
            if ($handle === null || ! flock($handle, LOCK_EX)) {
                throw new \RuntimeException('counter file unavailable');
            }

            try {
                $stored = json_decode((string) stream_get_contents($handle), true);
                $start = is_array($stored) ? (int) ($stored['s'] ?? 0) : 0;
                $count = is_array($stored) ? (int) ($stored['c'] ?? 0) : 0;

                if ($start === 0 || $now < $start || $now >= $start + $windowSeconds) {   // no window, a clock that stepped back, or an ended window: start anew
                    $start = $now;
                    $count = 0;
                }
                $count++;

                $record = json_encode(['s' => $start, 'c' => $count]);
                ftruncate($handle, 0);
                rewind($handle);
                // A short write (disk full) would leave an empty counter that silently never limits: treat it as a failure so it is logged.
                if (fwrite($handle, $record) !== strlen($record) || ! fflush($handle)) {
                    throw new \RuntimeException('counter file not written');
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable $e) {
            $this->warnOnce($e);

            return ['allowed' => true, 'count' => 0, 'retry_after' => 0];
        }

        if (random_int(1, self::PRUNE_ONE_IN) === 1) {
            $this->prune();
        }

        $allowed = $count <= $limit;

        return ['allowed' => $allowed, 'count' => $count, 'retry_after' => $allowed ? 0 : max(1, $start + $windowSeconds - $now)];
    }

    /** Current count without counting (tests, diagnostics). */
    public function peek(string $key, int $windowSeconds): int
    {
        $path = $this->path($key);
        $raw = @file_get_contents($path);
        $stored = $raw === false ? null : json_decode($raw, true);
        $now = Carbon::now()->getTimestamp();
        if (! is_array($stored) || $now < (int) ($stored['s'] ?? 0) || $now >= (int) ($stored['s'] ?? 0) + $windowSeconds) {
            return 0;
        }

        return (int) ($stored['c'] ?? 0);
    }

    /**
     * Deletes counter files that have not been touched for STALE_AFTER seconds (every window is shorter, so they can only be
     * expired). Looks at no more than $maxFiles files per call so it never makes a student's request slow.
     *
     * @return int number of files deleted
     */
    public function prune(int $maxFiles = 5000): int
    {
        if (! is_dir($this->directory)) {
            return 0;
        }

        $deleted = 0;
        $seen = 0;
        $cutoff = time() - self::STALE_AFTER;

        try {
            foreach (new \DirectoryIterator($this->directory) as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                if (++$seen > $maxFiles) {
                    break;
                }
                if ($file->getMTime() < $cutoff && @unlink($file->getPathname())) {
                    $deleted++;
                }
            }
        } catch (Throwable) {
            // pruning is best effort
        }

        return $deleted;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', $key);
    }

    /** @return resource|null */
    private function open(string $path)
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            return null;
        }

        $handle = @fopen($path, 'c+');

        return $handle === false ? null : $handle;
    }

    private function warnOnce(Throwable $e): void
    {
        if (time() - self::$lastWarning < 60) {
            return;
        }
        self::$lastWarning = time();
        Log::warning('Rate-limit counter unavailable; requests are allowed meanwhile.', ['exception' => class_basename($e)]);
    }
}
