<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
