<?php

namespace Database\Factories;

use App\Enums\IndicatorProgrammeStatusEnum;
use App\Models\IndicatorSuccess;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorSuccessProgramme>
 */
class IndicatorSuccessProgrammeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_success_id' => IndicatorSuccess::factory(),
            'programme_id' => Programme::factory(),
            'status' => IndicatorProgrammeStatusEnum::PUBLISHED->value,
        ];
    }
}
