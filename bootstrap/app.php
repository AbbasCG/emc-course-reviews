<?php

use App\Http\Middleware\PerformanceMonitor;
use App\Http\Middleware\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Which proxies are trusted comes from config/trustedproxy.php (default: none).
        // Only X-Forwarded-For is honoured, so forwarded host/proto never alter URLs.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR);

        // Monitoring (config/monitoring.php): a request id on every response/log line, and a terminable
        // monitor that logs exceptional requests after the response was sent.
        // Order matters: the request id first, then the monitor, so that early exits (maintenance 503, oversized POST 413) are seen too.
        $middleware->prepend(PerformanceMonitor::class);
        $middleware->prepend(RequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A QueryException message embeds the SQL WITH its bindings, i.e. a student's name, e-mail and comments. Log the
        // SQL template and the error codes instead (the request id is added by the shared log context).
        $exceptions->report(function (QueryException $e) {
            Log::error('Database error', [
                'sqlstate'    => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
                'sql'         => Str::limit(preg_replace('/\s+/', ' ', $e->getSql()), 240),
            ]);
        })->stop();
    })->create();
