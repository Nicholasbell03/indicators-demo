<?php

namespace App\Filament\TenantCluster\Resources\IndicatorComplianceResource\Pages;

use App\Enums\IndicatorLevelEnum;
use App\Filament\TenantCluster\Resources\IndicatorComplianceResource;
use App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource\Pages\CreateIndicatorCompliance as PortfolioCreateIndicatorCompliance;

// Note this extends the create page from the Portfolio Panel Indicator Compliance Resource
class CreateIndicatorCompliance extends PortfolioCreateIndicatorCompliance
{
    protected static string $resource = IndicatorComplianceResource::class;

    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::ESO;

    // All logic is in the parent class
}
