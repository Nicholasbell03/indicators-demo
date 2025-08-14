<?php

namespace App\Filament\TenantCluster\Resources\IndicatorComplianceResource\Pages;

use App\Enums\IndicatorLevelEnum;
use App\Filament\TenantCluster\Resources\IndicatorComplianceResource;
use App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource\Pages\ViewIndicatorCompliance as PortfolioViewIndicatorCompliance;

// Note this extends the view page from the Portfolio Panel Indicator Compliance Resource
class ViewIndicatorCompliance extends PortfolioViewIndicatorCompliance
{
    protected static string $resource = IndicatorComplianceResource::class;

    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::ESO;

    // All logic is in the parent class
}
