<?php

namespace App\Filament\TenantCluster\Resources\ProgrammeResource\Pages;

use App\Filament\TenantCluster\Resources\ProgrammeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditProgramme extends EditRecord
{
    protected static string $resource = ProgrammeResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        $hasSeats = $this->record->programmeSeats()->exists();

        return [
            Actions\Action::make('registration')
                ->label('Registration')
                ->disabled(! $hasSeats)
                ->url(function () {
                    session()->forget('programme_users_'.$this->record->id);

                    return route('filament.tenantCluster.resources.programmes.registration', $this->record->id);
                })
                ->button()
                ->color('warning')
                ->tooltip(function () use ($hasSeats) {
                    if (! $hasSeats) {
                        return 'No programme seats available';
                    }

                    return 'Manage programme registrations';
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
