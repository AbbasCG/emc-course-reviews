<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExportLog extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_EXPIRED    = 'expired';

    public const REPORT_TYPES = [
        'detailed'     => 'تصدير تفصيلي',
        'summary'      => 'تقرير ملخص',
        'professional' => 'تقرير PDF احترافي',
        'campaign'     => 'تقرير حملة',
    ];

    public const FORMATS = [
        'xlsx' => 'Excel',
        'csv'  => 'CSV',
        'pdf'  => 'PDF',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'report_type',
        'format',
        'status',
        'options',
        'date_from',
        'date_to',
        'record_count',
        'file_path',
        'file_size',
        'error',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'options'      => 'array',
        'date_from'    => 'date',
        'date_to'      => 'date',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->file_path
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && Storage::disk(config('exports.disk'))->exists($this->file_path);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_EXPIRED], true);
    }

    public function reportTypeLabel(): string
    {
        return self::REPORT_TYPES[$this->report_type] ?? $this->report_type;
    }

    public function formatLabel(): string
    {
        return self::FORMATS[$this->format] ?? strtoupper($this->format);
    }

    public function statusBadge(): array
    {
        return match ($this->status) {
            self::STATUS_COMPLETED  => ['label' => 'مكتمل', 'tone' => 'ok'],
            self::STATUS_PROCESSING => ['label' => 'قيد المعالجة', 'tone' => 'info'],
            self::STATUS_PENDING    => ['label' => 'في الانتظار', 'tone' => 'info'],
            self::STATUS_FAILED     => ['label' => 'فشل', 'tone' => 'bad'],
            self::STATUS_EXPIRED    => ['label' => 'منتهي الصلاحية', 'tone' => 'muted'],
            default                 => ['label' => $this->status, 'tone' => 'muted'],
        };
    }

    public function periodLabel(): string
    {
        if (!$this->date_from && !$this->date_to) {
            return 'كل الفترات';
        }

        return ($this->date_from?->format('Y-m-d') ?? '…') . ' → ' . ($this->date_to?->format('Y-m-d') ?? '…');
    }

    public function downloadName(): string
    {
        $base = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', '-', $this->name));
        $date = $this->created_at->format('Y-m-d');

        return (str_contains($base, $date) ? $base : $base . '_' . $date) . '.' . $this->format;
    }
}
