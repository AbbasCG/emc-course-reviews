<?php

namespace App\Support;

/**
 * Per-request database counters (bound as "scoped", one instance per request).
 * Only the SQL TEMPLATE (with ? placeholders, never bindings) of slow queries is kept.
 */
final class RequestStats
{
    public int $queries = 0;

    public float $queryMs = 0.0;

    /** @var array<int, array{ms: float, sql: string}> */
    public array $slow = [];

    private const MAX_SLOW = 5;

    /** Start of a request: counters must never carry over (long-running workers, tests). */
    public function reset(): void
    {
        $this->queries = 0;
        $this->queryMs = 0.0;
        $this->slow = [];
    }

    public function record(string $sql, float $ms, float $slowThresholdMs): void
    {
        $this->queries++;
        $this->queryMs += $ms;

        if ($ms >= $slowThresholdMs && count($this->slow) < self::MAX_SLOW) {
            $this->slow[] = ['ms' => round($ms, 1), 'sql' => mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 240)];
        }
    }
}
