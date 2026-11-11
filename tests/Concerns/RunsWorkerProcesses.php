<?php

namespace Tests\Concerns;

/**
 * Starts several PHP worker processes at the same moment and collects their output WITHOUT ever blocking forever: pipes are read
 * non-blocking (so a chatty stderr cannot fill the pipe and stall a worker) and every worker is killed after $timeout seconds.
 */
trait RunsWorkerProcesses
{
    /**
     * @param  array<int, array<int, string>>  $commands  one argv per worker
     * @param  array<string, string>|null  $env  null = inherit
     * @return array<int, array{stdout: string, stderr: string, exit: int}>
     */
    protected function runWorkers(array $commands, ?array $env = null, int $timeout = 60): array
    {
        $procs = [];
        $pipes = [];
        $out = [];
        foreach ($commands as $i => $command) {
            $procs[$i] = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], null, $env);
            stream_set_blocking($pipes[$i][1], false);
            stream_set_blocking($pipes[$i][2], false);
            $out[$i] = ['stdout' => '', 'stderr' => '', 'exit' => -1];
        }

        $deadline = microtime(true) + $timeout;
        $running = array_keys($procs);
        while ($running !== []) {
            foreach ($running as $key => $i) {
                $out[$i]['stdout'] .= (string) stream_get_contents($pipes[$i][1]);
                $out[$i]['stderr'] .= (string) stream_get_contents($pipes[$i][2]);
                $status = proc_get_status($procs[$i]);
                if (! $status['running']) {
                    $out[$i]['stdout'] .= (string) stream_get_contents($pipes[$i][1]);
                    $out[$i]['stderr'] .= (string) stream_get_contents($pipes[$i][2]);
                    $out[$i]['exit'] = $status['exitcode'];
                    proc_close($procs[$i]);
                    unset($running[$key]);
                }
            }
            if ($running !== [] && microtime(true) > $deadline) {
                foreach ($running as $i) {
                    proc_terminate($procs[$i]);
                    proc_close($procs[$i]);
                }
                $this->fail("worker process(es) did not finish within {$timeout}s");
            }
            if ($running !== []) {
                usleep(20000);
            }
        }

        return $out;
    }
}
