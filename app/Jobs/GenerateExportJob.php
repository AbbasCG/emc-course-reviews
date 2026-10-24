<?php

namespace App\Jobs;

use App\Exports\ExportService;
use App\Models\ExportLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class GenerateExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $exportLogId) {}

    public function handle(ExportService $service): void
    {
        $log = ExportLog::find($this->exportLogId);
        if (!$log || $log->status !== ExportLog::STATUS_PENDING) {
            return;
        }

        $service->run($log);
    }

    public function failed(?Throwable $e): void
    {
        ExportLog::whereKey($this->exportLogId)
            ->whereIn('status', [ExportLog::STATUS_PENDING, ExportLog::STATUS_PROCESSING])
            ->update([
                'status'       => ExportLog::STATUS_FAILED,
                'error'        => Str::limit($e?->getMessage() ?? 'Job failed', 500),
                'completed_at' => now(),
            ]);
    }
}
