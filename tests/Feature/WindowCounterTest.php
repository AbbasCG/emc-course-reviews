<?php

namespace Tests\Feature;

use App\Support\RateLimiting\WindowCounter;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsWorkerProcesses;
use Tests\TestCase;

class WindowCounterTest extends TestCase
{
    use RunsWorkerProcesses;

    private function counter(): WindowCounter
    {
        return new WindowCounter($this->rateLimitDirectory());
    }

    public function test_it_allows_up_to_the_limit_then_reports_how_long_to_wait(): void
    {
        $counter = $this->counter();

        foreach ([1, 2, 3] as $n) {
            $this->assertTrue($counter->hit('k', 3, 60)['allowed'], "hit $n");
        }
        $blocked = $counter->hit('k', 3, 60);

        $this->assertFalse($blocked['allowed']);
        $this->assertSame(4, $blocked['count']);
        $this->assertGreaterThanOrEqual(1, $blocked['retry_after']);
        $this->assertLessThanOrEqual(60, $blocked['retry_after']);
    }

    public function test_the_window_starts_again_after_it_ends(): void
    {
        $counter = $this->counter();
        $counter->hit('k', 1, 60);
        $this->assertFalse($counter->hit('k', 1, 60)['allowed']);

        $this->travel(59)->seconds();
        $this->assertFalse($counter->hit('k', 1, 60)['allowed']);

        $this->travel(2)->seconds();
        $this->assertTrue($counter->hit('k', 1, 60)['allowed']);
        $this->assertSame(1, $counter->peek('k', 60));
    }

    public function test_a_limit_of_zero_blocks_everything_and_peek_does_not_count(): void
    {
        $counter = $this->counter();

        $this->assertFalse($counter->hit('zero', 0, 60)['allowed']);
        $this->assertSame(1, $counter->peek('zero', 60));
        $this->assertSame(1, $counter->peek('zero', 60), 'peek must not count');
        $this->assertSame(0, $counter->peek('never-used', 60));
    }

    public function test_counters_are_independent(): void
    {
        $counter = $this->counter();
        $counter->hit('a', 1, 60);

        $this->assertFalse($counter->hit('a', 1, 60)['allowed']);
        $this->assertTrue($counter->hit('b', 1, 60)['allowed']);
    }

    public function test_keys_are_opaque_stable_and_scoped(): void
    {
        $key = WindowCounter::key('ip', '203.0.113.7');

        $this->assertSame($key, WindowCounter::key('ip', '203.0.113.7'));
        $this->assertNotSame($key, WindowCounter::key('session', '203.0.113.7'));
        $this->assertNotSame($key, WindowCounter::key('ip', '203.0.113.8'));
        $this->assertMatchesRegularExpression('/^ip-[a-f0-9]{40}$/', $key);
        $this->assertStringNotContainsString('203.0.113.7', $key);

        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->assertNotSame($key, WindowCounter::key('ip', '203.0.113.7'), 'the hash is keyed with the application key');
    }

    public function test_prune_removes_only_files_that_cannot_be_active_any_more(): void
    {
        $counter = $this->counter();
        $counter->hit('old', 5, 60);
        $counter->hit('fresh', 5, 60);
        $counter->hit('hourly', 5, 3600);
        touch($this->rateLimitDirectory() . '/old', time() - WindowCounter::STALE_AFTER - 10);

        $this->assertSame(1, $counter->prune());

        $this->assertFileDoesNotExist($this->rateLimitDirectory() . '/old');
        $this->assertFileExists($this->rateLimitDirectory() . '/fresh');
        $this->assertFileExists($this->rateLimitDirectory() . '/hourly');
        $this->assertSame(0, (new WindowCounter($this->rateLimitDirectory() . '/missing'))->prune(), 'a missing directory is not an error');
    }

    public function test_a_corrupt_counter_file_starts_a_new_window_instead_of_failing(): void
    {
        $counter = $this->counter();
        $counter->hit('other', 1, 60);   // creates the directory
        file_put_contents($this->rateLimitDirectory() . '/broken', '{{{');

        $this->assertTrue($counter->hit('broken', 1, 60)['allowed']);
        $this->assertFalse($counter->hit('broken', 1, 60)['allowed']);
    }

    #[Group('concurrency')]
    public function test_parallel_php_processes_never_lose_a_hit_and_never_allow_more_than_the_limit(): void
    {
        $workers = 6;
        $hits = 80;
        $limit = 150;
        $root = base_path();
        $worker = base_path('tests/Support/counter_worker.php');

        $command = [PHP_BINARY, $worker, $root, $this->rateLimitDirectory(), 'shared-ip', (string) $hits, (string) $limit, '3600'];
        $allowed = 0;
        foreach ($this->runWorkers(array_fill(0, $workers, $command)) as $result) {
            $this->assertSame('', trim($result['stderr']), 'worker error: ' . $result['stderr']);
            $allowed += (int) trim($result['stdout']);
        }

        // Laravel's file cache lost ~74% of the hits in the same experiment (docs/RATE-LIMITING.md); here nothing may be lost.
        $this->assertSame($workers * $hits, $this->counter()->peek('shared-ip', 3600), 'no hit may be lost');
        $this->assertSame($limit, $allowed, 'exactly the limit is allowed, not one more');
    }
}
