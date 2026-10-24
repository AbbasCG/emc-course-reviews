<?php

use App\Models\ExportLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exports:prune', function () {
    $disk    = Storage::disk(config('exports.disk'));
    $expired = 0;

    ExportLog::where('status', ExportLog::STATUS_COMPLETED)
        ->where('expires_at', '<=', now())
        ->chunkById(200, function ($logs) use ($disk, &$expired) {
            foreach ($logs as $log) {
                if ($log->file_path) {
                    $disk->delete($log->file_path);
                }
                $log->update(['status' => ExportLog::STATUS_EXPIRED, 'file_path' => null]);
                $expired++;
            }
        });

    // Jobs that never ran (no worker) are marked failed after a day.
    $stale = ExportLog::whereIn('status', [ExportLog::STATUS_PENDING, ExportLog::STATUS_PROCESSING])
        ->where('created_at', '<', now()->subDay())
        ->update(['status' => ExportLog::STATUS_FAILED, 'error' => 'انتهت مهلة المعالجة.', 'completed_at' => now()]);

    $this->info("Expired files removed: {$expired}. Stale jobs closed: {$stale}.");
})->purpose('Delete export files past their retention period');

Schedule::command('exports:prune')->dailyAt('03:00');

if (config('exports.schedule_queue_worker')) {
    // Shared hosting (Hostinger): process queued exports from the cron-driven scheduler.
    Schedule::command('queue:work --stop-when-empty --tries=1 --timeout=900 --max-time=55')
        ->everyMinute()
        ->withoutOverlapping(15);
}
