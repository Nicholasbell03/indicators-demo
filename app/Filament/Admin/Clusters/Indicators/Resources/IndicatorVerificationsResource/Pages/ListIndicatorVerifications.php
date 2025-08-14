<?php

namespace App\Filament\Admin\Clusters\Indicators\Resources\IndicatorVerificationsResource\Pages;

use App\Filament\Admin\Clusters\Indicators\Resources\IndicatorVerificationsResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListIndicatorVerifications extends ListRecords
{
    protected static string $resource = IndicatorVerificationsResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->pending()
                )
                ->badge(fn () => $this->getPendingCount()),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->complete()
                )
                ->badge(fn () => $this->getCompletedCount()),
        ];
    }

    protected function getPendingCount(): int
    {
        return $this->getResource()::getEloquentQuery()
            ->pending()
            ->count();
    }

    protected function getCompletedCount(): int
    {
        return $this->getResource()::getEloquentQuery()
            ->complete()
            ->count();
    }
}
