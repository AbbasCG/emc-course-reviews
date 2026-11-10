<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsLoadTestEnvironment;
use App\Models\ReviewCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LoadTestPrepare extends Command
{
    use GuardsLoadTestEnvironment;

    protected $signature = 'loadtest:prepare {--run= : Run label (default: generated)} {--json : Print JSON}';

    protected $description = 'Create the synthetic campaign used by a load test (staging / lab databases only)';

    public function handle(): int
    {
        if ($this->refuseUnlessIsolated()) {
            return self::FAILURE;
        }

        $run = $this->option('run') ?: strtolower(Str::random(8));
        if (! preg_match('/^[a-z0-9]{3,16}$/', $run)) {
            $this->error('--run must be 3-16 lowercase letters/digits.');

            return self::FAILURE;
        }

        $campaign = ReviewCampaign::create([
            'course_name'     => "LOADTEST $run",
            'instructor_name' => 'Load Test',
            'activity_type'   => 'course',
            'description'     => 'Synthetic campaign for load testing. Safe to delete.',
            'token'           => Str::random(32),
            'is_active'       => true,
            'theme'           => 'default',
        ]);

        $out = ['run' => $run, 'campaign_id' => $campaign->id, 'token' => $campaign->token, 'email_pattern' => "lt-$run-<vu>-<iteration>@loadtest.invalid"];
        $this->line($this->option('json') ? json_encode($out, JSON_UNESCAPED_SLASHES) : "run=$run campaign_id={$campaign->id} token={$campaign->token}");

        return self::SUCCESS;
    }
}
