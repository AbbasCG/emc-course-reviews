<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsLoadTestEnvironment;
use App\Models\AdminNotification;
use App\Models\ReviewCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadTestVerify extends Command
{
    use GuardsLoadTestEnvironment;

    protected $signature = 'loadtest:verify {run : Run label used by the k6 test}
                            {--expected= : Number of submissions k6 reported as successful (HTTP 200 + redirect)}
                            {--json : Print JSON}';

    protected $description = 'Compare reviews stored by a load-test run with what k6 reported (saved vs successful, duplicates)';

    public function handle(): int
    {
        if ($this->refuseUnlessIsolated()) {
            return self::FAILURE;
        }

        $run = (string) $this->argument('run');
        if (! preg_match('/^[a-z0-9]{3,16}$/', $run)) {
            $this->error('The run label must be 3-16 lowercase letters/digits.');

            return self::FAILURE;
        }
        $like = "lt-$run-%@loadtest.invalid";
        $campaignIds = ReviewCampaign::where('course_name', "LOADTEST $run")->pluck('id');

        // Which reviews belong to the run is defined once, in GuardsLoadTestEnvironment::runReviews().
        $saved = $this->runReviews($run)->count();
        $distinctEmails = $this->runReviews($run)->where('email', 'like', $like)->distinct()->count('email');
        $withEmail = $this->runReviews($run)->where('email', 'like', $like)->count();
        $duplicatePairs = DB::table('course_reviews')->whereIn('review_campaign_id', $campaignIds)->where('email', 'like', $like)
            ->select('review_campaign_id', 'email', DB::raw('COUNT(*) AS c'))->groupBy('review_campaign_id', 'email')->having('c', '>', 1)->count();
        $ids = array_flip($this->runReviews($run)->pluck('id')->all());
        $notifications = 0;
        AdminNotification::where('type', 'review')->select(['id', 'data'])->chunkById(1000, function ($chunk) use ($ids, &$notifications) {
            $notifications += $chunk->filter(fn ($n) => isset($ids[$n->data['review_id'] ?? null]))->count();
        });

        $expected = $this->option('expected');
        $report = [
            'run'                     => $run,
            'reviews_saved'           => $saved,
            'reviews_with_email'      => $withEmail,
            'distinct_emails'         => $distinctEmails,
            'duplicate_pairs'         => $duplicatePairs,
            'notifications_created'   => $notifications,
            'k6_successful_expected'  => $expected === null ? null : (int) $expected,
            'saved_minus_expected'    => $expected === null ? null : $saved - (int) $expected,
        ];
        $ok = $duplicatePairs === 0 && $withEmail === $distinctEmails && ($expected === null || $saved === (int) $expected);

        $report['result'] = $ok ? 'PASS' : 'FAIL';
        $this->line($this->option('json') ? json_encode($report, JSON_PRETTY_PRINT) : collect($report)->map(fn ($v, $k) => "$k: " . json_encode($v))->implode(PHP_EOL));
        if ($expected !== null && $saved > (int) $expected) {
            $this->warn('More reviews are stored than k6 counted as successful: some responses were lost (timeouts/5xx after the review was saved).');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
