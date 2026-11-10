<?php

namespace App\Console\Commands\Concerns;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The load-test helper commands create and delete reviews, so they refuse to run anywhere that could be production:
 *  - APP_ENV must be local, staging or testing;
 *  - the database name must contain a whole word such as staging / stage / test / lab / loadtest (so "latest" or
 *    "contest" do not count) and no production-looking word (prod / production / live);
 *  - the database must be on this machine or a private network (or be named exactly in LOADTEST_ALLOW_DB);
 *  - the name is taken from BOTH the configuration and the live connection, so a DB_URL cannot smuggle in another database.
 */
trait GuardsLoadTestEnvironment
{
    private function refuseUnlessIsolated(): bool
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->error('Refusing to run: APP_ENV is "' . config('app.env') . '". Load-test helpers only run in local, staging or testing.');

            return true;
        }

        $default = (string) config('database.default');
        $names = [(string) config("database.connections.$default.database")];
        $host = (string) config("database.connections.$default.host", '');
        try {
            $connection = DB::connection();
            $names[] = (string) $connection->getDatabaseName();
            $host = (string) ($connection->getConfig('host') ?? $host);
        } catch (Throwable) {
            // an unusable connection is refused below by the name check
        }

        $allowed = (string) env('LOADTEST_ALLOW_DB', '');
        foreach (array_unique($names) as $name) {
            if ($name === ':memory:') {   // an in-memory SQLite database cannot be production
                continue;
            }
            $base = pathinfo(basename($name), PATHINFO_FILENAME);
            $throwaway = preg_match('/(^|[_.\-])(staging|stage|test|testing|lab|loadtest)([_.\-]|$)/i', $base)
                && ! preg_match('/(^|[_.\-])(prod|production|live)([_.\-]|$)/i', $base);
            if (! $throwaway) {
                $this->error('Refusing to run: the database "' . $base . '" does not look like a throw-away load-test database (its name needs a whole word staging, stage, test, lab or loadtest and no prod/production/live).');

                return true;
            }
            if (! $this->isLocalOrPrivateHost($host) && $allowed !== $name) {
                $this->error('Refusing to run: the database host is not on this machine or a private network. Set LOADTEST_ALLOW_DB=' . $base . ' only if this really is the isolated Staging database.');

                return true;
            }
        }

        return false;
    }

    private function isLocalOrPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || $host === '::1') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;   // private or reserved (127.x, 10.x, 192.168.x ...)
    }

    /**
     * Reviews that belong to one load-test run: inside the run's synthetic campaign AND either carrying the run's synthetic
     * e-mail or ("no e-mail" students, ~10%) the synthetic name "Load Test <vu>-<iteration>". Anything else is never counted or deleted.
     */
    private function runReviews(string $run): Builder
    {
        $campaignIds = ReviewCampaign::where('course_name', "LOADTEST $run")->pluck('id');

        return CourseReview::query()->whereIn('review_campaign_id', $campaignIds)->where(function ($q) use ($run) {
            $q->where('email', 'like', "lt-$run-%@loadtest.invalid")
                ->orWhere(fn ($q) => $q->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->where('student_name', 'like', 'Load Test %'));
        });
    }
}
