<?php

namespace Database\Factories;

use App\Enums\IndicatorSubmissionStatusEnum;
use App\Models\IndicatorSubmission;
use App\Models\IndicatorTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndicatorSubmissionFactory extends Factory
{
    protected $model = IndicatorSubmission::class;

    public function definition(): array
    {
        return [
            // Default to a Success Indicator requirement
            'indicator_task_id' => IndicatorTask::factory(),
            'value' => $this->faker->randomFloat(2, 1000, 50000),
            'comment' => $this->faker->sentence(),
            'is_achieved' => $this->faker->boolean(),
            'status' => $this->faker->randomElement(IndicatorSubmissionStatusEnum::cases())->value,
            'submitter_id' => User::factory(),
            'submitted_at' => now(),
        ];
    }

    /**
     * Configure the submission as approved.
     */
    public function approved(): self
    {
        return $this->state(fn (array $attributes) => ['status' => IndicatorSubmissionStatusEnum::APPROVED->value]);
    }

    /**
     * Configure the submission as rejected.
     */
    public function rejected(): Factory
    {
        return $this->state(fn (array $attributes) => ['status' => IndicatorSubmissionStatusEnum::REJECTED->value]);
    }

    /**
     * Configure the submission as pending verification 1.
     */
    public function pendingVerification1(): Factory
    {
        return $this->state(fn (array $attributes) => ['status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1->value]);
    }

    /**
     * Configure the submission as pending verification 2.
     */
    public function pendingVerification2(): Factory
    {
        return $this->state(fn (array $attributes) => ['status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_2->value]);
    }
}
