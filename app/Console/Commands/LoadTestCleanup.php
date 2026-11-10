<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsLoadTestEnvironment;
use App\Models\AdminNotification;
use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Console\Command;

class LoadTestCleanup extends Command
{
    use GuardsLoadTestEnvironment;

    protected $signature = 'loadtest:cleanup {run : Run label to remove}';

    protected $description = 'Delete the synthetic reviews, notifications and campaign of one load-test run (staging / lab databases only)';

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

        // Same definition as loadtest:verify (GuardsLoadTestEnvironment::runReviews). Anything else is never touched.
        $ids = $this->runReviews($run)->pluck('id');
        $lookup = array_flip($ids->all());
        $notificationIds = collect();
        AdminNotification::where('type', 'review')->select(['id', 'data'])->chunkById(1000, function ($chunk) use ($lookup, &$notificationIds) {
            $notificationIds = $notificationIds->merge($chunk->filter(fn ($n) => isset($lookup[$n->data['review_id'] ?? null]))->pluck('id'));
        });
        $notifications = $notificationIds;

        foreach ($notificationIds->chunk(1000) as $chunk) {   // chunked: MySQL limits the number of placeholders
            AdminNotification::whereIn('id', $chunk)->delete();
        }
        foreach ($ids->chunk(1000) as $chunk) {
            CourseReview::whereIn('id', $chunk)->delete();
        }
        // Deleting a campaign cascades to ALL of its reviews, so keep it when anything else lives in it.
        $campaigns = 0;
        foreach (ReviewCampaign::where('course_name', "LOADTEST $run")->get() as $campaign) {
            if ($campaign->reviews()->exists()) {
                $this->warn("Campaign {$campaign->id} still has reviews that do not belong to run $run: it was kept.");

                continue;
            }
            $campaign->delete();
            $campaigns++;
        }

        $this->info("Removed {$ids->count()} reviews, {$notifications->count()} notifications and $campaigns campaign(s) of run $run.");

        return self::SUCCESS;
    }
}
