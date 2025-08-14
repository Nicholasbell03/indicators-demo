<?php

namespace App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource\Pages;

use App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListIndicatorCompliances extends ListRecords
{
    protected static string $resource = IndicatorComplianceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Compliance Indicator'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->withoutTrashed()),
            'archived' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed()),
        ];
    }
}
