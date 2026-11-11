<?php

namespace App\Console\Commands;

use App\Support\Maintenance\SessionPruner;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SessionsPruneExpired extends Command
{
    protected $signature = 'sessions:prune-expired
        {--dry-run : Only count, delete nothing}
        {--batch=1000 : Sessions deleted per batch}
        {--max-batches=0 : Stop after this many batches (0 = no limit)}
        {--sleep=50 : Milliseconds to wait between batches}
        {--grace=300 : Sessions that expired less than this many seconds ago are left alone}
        {--json : Print JSON}';

    protected $description = 'Delete EXPIRED sessions (database and file drivers) in small batches; an active session is never touched';

    public function handle(): int
    {
        try {
            $result = (new SessionPruner(
                (bool) $this->option('dry-run'),
                (int) $this->option('batch'),
                (int) $this->option('max-batches'),
                (int) $this->option('sleep'),
                (int) $this->option('grace'),
            ))->run();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info(sprintf(
            '%ssessions (%s driver, lifetime %d min): %d expired, %d active%s',
            $result['dry_run'] ? '[dry run] ' : '',
            $result['driver'],
            $result['lifetime_minutes'],
            $result['expired'],
            $result['kept'],
            $result['dry_run'] ? ' - nothing deleted.' : sprintf(', %d deleted in %d batch(es).', $result['deleted'], $result['batches'])
        ));

        if ($result['failed'] > 0) {
            $this->warn($result['failed'] . ' sessions could not be deleted (permissions? run it as the web server user).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
