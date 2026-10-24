<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewCampaign extends Model
{
    use HasFactory;

    /** Built-in activity types; any other stored value is a custom type. */
    public const ACTIVITY_TYPES = [
        'course'   => 'دورة تدريبية',
        'workshop' => 'ورشة عمل',
        'program'  => 'برنامج',
    ];

    public const STATUSES = [
        'active'   => 'نشطة',
        'inactive' => 'متوقفة',
        'expired'  => 'منتهية',
    ];

    protected $fillable = [
        'course_name',
        'instructor_name',
        'activity_type',
        'course_date',
        'description',
        'token',
        'is_active',
        'expires_at',
        'theme',
    ];

    protected $casts = [
        'course_date' => 'date',
        'is_active'   => 'boolean',
        'expires_at'  => 'datetime',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAvailable(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function statusBadge(): array
    {
        if (!$this->is_active) {
            return ['label' => 'متوقفة', 'class' => 'bg-red-100 text-red-700'];
        }
        if ($this->isExpired()) {
            return ['label' => 'منتهية', 'class' => 'bg-slate-100 text-slate-600'];
        }
        if ($this->expires_at && $this->expires_at->isBefore(now()->addDays(3))) {
            return ['label' => 'تنتهي قريباً', 'class' => 'bg-amber-100 text-amber-700'];
        }
        return ['label' => 'نشطة', 'class' => 'bg-emerald-100 text-emerald-700'];
    }

    /** Status key using the same precedence as statusBadge(): inactive > expired > active. */
    public function statusKey(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        return $this->isExpired() ? 'expired' : 'active';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->statusKey()];
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active'   => $query->where('is_active', true)
                                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'inactive' => $query->where('is_active', false),
            'expired'  => $query->where('is_active', true)
                                ->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            default    => $query,
        };
    }

    public static function activityTypeLabelFor(?string $type): string
    {
        if ($type === null || $type === '') {
            return self::ACTIVITY_TYPES['course'];
        }

        return self::ACTIVITY_TYPES[$type] ?? $type;
    }

    public function activityTypeLabel(): string
    {
        return self::activityTypeLabelFor($this->activity_type);
    }

    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('overall_rating') ?? 0;
    }

    public function getReviewCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    public function getReviewLinkAttribute(): string
    {
        return route('review.show', $this->token);
    }
}
