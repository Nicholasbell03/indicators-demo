<?php

namespace App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages;

use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorResponseFormatEnum;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource;
use App\Models\IndicatorSuccess;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewIndicatorSuccess extends ViewRecord
{
    protected static string $resource = IndicatorSuccessResource::class;

    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::PORTFOLIO;

    protected static function getLevel(): IndicatorLevelEnum
    {
        return static::$level;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (IndicatorSuccess $record) => $record->level === static::getLevel()),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'View '.$this->getRecord()->title;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['requires_approval'] = $data['verifier_1_role_id'] !== null || $data['verifier_2_role_id'] !== null;
        $data['requires_supporting_documentation'] = $data['supporting_documentation'] !== null;

        // Handle boolean response format when filling the form
        if ($data['response_format'] === IndicatorResponseFormatEnum::BOOLEAN->value) {
            $data['boolean_acceptance_value'] = $data['acceptance_value'] === '1' || $data['acceptance_value'] === 'true';
        }

        return $data;
    }
}
