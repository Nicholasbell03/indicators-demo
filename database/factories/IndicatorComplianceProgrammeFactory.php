<?php

namespace Database\Factories;

use App\Enums\IndicatorProgrammeStatusEnum;
use App\Models\IndicatorCompliance;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorComplianceProgramme>
 */
class IndicatorComplianceProgrammeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_compliance_id' => IndicatorCompliance::factory(),
            'programme_id' => Programme::factory(),
            'status' => IndicatorProgrammeStatusEnum::PUBLISHED->value,
        ];
    }
}
