<?php

namespace App\Filament\TenantCluster\Resources\IndicatorSuccessResource\Pages;

use App\Filament\TenantCluster\Resources\IndicatorSuccessResource;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages\EditIndicatorSuccess as PortfolioEditIndicatorSuccess;

// Note this extends the edit page from the Portfolio Panel Indicator Success Resource
class EditIndicatorSuccess extends PortfolioEditIndicatorSuccess
{
    protected static string $resource = IndicatorSuccessResource::class;

    // All logic is in the parent class
}
