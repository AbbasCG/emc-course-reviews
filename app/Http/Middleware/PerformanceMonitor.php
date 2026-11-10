<?php

namespace App\Http\Middleware;

use App\Support\ClientNetwork;
use App\Support\RequestStats;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Writes one JSON log line for exceptional requests AFTER the response has been sent
 * (terminate runs post-response), so monitoring adds no latency for the visitor and a
 * failure in monitoring can never break a request. See config/monitoring.php.
 */
class PerformanceMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('monitoring.enabled')) {
            return $next($request);
        }

        app(RequestStats::class)->reset();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();   // PHP 8.2+: the peak must belong to THIS request (long-lived workers keep one process)
        }
        $request->attributes->set('monitor_start', $this->startTime($request));

        $response = $next($request);
        // Taken here, not in terminate(): on servers without fastcgi_finish_request terminate runs after the body was
        // transmitted, which would make big downloads look slow.
        $request->attributes->set('monitor_end', microtime(true));

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('monitoring.enabled') || ! $request->attributes->has('monitor_start')) {
            return;
        }

        try {
            $status = $response->getStatusCode();
            $durationMs = ($request->attributes->get('monitor_end', microtime(true)) - $request->attributes->get('monitor_start')) * 1000;
            $peakMb = memory_get_peak_usage(true) / 1048576;
            $stats = app(RequestStats::class);

            $isError = in_array($status, config('monitoring.status_codes'), true);
            $isSlow = $durationMs >= config('monitoring.slow_request_ms');
            $isHeavy = $peakMb >= config('monitoring.high_memory_mb');
            $hasSlowQuery = $stats->slow !== [];

            if (! ($isError || $isSlow || $isHeavy || $hasSlowQuery || config('monitoring.log_all_requests'))) {
                return;
            }

            $event = $isError ? 'http.error' : (($isSlow || $isHeavy || $hasSlowQuery) ? 'http.slow' : 'http.request');
            $level = $status >= 500 ? 'error' : (($isError || $isSlow || $isHeavy || $hasSlowQuery) ? 'warning' : 'info');

            Log::channel(config('monitoring.channel'))->log($level, $event, array_filter([
                'request_id'     => RequestId::current($request),
                'cdn_request_id' => RequestId::safe($request->headers->get('X-Hcdn-Request-Id')),
                'method'         => $request->getMethod(),
                'route'          => $this->routeName($request),
                'status'         => $status,
                'duration_ms'    => round($durationMs, 1),
                'memory_peak_mb' => round($peakMb, 1),
                'queries'        => $stats->queries,
                'query_ms'       => round($stats->queryMs, 1),
                'slow_queries'   => $stats->slow ?: null,
                'ip_hash'        => $this->ipHash($request),
            ], fn ($value) => $value !== null));
        } catch (Throwable) {
            // monitoring must never affect the application
        }
    }

    private function startTime(Request $request): float
    {
        // The web server's own request start (includes framework bootstrap); ignored when it is not plausible for this request.
        $started = (float) $request->server('REQUEST_TIME_FLOAT', 0);
        $now = microtime(true);

        return ($started > 0 && $now - $started >= 0 && $now - $started < 120) ? $started : $now;
    }

    /** Route NAME only: the URL contains the campaign token. */
    private function routeName(Request $request): string
    {
        $route = $request->route();

        return $route === null ? 'unmatched' : ($route->getName() ?: 'unnamed');
    }

    /** Salted hash of the IP network: shows whether many errors come from ONE network without storing the IP. */
    private function ipHash(Request $request): string
    {
        return substr(hash_hmac('sha256', ClientNetwork::bucket($request->ip()), (string) config('app.key')), 0, 12);
    }
}
