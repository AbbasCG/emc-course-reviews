<?php

/**
 * Real-database concurrency check for ReviewSubmitter (NOT part of `php artisan test`).
 *
 * Starts N PHP processes that boot the app, wait on a barrier file and then
 * submit a review at the same instant against a real MySQL/MariaDB server.
 * It verifies that nothing is lost or duplicated and that no request fails.
 *
 *   DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=emc_concurrency_test DB_USERNAME=root DB_PASSWORD= \
 *   php tests/Concurrency/parallel-submit.php same-email 30
 *
 * Scenarios: same-email | anon-same-session | distinct
 *
 * SAFETY: refuses to run unless the database host is local and the database
 * name contains "test" or "concurrency". It WIPES course_reviews,
 * review_campaigns, admin_notifications and cache in that database.
 */

use App\Models\ReviewCampaign;
use App\Services\ReviewSubmitter;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$isWorker = ($argv[1] ?? '') === '--worker';

if (!$isWorker) {
    exit(parent_main($argv));
}

exit(worker_main($argv, $app));

// ───────────────────────── worker ─────────────────────────

function worker_main(array $argv, $app): int
{
    [, , $scenario, $index, $campaignId, $barrier, $readyDir] = $argv;

    $campaign = ReviewCampaign::findOrFail((int) $campaignId);
    $data = [
        'student_name'          => "طالب $index",
        'email'                 => null,
        'overall_rating'        => 5,
        'course_content_rating' => 4,
        'instructor_rating'     => 5,
        'organization_rating'   => 4,
        'difficulty_level'      => 'متوسط',
        'what_did_you_like'     => 'الشرح',
        'what_can_be_improved'  => 'الوقت',
        'would_recommend'       => true,
        'additional_notes'      => null,
    ];
    $sessionId = str_repeat('a', 39) . ($scenario === 'anon-same-session' ? '0' : dechex((int) $index % 16));

    if ($scenario === 'same-email') {
        $data['email'] = 'same@example.test';
    } elseif ($scenario === 'distinct') {
        $data['email'] = "student$index@example.test";
    } else { // anon-same-session: identical payload and session in every worker
        $data['student_name'] = 'طالب واحد';
    }

    DB::connection()->getPdo(); // connect before the barrier so only the submit is raced
    touch("$readyDir/ready-$index");
    $deadline = microtime(true) + 60;
    while (!is_file($barrier) && microtime(true) < $deadline) {
        usleep(500);
    }

    $t = microtime(true);
    try {
        $outcome = app(ReviewSubmitter::class)->submit($campaign, $data, $sessionId);
    } catch (Throwable $e) {
        $outcome = 'EXCEPTION:' . get_class($e) . ':' . $e->getMessage();
    }

    echo json_encode(['i' => (int) $index, 'outcome' => $outcome, 'ms' => round((microtime(true) - $t) * 1000)]) . PHP_EOL;

    return 0;
}

// ───────────────────────── parent ─────────────────────────

function parent_main(array $argv): int
{
    $scenario = $argv[1] ?? 'same-email';
    $workers = (int) ($argv[2] ?? 30);

    $conn = config('database.connections.' . config('database.default'));
    $host = $conn['host'] ?? '';
    $name = $conn['database'] ?? '';
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || !preg_match('/test|concurrency/i', $name)) {
        fwrite(STDERR, "Refusing to run: host '$host' / database '$name' is not a local throwaway test database.\n");

        return 2;
    }
    if (!in_array($scenario, ['same-email', 'anon-same-session', 'distinct'], true)) {
        fwrite(STDERR, "Unknown scenario '$scenario'.\n");

        return 2;
    }

    foreach (['admin_notifications', 'course_reviews', 'review_campaigns', 'cache', 'cache_locks'] as $table) {
        DB::table($table)->delete();
    }
    $campaign = ReviewCampaign::create(['course_name' => 'Concurrency test', 'token' => Illuminate\Support\Str::random(32), 'is_active' => true, 'theme' => 'default', 'activity_type' => 'course']);

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'emc-parallel-' . getmypid();
    mkdir($dir);
    $barrier = "$dir/go";

    $procs = [];
    for ($i = 0; $i < $workers; $i++) {
        $cmd = [PHP_BINARY, __FILE__, '--worker', $scenario, (string) $i, (string) $campaign->id, $barrier, $dir];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$proc, $pipes];
    }

    $deadline = microtime(true) + 90;
    while (count(glob("$dir/ready-*")) < $workers && microtime(true) < $deadline) {
        usleep(20000);
    }
    $ready = count(glob("$dir/ready-*"));
    touch($barrier);

    $outcomes = [];
    $times = [];
    foreach ($procs as [$proc, $pipes]) {
        $out = trim(stream_get_contents($pipes[1]));
        $err = trim(stream_get_contents($pipes[2]));
        proc_close($proc);
        $row = json_decode(strrchr("\n" . $out, "\n") ?: '', true);
        if (!$row) {
            $outcomes['NO_RESULT: ' . substr($err ?: $out, 0, 200)] = ($outcomes['NO_RESULT: ' . substr($err ?: $out, 0, 200)] ?? 0) + 1;
            continue;
        }
        $outcomes[$row['outcome']] = ($outcomes[$row['outcome']] ?? 0) + 1;
        $times[] = $row['ms'];
    }

    $reviews = DB::table('course_reviews')->where('review_campaign_id', $campaign->id)->count();
    $notifications = DB::table('admin_notifications')->count();
    sort($times);

    foreach (glob("$dir/*") as $f) {
        @unlink($f);
    }
    @rmdir($dir);

    $count = fn (string $k) => $outcomes[$k] ?? 0;
    $failures = [];
    if ($ready < $workers) {
        $failures[] = "only $ready/$workers workers were ready at the barrier";
    }
    foreach ($outcomes as $k => $v) {
        if (str_starts_with($k, 'EXCEPTION') || str_starts_with($k, 'NO_RESULT') || $k === ReviewSubmitter::FAILED) {
            $failures[] = "$v x $k";
        }
    }

    switch ($scenario) {
        case 'same-email':
            $expect = $reviews === 1 && $count(ReviewSubmitter::CREATED) === 1 && $count(ReviewSubmitter::DUPLICATE_EMAIL) === $workers - 1;
            break;
        case 'anon-same-session':
            $expect = $reviews === 1 && $count(ReviewSubmitter::CREATED) === 1
                && $count(ReviewSubmitter::REPLAYED) + $count(ReviewSubmitter::IN_FLIGHT) === $workers - 1;
            break;
        default:
            $expect = $reviews === $workers && $count(ReviewSubmitter::CREATED) === $workers && $notifications === $workers;
    }
    if (!$expect) {
        $failures[] = 'unexpected outcome distribution or row count';
    }

    printf("scenario=%s workers=%d db=%s@%s\n", $scenario, $workers, $name, $host);
    printf("outcomes: %s\n", json_encode($outcomes, JSON_UNESCAPED_UNICODE));
    printf("rows: course_reviews=%d admin_notifications=%d\n", $reviews, $notifications);
    if ($times) {
        printf("submit latency ms: min=%d median=%d p95=%d max=%d\n", $times[0], $times[intdiv(count($times), 2)], $times[(int) floor(count($times) * 0.95) - 1] ?? end($times), end($times));
    }
    echo $failures ? 'RESULT: FAIL - ' . implode('; ', $failures) . PHP_EOL : "RESULT: PASS\n";

    return $failures ? 1 : 0;
}
