<?php

namespace App\Filament\Admin\Clusters\Indicators\Resources\IndicatorSubmissionsResource\Pages;

use App\Filament\Admin\Clusters\Indicators\Resources\IndicatorSubmissionsResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListIndicatorSubmissions extends ListRecords
{
    protected static string $resource = IndicatorSubmissionsResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->pending()
                )
                ->badge(fn () => $this->getPendingCount()),

            'in_verification' => Tab::make('In Verification')
                ->modifyQueryUsing(fn (Builder $query) => $query->inVerification()
                )
                ->badge(fn () => $this->getInVerificationCount()),

            'complete' => Tab::make('Complete')
                ->modifyQueryUsing(fn (Builder $query) => $query->complete()
                )
                ->badge(fn () => $this->getCompleteCount()),
            'all' => Tab::make('All')
                ->badge(fn () => $this->getAllCount()),
        ];
    }

    protected function getPendingCount(): int
    {
        return $this->getResource()::getEloquentQuery()
            ->pending()
            ->count();
    }

    protected function getInVerificationCount(): int
    {
        return $this->getResource()::getEloquentQuery()
            ->inVerification()
            ->count();
    }

    protected function getCompleteCount(): int
    {
        return $this->getResource()::getEloquentQuery()
            ->complete()
            ->count();
    }

    protected function getAllCount(): int
    {
        return $this->getResource()::getEloquentQuery()->count();
    }
}
