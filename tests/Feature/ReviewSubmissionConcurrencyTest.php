<?php

namespace Tests\Feature;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RunsWorkerProcesses;
use Tests\TestCase;

/**
 * Real parallelism: several PHP processes, each booting the application, submit at the same moment against one SQLite file,
 * one file cache and one set of rate-limit counter files. In-process tests cannot show these races.
 */
#[Group('concurrency')]
class ReviewSubmissionConcurrencyTest extends TestCase
{
    use RunsWorkerProcesses;

    private string $dir;
    private string $database;
    private ReviewCampaign $campaign;
    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'emc-concurrency-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/storage', 0777, true);
        $this->database = $this->dir . '/db.sqlite';
        touch($this->database);

        $this->previousDefault = config('database.default');
        config(['database.connections.sqlite.database' => $this->database]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        $this->campaign = ReviewCampaign::factory()->create();
    }

    protected function tearDown(): void
    {
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach (array_reverse(iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST))) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function payload(string $email): array
    {
        return [
            'student_name' => 'طالب', 'email' => $email, 'overall_rating' => 5, 'course_content_rating' => 4, 'instructor_rating' => 5,
            'organization_rating' => 4, 'difficulty_level' => 'متوسط', 'what_did_you_like' => 'الشرح', 'what_can_be_improved' => 'الوقت', 'would_recommend' => true,
        ];
    }

    /**
     * Starts one process per job at the same moment and waits for all of them.
     *
     * @param  array<int, array{payload: array, session: ?string, ip: string}>  $jobs
     * @return array<int, int> HTTP status of every job
     */
    private function runParallel(array $jobs, array $extraEnv = []): array
    {
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database, 'DB_URL' => '',
            'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'single',
            'LARAVEL_STORAGE_PATH' => $this->dir . '/storage', 'RATELIMIT_STORE_PATH' => $this->rateLimitDirectory(),
            'MONITORING_ENABLED' => 'false', 'TRUSTED_PROXIES' => '',
        ], $extraEnv);

        $commands = [];
        foreach ($jobs as $i => $job) {
            $config = json_encode([
                'root' => base_path(), 'token' => $this->campaign->token, 'payload' => $job['payload'], 'session' => $job['session'],
                'cookie' => config('session.cookie'), 'ip' => $job['ip'],
            ]);
            $commands[$i] = [PHP_BINARY, base_path('tests/Support/submit_worker.php'), $config];
        }

        $statuses = [];
        foreach ($this->runWorkers($commands, $env) as $i => $result) {
            $decoded = json_decode(trim(strrchr("\n" . trim($result['stdout']), "\n")), true);
            $this->assertIsArray($decoded, "worker $i produced no result. stdout: {$result['stdout']} stderr: {$result['stderr']}");
            $statuses[$i] = $decoded['status'];
        }

        return $statuses;
    }

    public function test_the_same_email_submitted_by_eight_processes_at_once_is_stored_exactly_once(): void
    {
        $jobs = [];
        foreach (range(1, 8) as $i) {
            $jobs[] = ['payload' => $this->payload('same.student@example.test'), 'session' => null, 'ip' => '203.0.113.' . $i];
        }

        $statuses = $this->runParallel($jobs);

        $this->assertSame(1, CourseReview::where('email', 'same.student@example.test')->count(), 'the unique index must stop every duplicate');
        $this->assertSame(1, count(array_filter($statuses, fn ($s) => $s === 200)), 'exactly one request wins: ' . json_encode($statuses));
        foreach ($statuses as $status) {
            $this->assertContains($status, [200, 422], 'the losers get a normal "already submitted" answer, never a 500');
        }
    }

    public function test_the_same_anonymous_review_double_submitted_in_parallel_is_stored_once(): void
    {
        $session = substr(hash('sha256', 'one-browser'), 0, 40);   // one browser: same session, same answers, no e-mail
        $jobs = array_fill(0, 5, ['payload' => $this->payload(''), 'session' => $session, 'ip' => '203.0.113.50']);

        $statuses = $this->runParallel($jobs);

        $this->assertSame(1, CourseReview::count(), 'one click, however many times the request arrives: ' . json_encode($statuses));
        foreach ($statuses as $status) {
            $this->assertContains($status, [200, 409], 'the extra requests are a replay (200) or "still processing" (409), never an error');
        }
    }

    public function test_many_students_in_parallel_behind_one_ip_do_not_corrupt_the_counters_or_the_data(): void
    {
        $jobs = [];
        foreach (range(1, 12) as $i) {
            $jobs[] = ['payload' => $this->payload("student$i@example.test"), 'session' => substr(hash('sha256', "s$i"), 0, 40), 'ip' => '203.0.113.77'];
        }

        // Without a form view they are all "untrusted". The workers get a limit of 8 (below the 12 requests) so that a lost counter
        // hit would let a 9th request through: the limit must be EXACT under parallel hits.
        $statuses = $this->runParallel($jobs, ['REVIEW_RATE_UNTRUSTED_PER_IP' => '8']);

        $this->assertSame(8, count(array_filter($statuses, fn ($s) => $s === 200)), json_encode($statuses));
        $this->assertSame(4, count(array_filter($statuses, fn ($s) => $s === 429)), json_encode($statuses));
        $this->assertSame(8, CourseReview::count());
        foreach ($statuses as $status) {
            $this->assertContains($status, [200, 429]);
        }
    }
}
