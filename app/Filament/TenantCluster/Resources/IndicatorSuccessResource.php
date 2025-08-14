<?php

namespace App\Filament\TenantCluster\Resources;

use App\Enums\IndicatorLevelEnum;
use App\Filament\TenantCluster\Resources\IndicatorSuccessResource\Pages;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource as PortfolioIndicatorSuccessResource;

class IndicatorSuccessResource extends PortfolioIndicatorSuccessResource
{
    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::ESO;

    // All logic is in the parent class

    // This uses the relation managers from the PortfolioIndicatorSuccessResource class

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndicatorSuccesses::route('/'),
            'create' => Pages\CreateIndicatorSuccess::route('/create'),
            'edit' => Pages\EditIndicatorSuccess::route('/{record}/edit'),
            'view' => Pages\ViewIndicatorSuccess::route('/{record}'),
        ];
    }
}
