<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseReview extends Model
{
    use HasFactory;

    protected $table = 'course_reviews';

    protected $fillable = [
        'review_campaign_id',
        'student_name',
        'email',
        'overall_rating',
        'course_content_rating',
        'instructor_rating',
        'organization_rating',
        'difficulty_level',
        'what_did_you_like',
        'what_can_be_improved',
        'would_recommend',
        'additional_notes',
    ];

    protected $casts = [
        'would_recommend' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReviewCampaign::class, 'review_campaign_id');
    }
}
