<?php

namespace Database\Factories;

use App\Models\ReviewCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReviewCampaign>
 */
class ReviewCampaignFactory extends Factory
{
    protected $model = ReviewCampaign::class;

    public function definition(): array
    {
        return [
            'course_name'     => 'دورة ' . fake()->unique()->words(2, true),
            'instructor_name' => 'د. ' . fake()->firstName(),
            'activity_type'   => 'course',
            'course_date'     => now()->subDays(10)->toDateString(),
            'description'     => null,
            'token'           => Str::random(32),
            'is_active'       => true,
            'expires_at'      => null,
            'theme'           => 'default',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
