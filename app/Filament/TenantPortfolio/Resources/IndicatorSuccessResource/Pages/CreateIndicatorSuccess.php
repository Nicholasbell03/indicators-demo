<?php

namespace App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages;

use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorResponseFormatEnum;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIndicatorSuccess extends CreateRecord
{
    protected static string $resource = IndicatorSuccessResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Create Success Indicator';

    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::PORTFOLIO;

    protected static function getLevel(): IndicatorLevelEnum
    {
        return static::$level;
    }

    protected static function isLevel(IndicatorLevelEnum $level): bool
    {
        return static::$level === $level;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', [
            'record' => $this->getRecord(),
            'activeRelationManager' => 0,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle boolean response format
        if ($data['response_format'] === IndicatorResponseFormatEnum::BOOLEAN->value) {
            // For boolean, set acceptance_value based on the toggle and clear target_value
            $data['acceptance_value'] = isset($data['boolean_acceptance_value']) && $data['boolean_acceptance_value'] ? '1' : '0';
            $data['target_value'] = null;
        }

        // Remove the temporary boolean field
        unset($data['boolean_acceptance_value']);

        $data = $this->setPortfolioOrClusterId($data);
        $data['level'] = static::getLevel()->value;

        return $data;
    }

    private function setPortfolioOrClusterId(array $data): array
    {
        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if (! $currentTenant) {
            throw new \Exception('Current tenant not found');
        }

        if (! $currentTenant->portfolio() && ! $currentTenant->cluster()) {
            throw new \Exception('Current tenant portfolio or cluster not found');
        }

        if (static::isLevel(IndicatorLevelEnum::PORTFOLIO)) {
            $data['tenant_portfolio_id'] = $currentTenant->portfolio()?->id;
        }
        if (static::isLevel(IndicatorLevelEnum::ESO)) {
            $data['tenant_cluster_id'] = $currentTenant->cluster?->id;
        }

        return $data;
    }
}
