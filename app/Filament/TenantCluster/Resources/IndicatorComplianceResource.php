<?php

namespace App\Filament\TenantCluster\Resources;

use App\Enums\IndicatorLevelEnum;
use App\Filament\TenantCluster\Resources\IndicatorComplianceResource\Pages;
use App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource as PortfolioIndicatorComplianceResource;

class IndicatorComplianceResource extends PortfolioIndicatorComplianceResource
{
    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::ESO;

    // All logic is in the parent class

    // This uses the relation managers from the PortfolioIndicatorComplianceResource class

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndicatorCompliances::route('/'),
            'create' => Pages\CreateIndicatorCompliance::route('/create'),
            'edit' => Pages\EditIndicatorCompliance::route('/{record}/edit'),
            'view' => Pages\ViewIndicatorCompliance::route('/{record}'),
        ];
    }
}
