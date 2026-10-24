<?php

namespace Database\Factories;

use App\Models\CourseReview;
use App\Models\ReviewCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseReview>
 */
class CourseReviewFactory extends Factory
{
    protected $model = CourseReview::class;

    public function definition(): array
    {
        return [
            'review_campaign_id'    => ReviewCampaign::factory(),
            'student_name'          => 'طالب ' . fake()->firstName(),
            'email'                 => fake()->unique()->safeEmail(),
            'overall_rating'        => 4,
            'course_content_rating' => 4,
            'instructor_rating'     => 5,
            'organization_rating'   => 3,
            'difficulty_level'      => 'متوسط',
            'what_did_you_like'     => 'المحتوى ممتاز والمدرب متمكن من Excel و Power BI',
            'what_can_be_improved'  => 'زيادة عدد الساعات العملية',
            'would_recommend'       => true,
            'additional_notes'      => null,
        ];
    }
}
