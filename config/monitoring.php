<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request monitoring
    |--------------------------------------------------------------------------
    |
    | Cheap, privacy-safe monitoring written AFTER the response is sent (terminable
    | middleware), so it never adds latency for the student. Only exceptional
    | requests are logged by default: HTTP 429/500/502/503/504, slow requests,
    | high memory and slow SQL. A log line never contains the URL (it holds the
    | campaign token), query string, request body, cookies, headers, e-mail, name
    | or raw IP; only the route NAME, status, timings, memory, query counters, a
    | request id and a salted hash of the IP network.
    |
    | 502 and 504 are produced by the CDN / web server and never reach Laravel;
    | count them from the access log (see loadtest/count-http-errors.sh).
    |
    */
    'enabled' => (bool) env('MONITORING_ENABLED', true),

    // Log channel (config/logging.php): JSON lines in storage/logs/performance-*.log, rotated daily.
    'channel' => env('MONITORING_CHANNEL', 'performance'),

    // Statuses logged as "http.error".
    'status_codes' => [429, 500, 502, 503, 504],

    'slow_request_ms' => (int) env('MONITORING_SLOW_REQUEST_MS', 1500),
    'slow_query_ms'   => (int) env('MONITORING_SLOW_QUERY_MS', 300),
    'high_memory_mb'  => (int) env('MONITORING_HIGH_MEMORY_MB', 64),

    // Log EVERY request (event "http.request"). For load-test labs only; keep false in production.
    'log_all_requests' => (bool) env('MONITORING_LOG_ALL', false),
];
