<?php

namespace Database\Factories;

use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorResponseFormatEnum;
use App\Models\Role;
use App\Models\TenantCluster;
use App\Models\TenantPortfolio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorCompliance>
 */
class IndicatorComplianceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Randomly choose between portfolio or cluster, but not both
        $usePortfolio = $this->faker->boolean();

        // Get two different existing roles
        $roleIds = Role::pluck('id')->shuffle()->take(2);
        $responseFormat = $this->faker->randomElement(IndicatorResponseFormatEnum::cases());

        $title = $this->faker->sentence;

        return [
            'tenant_portfolio_id' => $usePortfolio ? TenantPortfolio::factory() : null,
            'tenant_cluster_id' => ! $usePortfolio ? TenantCluster::factory() : null,
            'level' => $this->faker->randomElement(IndicatorLevelEnum::cases()),
            'type' => $this->faker->randomElement(IndicatorComplianceTypeEnum::cases()),
            'responsible_role_id' => Role::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->faker->text,
            'additional_instruction' => $this->faker->text,
            'response_format' => $responseFormat,
            'target_value' => $responseFormat === IndicatorResponseFormatEnum::BOOLEAN ? $this->faker->randomElement([0, 1]) : $this->faker->numberBetween(80, 100),
            'acceptance_value' => $responseFormat === IndicatorResponseFormatEnum::BOOLEAN ? $this->faker->randomElement([0, 1]) : $this->faker->numberBetween(1, 79),
            'supporting_documentation' => $this->faker->text,
            'verifier_1_role_id' => $roleIds->first(),
            'verifier_2_role_id' => $roleIds->count() > 1 ? $roleIds->last() : $roleIds->first(),
        ];
    }

    /**
     * Create an indicator success for a tenant portfolio.
     */
    public function forPortfolio(?int $portfolioId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_portfolio_id' => $portfolioId ?? TenantPortfolio::factory(),
            'tenant_cluster_id' => null,
        ]);
    }

    /**
     * Create an indicator success for a tenant cluster.
     */
    public function forCluster(?int $clusterId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_portfolio_id' => null,
            'tenant_cluster_id' => $clusterId ?? TenantCluster::factory(),
        ]);
    }

    public function forType(IndicatorComplianceTypeEnum $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    public function forLevel(IndicatorLevelEnum $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    public function forResponseFormat(IndicatorResponseFormatEnum $responseFormat): static
    {
        return $this->state(fn (array $attributes) => [
            'response_format' => $responseFormat,
        ]);
    }
}
