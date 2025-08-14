<?php

namespace App\Filament\TenantPortfolio\Resources\ProgrammeResource\Pages;

use App\Filament\TenantPortfolio\Resources\ProgrammeResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditProgramme extends EditRecord
{
    protected static string $resource = ProgrammeResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title;
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
