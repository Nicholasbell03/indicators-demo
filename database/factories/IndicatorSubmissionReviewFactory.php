<?php

namespace Database\Factories;

use App\Models\IndicatorReviewTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorSubmissionReview>
 */
class IndicatorSubmissionReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reviewTask = IndicatorReviewTask::factory()->create();

        return [
            'indicator_review_task_id' => $reviewTask->id,
            'indicator_submission_id' => $reviewTask->indicatorSubmission->id,
            'reviewer_id' => $reviewTask->verifierUser->id,
            'approved' => $this->faker->boolean(),
            'verifier_level' => $reviewTask->verifierLevel,
            'comment' => $this->faker->sentence(),
        ];
    }

    public function approved(): Factory
    {
        return $this->state(function (array $attributes) {
            return ['approved' => true];
        });
    }

    public function rejected(): Factory
    {
        return $this->state(function (array $attributes) {
            return ['approved' => false];
        });
    }

    public function verifierLevel(int $level): Factory
    {
        return $this->state(function (array $attributes) use ($level) {
            return ['verifier_level' => $level];
        });
    }
}
