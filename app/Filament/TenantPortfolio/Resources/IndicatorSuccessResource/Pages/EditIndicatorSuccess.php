<?php

namespace App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages;

use App\Enums\IndicatorResponseFormatEnum;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditIndicatorSuccess extends EditRecord
{
    protected static string $resource = IndicatorSuccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // TODO: add permission check
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle boolean response format
        if ($data['response_format'] === IndicatorResponseFormatEnum::BOOLEAN->value) {
            // For boolean, set acceptance_value based on the toggle and clear target_value
            $data['acceptance_value'] = isset($data['boolean_acceptance_value']) && $data['boolean_acceptance_value'] ? '1' : '0';
            $data['target_value'] = null;
        }

        // Remove the temporary boolean field
        unset($data['boolean_acceptance_value']);

        return $data;
    }
}
