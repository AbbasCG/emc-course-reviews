<?php

namespace Tests\Concerns;

use Monolog\Formatter\JsonFormatter;

/** Points the "performance" log channel at a temp JSON-lines file and reads it back. */
trait ReadsPerformanceLog
{
    private string $performanceLogPath;

    protected function usePerformanceLog(): void
    {
        $dir = storage_path('framework/testing');
        @mkdir($dir, 0777, true);
        $this->performanceLogPath = $dir . '/performance-' . uniqid('', true) . '.log';

        config(['logging.channels.performance' => [
            'driver'         => 'single',
            'path'           => $this->performanceLogPath,
            'level'          => 'info',
            'formatter'      => JsonFormatter::class,
            'formatter_with' => ['includeStacktraces' => false],
        ]]);
        app('log')->forgetChannel('performance');
    }

    /** @return array<int, array<string, mixed>> */
    protected function performanceEntries(): array
    {
        if (! is_file($this->performanceLogPath)) {
            return [];
        }

        return array_values(array_map(
            fn ($line) => json_decode($line, true),
            array_filter(explode("\n", (string) file_get_contents($this->performanceLogPath)))
        ));
    }

    protected function performanceLogRaw(): string
    {
        return is_file($this->performanceLogPath) ? (string) file_get_contents($this->performanceLogPath) : '';
    }

    protected function tearDownPerformanceLog(): void
    {
        if (isset($this->performanceLogPath) && is_file($this->performanceLogPath)) {
            @unlink($this->performanceLogPath);
        }
    }
}
