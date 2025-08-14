<?php

namespace App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages;

use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListIndicatorSuccesses extends ListRecords
{
    protected static string $resource = IndicatorSuccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Success Indicator'),
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
