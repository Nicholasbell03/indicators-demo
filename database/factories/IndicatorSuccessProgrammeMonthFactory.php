<?php

namespace Database\Factories;

use App\Models\IndicatorSuccessProgramme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorSuccessProgrammeMonth>
 */
class IndicatorSuccessProgrammeMonthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_success_programme_id' => IndicatorSuccessProgramme::factory(),
            'programme_month' => $this->faker->numberBetween(1, 12),
        ];
    }
}
