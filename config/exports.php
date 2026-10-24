<?php

return [

    /*
    | Disk & directory where generated export files are stored.
    | Must be a private local disk (files are only served through the
    | authenticated download route, never via a public URL).
    */
    'disk'      => env('EXPORT_DISK', 'local'),
    'directory' => 'exports',

    // Days a generated file is kept before `exports:prune` removes it.
    'retention_days' => (int) env('EXPORT_RETENTION_DAYS', 7),

    // Rows fetched per database chunk while writing files.
    'chunk_size' => (int) env('EXPORT_CHUNK_SIZE', 500),

    /*
    | Exports above these row counts are dispatched to the queue instead of
    | being generated during the HTTP request. Set EXPORT_USE_QUEUE=false to
    | always generate synchronously (e.g. when no queue worker/cron exists).
    */
    'use_queue'      => (bool) env('EXPORT_USE_QUEUE', true),
    'sync_limit'     => (int) env('EXPORT_SYNC_LIMIT', 3000),
    'pdf_sync_limit' => (int) env('EXPORT_PDF_SYNC_LIMIT', 400),

    // Hard cap for row-per-review PDF tables (use XLSX/CSV for more).
    'pdf_max_rows' => (int) env('EXPORT_PDF_MAX_ROWS', 5000),

    // Max textual comments printed in the professional PDF report.
    'pdf_comment_limit' => (int) env('EXPORT_PDF_COMMENT_LIMIT', 300),

    /*
    | Personal data (student name / email). Empty list = every authenticated
    | admin may export it; otherwise only the listed account emails may.
    */
    'pii_allowed_emails' => array_filter(array_map('trim', explode(',', (string) env('EXPORT_PII_ALLOWED_EMAILS', '')))),

    /*
    | Run a short-lived queue worker from the scheduler every minute. Useful
    | on shared hosting (Hostinger) where a permanent worker is not possible.
    */
    'schedule_queue_worker' => (bool) env('EXPORT_SCHEDULE_QUEUE_WORKER', true),

    'temp_path' => storage_path('app/tmp'),
];
