<?php

namespace App\Filament\TenantCluster\Resources\IndicatorComplianceResource\Pages;

use App\Filament\TenantCluster\Resources\IndicatorComplianceResource;
use App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource\Pages\ListIndicatorCompliances as PortfolioListIndicatorCompliances;

// Note this extends the list page from the Portfolio Panel Indicator Compliance Resource
class ListIndicatorCompliances extends PortfolioListIndicatorCompliances
{
    protected static string $resource = IndicatorComplianceResource::class;

    // All logic is in the parent class
}
