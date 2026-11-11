<?php

namespace App\Console\Commands;

use App\Support\Maintenance\CachePruner;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CachePruneExpired extends Command
{
    protected $signature = 'cache:prune-expired
        {--store= : Cache store to clean (default: the default store)}
        {--dry-run : Only count, delete nothing}
        {--batch=1000 : Entries deleted per batch}
        {--max-batches=0 : Stop after this many batches (0 = no limit)}
        {--sleep=50 : Milliseconds to wait between batches}
        {--grace=60 : Entries that expired less than this many seconds ago are left alone}
        {--json : Print JSON}';

    protected $description = 'Delete EXPIRED cache entries (database and file stores) in small batches; never touches live entries or any other table';

    public function handle(): int
    {
        try {
            $pruner = new CachePruner(
                (string) ($this->option('store') ?: config('cache.default')),
                (bool) $this->option('dry-run'),
                (int) $this->option('batch'),
                (int) $this->option('max-batches'),
                (int) $this->option('sleep'),
                (int) $this->option('grace'),
            );
            $result = $pruner->run();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info(sprintf(
            '%scache store "%s" (%s): %d expired, %d still valid%s',
            $result['dry_run'] ? '[dry run] ' : '',
            $result['store'],
            $result['driver'],
            $result['expired'],
            $result['kept'],
            $result['dry_run'] ? ' - nothing deleted.' : sprintf(', %d deleted in %d batch(es), %d expired lock(s) removed.', $result['deleted'], $result['batches'], $result['locks_deleted'])
        ));

        if ($result['failed'] > 0) {
            $this->warn($result['failed'] . ' entries could not be deleted (permissions? run it as the web server user).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
