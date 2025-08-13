<?php

namespace Database\Factories;

use App\Models\IndicatorComplianceProgramme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorComplianceProgrammeMonth>
 */
class IndicatorComplianceProgrammeMonthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_compliance_programme_id' => IndicatorComplianceProgramme::factory(),
            'programme_month' => $this->faker->numberBetween(1, 12),
            'target_value' => $this->faker->numberBetween(1, 100),
        ];
    }
}
