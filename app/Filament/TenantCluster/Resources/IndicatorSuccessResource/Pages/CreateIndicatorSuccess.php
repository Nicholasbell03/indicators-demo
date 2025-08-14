<?php

namespace App\Filament\TenantCluster\Resources\IndicatorSuccessResource\Pages;

use App\Enums\IndicatorLevelEnum;
use App\Filament\TenantCluster\Resources\IndicatorSuccessResource;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages\CreateIndicatorSuccess as PortfolioCreateIndicatorSuccess;

// Note this extends the create page from the Portfolio Panel Indicator Success Resource
class CreateIndicatorSuccess extends PortfolioCreateIndicatorSuccess
{
    protected static string $resource = IndicatorSuccessResource::class;

    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::ESO;

    // All logic is in the parent class
}
