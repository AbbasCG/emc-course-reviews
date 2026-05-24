<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewCampaign extends Model
{
    protected $fillable = [
        'course_name',
        'instructor_name',
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
