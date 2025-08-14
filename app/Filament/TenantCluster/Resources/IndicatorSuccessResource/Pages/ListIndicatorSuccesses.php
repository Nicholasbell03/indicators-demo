<?php

namespace App\Filament\TenantCluster\Resources\IndicatorSuccessResource\Pages;

use App\Filament\TenantCluster\Resources\IndicatorSuccessResource;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages\ListIndicatorSuccesses as PortfolioListIndicatorSuccesses;

// Note this extends the list page from the Portfolio Panel Indicator Success Resource
class ListIndicatorSuccesses extends PortfolioListIndicatorSuccesses
{
    protected static string $resource = IndicatorSuccessResource::class;

    // All logic is in the parent class
}
