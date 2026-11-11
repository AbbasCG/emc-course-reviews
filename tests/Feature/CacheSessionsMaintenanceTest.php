<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use App\Support\Maintenance\CachePruner;
use Illuminate\Cache\FileStore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * PR-6: cache:prune-expired and sessions:prune-expired remove expired data only, in batches, and never anything else.
 */
class CacheSessionsMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'emc-maint-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/cache', 0777, true);
        mkdir($this->dir . '/sessions', 0777, true);
        config([
            'cache.stores.prunefile' => ['driver' => 'file', 'path' => $this->dir . '/cache'],
            'session.files' => $this->dir . '/sessions',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function now(): int
    {
        return Carbon::now()->getTimestamp();
    }

    private function cacheRow(string $key, int $expiration, string $table = 'cache'): void
    {
        $row = $table === 'cache' ? ['key' => $key, 'value' => 'x', 'expiration' => $expiration] : ['key' => $key, 'owner' => 'o', 'expiration' => $expiration];
        DB::table($table)->insert($row);
    }

    private function sessionRow(string $id, int $lastActivity): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => 'p', 'last_activity' => $lastActivity]);
    }

    private function prune(array $options = []): array
    {
        $options += ['--store' => 'database', '--sleep' => 0, '--json' => true];
        $code = Artisan::call('cache:prune-expired', $options);
        $output = Artisan::output();   // fetching the output empties the buffer: read it once
        $this->assertSame(0, $code, $output);

        return json_decode($output, true);
    }

    private function pruneSessions(array $options = []): array
    {
        $options += ['--sleep' => 0, '--json' => true];
        $code = Artisan::call('sessions:prune-expired', $options);
        $output = Artisan::output();
        $this->assertSame(0, $code, $output);

        return json_decode($output, true);
    }

    // ───────────────────────── database cache ─────────────────────────

    public function test_expired_cache_rows_are_removed_and_live_ones_stay(): void
    {
        $now = $this->now();
        $this->cacheRow('expired-long-ago', $now - 86400);
        $this->cacheRow('expired-just-now', $now - 61);       // older than the 60 s grace
        $this->cacheRow('expired-within-grace', $now - 30);   // might be renewed right now: left alone
        $this->cacheRow('live', $now + 600);
        $this->cacheRow('live-forever', 9999999999);

        $result = $this->prune();

        $this->assertSame(2, $result['deleted']);
        $this->assertEqualsCanonicalizing(['expired-within-grace', 'live', 'live-forever'], DB::table('cache')->pluck('key')->all());
    }

    public function test_an_empty_cache_table_is_fine_and_a_second_run_changes_nothing(): void
    {
        $this->assertSame(0, $this->prune()['deleted']);

        $this->cacheRow('old', $this->now() - 1000);
        $this->assertSame(1, $this->prune()['deleted']);
        $second = $this->prune();

        $this->assertSame(0, $second['deleted']);
        $this->assertSame(0, $second['expired']);
        $this->assertSame(0, DB::table('cache')->count());
    }

    public function test_dry_run_counts_but_deletes_nothing(): void
    {
        $now = $this->now();
        foreach (range(1, 5) as $i) {
            $this->cacheRow("old-$i", $now - 1000);
        }
        $this->cacheRow('live', $now + 600);

        $result = $this->prune(['--dry-run' => true]);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(5, $result['expired']);
        $this->assertSame(1, $result['kept']);
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(6, DB::table('cache')->count());
    }

    public function test_deletion_happens_in_batches_and_can_be_capped(): void
    {
        $now = $this->now();
        foreach (range(1, 25) as $i) {
            $this->cacheRow("old-$i", $now - 1000);
        }

        $capped = $this->prune(['--batch' => 7, '--max-batches' => 2]);
        $this->assertSame(14, $capped['deleted']);
        $this->assertSame(2, $capped['batches']);

        $rest = $this->prune(['--batch' => 7]);
        $this->assertSame(11, $rest['deleted']);
        $this->assertSame(2, $rest['batches']);   // 7 + 4
        $this->assertSame(0, DB::table('cache')->count());
    }

    public function test_expired_locks_are_removed_and_live_locks_stay(): void
    {
        $now = $this->now();
        $this->cacheRow('lock-old', $now - 1000, 'cache_locks');
        $this->cacheRow('lock-live', $now + 100, 'cache_locks');

        $result = $this->prune();

        $this->assertSame(1, $result['locks_deleted']);
        $this->assertSame(['lock-live'], DB::table('cache_locks')->pluck('key')->all());
    }

    public function test_it_never_touches_reviews_campaigns_exports_or_notifications(): void
    {
        $campaign = ReviewCampaign::factory()->create();
        CourseReview::factory()->count(3)->create(['review_campaign_id' => $campaign->id]);
        AdminNotification::create(['type' => 'review', 'title' => 't', 'message' => 'm', 'data' => []]);
        $this->cacheRow('old', $this->now() - 1000);
        $this->sessionRow('old-session', 1);
        $before = [CourseReview::count(), ReviewCampaign::count(), AdminNotification::count()];
        config(['session.driver' => 'database']);

        $this->prune();
        $this->pruneSessions();

        $this->assertSame($before, [CourseReview::count(), ReviewCampaign::count(), AdminNotification::count()]);
    }

    public function test_it_refuses_a_cache_table_that_is_really_a_business_table_or_has_the_wrong_shape(): void
    {
        $campaign = ReviewCampaign::factory()->create();
        CourseReview::factory()->count(2)->create(['review_campaign_id' => $campaign->id]);

        config(['cache.stores.database.table' => 'course_reviews']);
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'database']));
        $this->assertStringContainsString('not a cache table', Artisan::output());

        config(['cache.stores.database.table' => 'migrations']);
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'database']));

        config(['cache.stores.database.table' => 'admin_notifications']);
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'database']));

        $this->assertSame(2, CourseReview::count());
    }

    public function test_stores_without_files_or_tables_and_bad_options_are_refused(): void
    {
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'array']));
        $this->assertStringContainsString('expires its own entries', Artisan::output());
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'nope']));
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'database', '--batch' => 0]));
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'database', '--batch' => 70000]));
        config(['cache.stores.broken' => ['driver' => 'file']]);   // no path configured
        $this->assertSame(0, Artisan::call('cache:prune-expired', ['--store' => 'broken']));
        $this->assertSame(1, Artisan::call('cache:prune-expired', ['--store' => 'database', '--grace' => -5]));
    }

    // ───────────────────────── file cache ─────────────────────────

    private function fileStore(): FileStore
    {
        return new FileStore(new Filesystem, $this->dir . '/cache');
    }

    public function test_expired_cache_files_are_removed_live_and_foreign_files_stay(): void
    {
        $store = $this->fileStore();
        $store->put('old-1', 'x', 10);
        $store->put('old-2', 'x', 10);
        $store->put('live', 'x', 3600);
        file_put_contents($this->dir . '/cache/.gitignore', "*\n");                 // not a cache entry
        file_put_contents($this->dir . '/cache/notes.txt', 'keep me, I am not cache data');
        file_put_contents($this->dir . '/cache/export.csv', '0000000001,student,rating');   // starts with ten digits, like an expiry
        $hash = sha1('lookalike');
        @mkdir($this->dir . '/cache/zz/zz', 0777, true);
        file_put_contents($this->dir . '/cache/zz/zz/' . $hash, '0000000001data');          // right name, wrong folder
        $this->travel(200)->seconds();

        $result = $this->prune(['--store' => 'prunefile']);

        $this->assertSame(2, $result['deleted']);
        $this->assertFileDoesNotExist($store->path('old-1'));
        $this->assertFileExists($store->path('live'));
        $this->assertFileExists($this->dir . '/cache/.gitignore');
        $this->assertFileExists($this->dir . '/cache/notes.txt');
        $this->assertFileExists($this->dir . '/cache/export.csv', 'a foreign file that starts with ten digits must survive');
        $this->assertFileExists($this->dir . '/cache/zz/zz/' . $hash);
        $this->assertSame('x', $store->get('live'));

        $again = $this->prune(['--store' => 'prunefile']);
        $this->assertSame(0, $again['deleted'], 'a repeated run changes nothing');
    }

    public function test_file_cache_dry_run_and_empty_or_missing_directories(): void
    {
        $store = $this->fileStore();
        $store->put('old', 'x', 10);
        $this->travel(200)->seconds();

        $dry = $this->prune(['--store' => 'prunefile', '--dry-run' => true]);
        $this->assertSame(1, $dry['expired']);
        $this->assertSame(0, $dry['deleted']);
        $this->assertFileExists($store->path('old'));

        config(['cache.stores.emptydir' => ['driver' => 'file', 'path' => $this->dir . '/does-not-exist']]);
        $this->assertSame(0, $this->prune(['--store' => 'emptydir'])['deleted']);
    }

    public function test_a_file_entry_renewed_after_it_expired_is_not_deleted(): void
    {
        $store = $this->fileStore();
        $store->put('renewed', 'old', 10);
        $this->travel(200)->seconds();
        $store->put('renewed', 'new', 600);   // the key is written again before the pruner gets to it

        $this->assertSame(0, $this->prune(['--store' => 'prunefile'])['deleted']);
        $this->assertSame('new', $store->get('renewed'));
    }

    // ───────────────────────── sessions ─────────────────────────

    public function test_database_sessions_active_and_recently_expired_stay_old_ones_go(): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 120]);
        $now = $this->now();
        $this->sessionRow('active', $now - 60);
        $this->sessionRow('almost-expired', $now - 119 * 60);
        $this->sessionRow('expired-within-grace', $now - 120 * 60 - 100);   // expired, but inside the 300 s grace
        $this->sessionRow('expired', $now - 120 * 60 - 400);
        $this->sessionRow('ancient', $now - 30 * 86400);

        $result = $this->pruneSessions();

        $this->assertSame(2, $result['deleted']);
        $this->assertEqualsCanonicalizing(['active', 'almost-expired', 'expired-within-grace'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_session_pruning_dry_run_empty_table_repeat_and_batches(): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 120]);
        $this->assertSame(0, $this->pruneSessions()['deleted']);

        foreach (range(1, 12) as $i) {
            $this->sessionRow("old-$i", 1);
        }
        $this->sessionRow('active', $this->now());

        $dry = $this->pruneSessions(['--dry-run' => true]);
        $this->assertSame([12, 1, 0], [$dry['expired'], $dry['kept'], $dry['deleted']]);
        $this->assertSame(13, DB::table('sessions')->count());

        $real = $this->pruneSessions(['--batch' => 5]);
        $this->assertSame(12, $real['deleted']);
        $this->assertSame(3, $real['batches']);
        $this->assertSame(0, $this->pruneSessions()['deleted']);
        $this->assertSame(['active'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_file_sessions_are_judged_by_their_last_modification(): void
    {
        config(['session.driver' => 'file', 'session.lifetime' => 120]);
        $now = $this->now();
        foreach (['active' => $now - 60, 'expired' => $now - 3 * 3600, 'expired-2' => $now - 10 * 86400] as $name => $mtime) {
            file_put_contents($this->dir . "/sessions/$name", 'payload');
            touch($this->dir . "/sessions/$name", $mtime);
        }
        file_put_contents($this->dir . '/sessions/.gitignore', "*\n");
        touch($this->dir . '/sessions/.gitignore', 1);   // ancient, but not a session

        $result = $this->pruneSessions();

        $this->assertSame(2, $result['deleted']);
        $this->assertFileExists($this->dir . '/sessions/active');
        $this->assertFileDoesNotExist($this->dir . '/sessions/expired');
        $this->assertFileExists($this->dir . '/sessions/.gitignore');
    }

    public function test_session_drivers_with_their_own_expiry_are_refused(): void
    {
        config(['session.driver' => 'array']);

        $this->assertSame(1, Artisan::call('sessions:prune-expired'));
        $this->assertStringContainsString('expires its own data', Artisan::output());
    }

    public function test_it_refuses_a_sessions_table_that_is_really_another_table(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'cache']);

        $this->assertSame(1, Artisan::call('sessions:prune-expired'));
    }

    // ───────────────────────── scheduling ─────────────────────────

    private function scheduledCommands(): array
    {
        Artisan::all();   // loads routes/console.php, where the schedule is declared
        $out = [];
        foreach (app(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'prune-expired') && $event->filtersPass($this->app)) {
                $out[] = $event->command;
            }
        }

        return $out;
    }

    public function test_cleanup_is_scheduled_only_for_drivers_that_really_accumulate_rows(): void
    {
        config(['cache.default' => 'file', 'session.driver' => 'file', 'maintenance.schedule.cache' => null, 'maintenance.schedule.sessions' => null]);
        $this->assertSame([], $this->scheduledCommands(), 'the current production drivers (file) get no new scheduled task');

        config(['cache.default' => 'database', 'session.driver' => 'database']);
        $commands = implode(' ', $this->scheduledCommands());
        $this->assertStringContainsString('cache:prune-expired', $commands);
        $this->assertStringContainsString('sessions:prune-expired', $commands);

        config(['maintenance.schedule.cache' => false, 'maintenance.schedule.sessions' => false]);
        $this->assertSame([], $this->scheduledCommands(), 'and it can be switched off');

        config(['cache.default' => 'file', 'session.driver' => 'file', 'maintenance.schedule.cache' => true]);
        $this->assertStringContainsString('cache:prune-expired', implode(' ', $this->scheduledCommands()), 'or switched on explicitly');
    }

    // ───────────────────────── real parallelism ─────────────────────────

    #[Group('concurrency')]
    public function test_pruning_while_another_process_keeps_writing_never_removes_a_live_entry(): void
    {
        $store = $this->fileStore();
        foreach (range(1, 300) as $i) {   // expired entries nobody will ever read again: the pruner's job
            $path = $store->path("stale-$i");
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, str_pad((string) ($this->now() - 5000), 10, '0', STR_PAD_LEFT) . serialize('x'));
        }

        foreach (range(1, 100) as $i) {   // expired entries that the writer renews while the pruner runs
            $path = $store->path("renew-$i");
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, str_pad((string) ($this->now() - 5000), 10, '0', STR_PAD_LEFT) . serialize('old'));
        }

        $command = [PHP_BINARY, base_path('tests/Support/cache_writer_worker.php'), base_path(), $this->dir . '/cache', '400'];
        $proc = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deleted = 0;
        $deadline = microtime(true) + 90;
        do {
            $deleted += (new CachePruner('prunefile', false, 50, 0, 0, 0))->run()['deleted'];   // grace 0 and no pauses: as aggressive as possible
            $running = proc_get_status($proc)['running'];
        } while ($running && microtime(true) < $deadline);

        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($proc);

        $this->assertSame('', trim($err), 'writer error: ' . $err);
        $this->assertSame('0', trim($out), 'no live entry may ever be lost to the pruner');
        $this->assertGreaterThanOrEqual(300, $deleted + (new CachePruner('prunefile', false, 50, 0, 0, 0))->run()['deleted']);
        foreach (range(1, 300) as $i) {
            $this->assertFileDoesNotExist($store->path("stale-$i"));
        }
    }
}
