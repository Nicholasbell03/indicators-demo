<?php

namespace Database\Factories;

use App\Models\IndicatorSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorSubmissionAttachment>
 */
class IndicatorSubmissionAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_submission_id' => IndicatorSubmission::factory(),
            'file_path' => $this->faker->filePath(),
        ];
    }
}
