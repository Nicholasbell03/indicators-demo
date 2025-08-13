<?php

namespace Database\Factories;

use App\Enums\IndicatorTaskStatusEnum;
use App\Models\IndicatorComplianceProgrammeMonth;
use App\Models\IndicatorSuccessProgrammeMonth;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndicatorTaskFactory extends Factory
{
    public function definition(): array
    {
        $indicatableMonth = IndicatorSuccessProgrammeMonth::factory()->create();
        $indicatableMonth->load('indicatorSuccessProgramme');
        $entrepreneur = User::factory()->create();
        $organisation = Organisation::factory()->create();
        $entrepreneur->organisations()->attach($organisation);

        return [
            'entrepreneur_id' => $entrepreneur->id,
            'organisation_id' => $organisation->id,
            'programme_id' => $indicatableMonth->indicatorSuccessProgramme?->programme_id ?? throw new \Exception('IndicatorSuccessProgramme relationship not found'),
            'indicatable_month_id' => $indicatableMonth->id,
            'indicatable_month_type' => $indicatableMonth->getMorphClass(),
            'responsible_type' => 'user',
            'responsible_role_id' => Role::where('name', 'Entrepreneur')->first()?->id ?? Role::first()?->id,
            'responsible_user_id' => $entrepreneur->id,
            'due_date' => $this->faker->dateTimeBetween('+1 week', '+1 month'),
            'status' => $this->faker->randomElement(IndicatorTaskStatusEnum::databaseTypes())->value,
            'indicatable_type' => $indicatableMonth->indicator->getMorphClass(),
            'indicatable_id' => $indicatableMonth->indicator->id,
        ];
    }

    public function forSuccess(?IndicatorSuccessProgrammeMonth $indicatableMonth = null): self
    {
        return $this->state(function () use ($indicatableMonth) {
            $indicatableMonth = $indicatableMonth ?? IndicatorSuccessProgrammeMonth::factory()->create();
            $indicatableMonth->load('indicatorSuccessProgramme');

            return [
                'indicatable_month_id' => $indicatableMonth->id,
                'indicatable_month_type' => $indicatableMonth->getMorphClass(),
                'programme_id' => $indicatableMonth->indicatorSuccessProgramme?->programme_id ?? throw new \Exception('IndicatorSuccessProgramme relationship not found'),
                'indicatable_type' => $indicatableMonth->indicator->getMorphClass(),
                'indicatable_id' => $indicatableMonth->indicator->id,
            ];
        });
    }

    public function forCompliance(?IndicatorComplianceProgrammeMonth $indicatableMonth = null): self
    {
        return $this->state(function () use ($indicatableMonth) {
            $indicatableMonth = $indicatableMonth ?? IndicatorComplianceProgrammeMonth::factory()->create();
            $indicatableMonth->load('indicatorComplianceProgramme');

            return [
                'indicatable_month_id' => $indicatableMonth->id,
                'indicatable_month_type' => $indicatableMonth->getMorphClass(),
                'programme_id' => $indicatableMonth->indicatorComplianceProgramme?->programme_id ?? throw new \Exception('IndicatorComplianceProgramme relationship not found'),
                'indicatable_type' => $indicatableMonth->indicator->getMorphClass(),
                'indicatable_id' => $indicatableMonth->indicator->id,
            ];
        });
    }

    public function forUser(User $user): self
    {
        return $this->state(function () use ($user) {
            return [
                'entrepreneur_id' => $user->id,
            ];
        });
    }

    public function forOrganisation(Organisation $organisation): self
    {
        return $this->state(function () use ($organisation) {
            return [
                'organisation_id' => $organisation->id,
            ];
        });
    }

    public function systemType(): self
    {
        return $this->state(function () {
            return [
                'responsible_type' => 'system',
                'responsible_role_id' => null,
                'responsible_user_id' => null,
            ];
        });
    }

    public function status(IndicatorTaskStatusEnum $status): self
    {
        return $this->state(function () use ($status) {
            return ['status' => $status->value];
        });
    }
}
