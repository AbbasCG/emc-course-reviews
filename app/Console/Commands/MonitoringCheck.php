<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only health/limits report to run on the server over SSH (evidence for capacity decisions).
 * It changes nothing and prints no secrets (no .env values, no passwords, no keys).
 */
class MonitoringCheck extends Command
{
    protected $signature = 'monitoring:check {--json : Print machine readable JSON}';

    protected $description = 'Read-only check of production-safety settings, PHP limits and database limits';

    public function handle(): int
    {
        $checks = [];
        $add = function (string $name, mixed $value, string $status = 'info', string $note = '') use (&$checks) {
            $checks[] = ['check' => $name, 'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, 'status' => $status, 'note' => $note];
        };

        $production = app()->environment('production');
        $debug = (bool) config('app.debug');
        $add('APP_ENV', config('app.env'));
        $add('APP_DEBUG', $debug, $production && $debug ? 'FAIL' : 'ok', $production && $debug ? 'debug pages expose code, paths, SQL and env values to visitors' : '');
        $level = (string) config('logging.channels.' . config('logging.default') . '.level', 'n/a');
        $add('LOG_LEVEL (default channel)', $level, $production && $level === 'debug' ? 'WARN' : 'info', $production && $level === 'debug' ? 'debug level logs every query-related event; use warning or error' : '');
        $add('session driver', config('session.driver'));
        $add('cache store', config('cache.default'));
        $add('queue connection', config('queue.default'));
        $add('trusted proxies', json_encode(config('trustedproxy.proxies')), config('trustedproxy.proxies') === '*' ? 'WARN' : 'info', config('trustedproxy.proxies') === '*' ? 'wildcard trust lets a request sent straight to the origin choose its own IP' : '');
        $add('monitoring enabled', (bool) config('monitoring.enabled'), config('monitoring.enabled') ? 'ok' : 'WARN');
        $add('monitoring logs every request', (bool) config('monitoring.log_all_requests'), $production && config('monitoring.log_all_requests') ? 'WARN' : 'ok', 'for load-test labs only');
        $add('logs writable', is_writable(storage_path('logs')), is_writable(storage_path('logs')) ? 'ok' : 'FAIL');

        $add('PHP version', PHP_VERSION);
        $add('memory_limit', ini_get('memory_limit'));
        $add('max_execution_time', ini_get('max_execution_time'));
        $opcache = function_exists('opcache_get_status') && ini_get('opcache.enable_cli') !== false ? (bool) ini_get('opcache.enable') : false;
        $add('opcache.enable', $opcache, $opcache ? 'ok' : 'info', 'the CLI value can differ from the web value; check phpinfo() in hPanel for the web SAPI');
        $add('expose_php', ini_get('expose_php') ? 'On' : 'Off', ini_get('expose_php') && $production ? 'WARN' : 'info', 'On adds an X-Powered-By header (the app removes it from responses)');

        $this->databaseChecks($add);

        $failed = collect($checks)->contains(fn ($c) => $c['status'] === 'FAIL');

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => ! $failed, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['check', 'value', 'status', 'note'], $checks);
            $this->{$failed ? 'error' : 'info'}($failed ? 'At least one check FAILED.' : 'No failing checks.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function databaseChecks(callable $add): void
    {
        $driver = DB::connection()->getDriverName();
        $add('database driver', $driver);

        try {
            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                $add('database reachable', (bool) DB::connection()->getPdo(), 'ok');

                return;
            }

            $version = DB::selectOne('select version() as v')->v;
            $vars = collect(DB::select("show variables where Variable_name in ('max_connections','max_user_connections','innodb_lock_wait_timeout','wait_timeout','transaction_isolation','tx_isolation','time_zone')"))
                ->mapWithKeys(fn ($r) => [$r->Variable_name => $r->Value]);
            $status = collect(DB::select("show global status where Variable_name in ('Threads_connected','Threads_running','Max_used_connections','Aborted_connects','Connection_errors_max_connections','Slow_queries','Innodb_row_lock_waits','Innodb_row_lock_time_avg')"))
                ->mapWithKeys(fn ($r) => [$r->Variable_name => $r->Value]);

            $add('database version', $version);
            foreach ($vars as $name => $value) {
                $add("mysql $name", $value);
            }
            $maxUser = (int) ($vars['max_user_connections'] ?? 0);
            $add('mysql max_user_connections (0 = unlimited)', $maxUser);
            foreach ($status as $name => $value) {
                $add("mysql $name", $value);
            }
            if ($maxUser > 0 && (int) ($status['Max_used_connections'] ?? 0) >= 0.8 * $maxUser) {
                $add('mysql connections vs per-user limit', ($status['Max_used_connections'] ?? '?') . ' / ' . $maxUser, 'WARN', 'peak connections reached 80% of the per-user limit');
            }
        } catch (Throwable $e) {
            $add('database reachable', false, 'FAIL', get_class($e));
        }
    }
}
