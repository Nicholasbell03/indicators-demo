<?php

namespace App\Filament\TenantCluster\Resources\IndicatorComplianceResource\Pages;

use App\Filament\TenantCluster\Resources\IndicatorComplianceResource;
use App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource\Pages\EditIndicatorCompliance as PortfolioEditIndicatorCompliance;

// Note this extends the edit page from the Portfolio Panel Indicator Compliance Resource
class EditIndicatorCompliance extends PortfolioEditIndicatorCompliance
{
    protected static string $resource = IndicatorComplianceResource::class;

    // All logic is in the parent class
}
