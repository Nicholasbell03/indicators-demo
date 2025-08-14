<?php

namespace App\Filament\TenantPortfolio\Resources\ProgrammeResource\RelationManagers;

use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorProgrammeStatusEnum;
use App\Models\IndicatorSuccess;
use App\Models\IndicatorSuccessProgramme;
use App\Models\Programme;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndicatorSuccessRelationManager extends RelationManager
{
    protected static string $relationship = 'indicatorSuccesses';

    protected static ?string $title = 'Success Indicators';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->indicatorSuccesses->count();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Success Indicator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => Str::title($state))
                    ->tooltip(fn (string $state): string => match ($state) {
                        IndicatorProgrammeStatusEnum::PENDING->value => 'This success indicator is pending approval',
                        IndicatorProgrammeStatusEnum::PUBLISHED->value => 'This success indicator is published and can\'t be unpublished',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        IndicatorProgrammeStatusEnum::PENDING->value => 'warning',
                        IndicatorProgrammeStatusEnum::PUBLISHED->value => 'success',
                    }),
                Tables\Columns\TextColumn::make('level')
                    ->label('Set By')
                    ->badge()
                    ->formatStateUsing(fn (IndicatorLevelEnum $state) => $state->label())
                    ->tooltip(fn (IndicatorLevelEnum $state): ?string => match ($state) {
                        IndicatorLevelEnum::PORTFOLIO => 'This is a portfolio level indicator',
                        IndicatorLevelEnum::ESO => 'This is a ESO level indicator',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('months')
                    ->label('Collection Months')
                    ->wrapHeader()
                    ->wrap()
                    ->getStateUsing(function (IndicatorSuccess $record) {
                        $pivotRecord = IndicatorSuccessProgramme::where('indicator_success_id', $record->id)
                            ->where('programme_id', $this->getOwnerRecord()->id)
                            ->first();

                        if (! $pivotRecord) {
                            return [];
                        }

                        return $pivotRecord->months()->pluck('programme_month')->toArray();
                    })
                    ->separator(', '),
            ])
            ->defaultSort('title', 'asc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Attach Success Indicator')
                    ->color('primary')
                    ->preloadRecordSelect()
                    ->attachAnother(false)
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->live(),
                        Forms\Components\Hidden::make('status')
                            ->default(IndicatorProgrammeStatusEnum::PENDING->value),
                        Forms\Components\Actions::make([
                            $this->getViewIndicatorAction(isForAttach: true),
                        ])
                            ->alignStart()
                            ->visible(fn (Get $get) => (bool) $get('recordId')),
                        ...$this->getMonthSelectionFormComponents(isForAttach: true),
                    ])
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        $currentPanel = Filament::getCurrentPanel()->getId();

                        if ($currentPanel === 'tenantPortfolio') {
                            return $query->ofCurrentTenantPortfolio();
                        } else {
                            return $query->ofCurrentTenantCluster();
                        }
                    })
                    ->after(function (array $data) {
                        $this->handleMonthsUpdate($data['recordId'], $data['specific_months'] ?? []);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make('publish')
                    ->label('Publish')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(function (IndicatorSuccess $record) {
                        $currentPanel = Filament::getCurrentPanel()->getId();

                        if ($record->pivot?->status !== IndicatorProgrammeStatusEnum::PENDING->value) {
                            return false;
                        }

                        if ($currentPanel === 'tenantPortfolio') {
                            return $record->level === IndicatorLevelEnum::PORTFOLIO;
                        } else {
                            return $record->level === IndicatorLevelEnum::ESO;
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Publish Success Indicator')
                    ->modalDescription('Are you sure you want to publish this success indicator? Once published, it cannot be undone.')
                    ->action(function (IndicatorSuccess $record) {
                        $pivotRecord = IndicatorSuccessProgramme::where('indicator_success_id', $record->id)
                            ->where('programme_id', $this->getOwnerRecord()->id)
                            ->first();
                        $pivotRecord->status = IndicatorProgrammeStatusEnum::PUBLISHED->value;
                        $pivotRecord->save();

                        Notification::make()
                            ->title('Success')
                            ->body('Success indicator published successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make('edit_months')
                    ->label('Edit')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->modalHeading('Update Collection Months')
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->visible(function (IndicatorSuccess $record) {
                        $currentPanel = Filament::getCurrentPanel()->getId();

                        if ($currentPanel === 'tenantPortfolio') {
                            return $record->level === IndicatorLevelEnum::PORTFOLIO;
                        } else {
                            return $record->level === IndicatorLevelEnum::ESO;
                        }
                    })
                    ->form(fn (IndicatorSuccess $record) => [
                        Forms\Components\Actions::make([
                            $this->getViewIndicatorAction(isForAttach: false, record: $record),
                        ])
                            ->alignStart(),
                        ...$this->getMonthSelectionFormComponents(isForAttach: false, indicatorSuccess: $record),
                    ])
                    ->fillForm(function (IndicatorSuccess $record) {
                        $pivotRecord = IndicatorSuccessProgramme::where('indicator_success_id', $record->id)
                            ->where('programme_id', $this->getOwnerRecord()->id)
                            ->first();
                        $currentMonths = $pivotRecord->months()->pluck('programme_month')->toArray();

                        return [
                            'specific_months' => $currentMonths,
                        ];
                    })
                    ->action(function (array $data, IndicatorSuccess $record) {
                        $this->handleMonthsUpdate($record->id, $data['specific_months'] ?? []);

                        Notification::make()
                            ->title('Success')
                            ->body('Collection months updated successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DetachAction::make()
                    ->requiresConfirmation()
                    ->visible(function (IndicatorSuccess $record) {
                        $currentPanel = Filament::getCurrentPanel()->getId();

                        if ($currentPanel === 'tenantPortfolio') {
                            return $record->level === IndicatorLevelEnum::PORTFOLIO;
                        } else {
                            return $record->level === IndicatorLevelEnum::ESO;
                        }
                    })
                    ->modalHeading('Remove Success Indicator')
                    ->modalDescription('This action will remove the success indicator from the programme.'),
            ]);
    }

    private function getMonthSelectionFormComponents(bool $isForAttach = false, ?IndicatorSuccess $indicatorSuccess = null): array
    {
        return [
            Forms\Components\CheckboxList::make('specific_months')
                ->label('Select the months for data collection')
                ->options(function (Get $get) use ($isForAttach) {
                    if ($isForAttach) {
                        $recordId = $get('recordId');
                        if (! $recordId) {
                            return [];
                        }
                    }

                    $programme = $this->getOwnerRecord();

                    return $this->getMonthOptions($programme);
                })
                ->columns(3)
                ->gridDirection('row')
                ->required()
                ->visible(function (Get $get) use ($isForAttach) {
                    return $isForAttach ? ($get('recordId') ?? false) : true;
                })
                ->reactive(),

            Actions::make([
                Action::make('select_all_months')
                    ->label('Select all')
                    ->link()
                    ->action(function (Set $set) {
                        $programme = $this->getOwnerRecord();
                        $duration = $programme->period ?? 12;
                        $set('specific_months', range(1, $duration));
                    }),
                Action::make('deselect_all_months')
                    ->label('Deselect all')
                    ->link()
                    ->action(function (Set $set) {
                        $set('specific_months', []);
                    }),
            ])
                ->visible(function (Get $get) use ($isForAttach) {
                    return $isForAttach ? ($get('recordId') ?? false) : true;
                })
                ->alignEnd(),
        ];
    }

    private function getMonthOptions(Programme $programme): array
    {
        $duration = $programme->period ?? 12;

        return array_reduce(range(1, $duration), function ($carry, $month) {
            $carry[$month] = "Month {$month}";

            return $carry;
        }, []);
    }

    private function handleMonthsUpdate(int $indicatorSuccessId, array $selectedMonths): void
    {
        // Find the pivot record (note the parameter order is swapped from ProgrammesRelationManager)
        $pivotRecord = IndicatorSuccessProgramme::where('indicator_success_id', $indicatorSuccessId)
            ->where('programme_id', $this->getOwnerRecord()->id)
            ->first();

        if (! $pivotRecord) {
            Notification::make()
                ->title('Error')
                ->body('Failed to find success indicator relationship')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($pivotRecord, $selectedMonths) {
            // Clear existing months
            $pivotRecord->months()->delete();

            // Create new months
            if (! empty($selectedMonths)) {
                $monthsToCreate = [];
                foreach ($selectedMonths as $month) {
                    $monthsToCreate[] = ['programme_month' => $month];
                }
                $pivotRecord->months()->createMany($monthsToCreate);
            }
        });
    }

    private function getViewIndicatorAction(bool $isForAttach = false, ?IndicatorSuccess $record = null): Action
    {
        return Action::make('view_indicator')
            ->label('View Indicator Details')
            ->icon('heroicon-o-eye')
            ->badge()
            ->color('gray')
            ->url(function (Get $get) use ($isForAttach, $record) {
                if ($isForAttach) {
                    $recordId = $get('recordId');
                    if (! $recordId) {
                        return null;
                    }

                    return $this->getIndicatorSuccessResourceClass()::getUrl('view', ['record' => $recordId]);
                } else {
                    return $this->getIndicatorSuccessResourceClass()::getUrl('view', ['record' => $record->id]);
                }
            })
            ->openUrlInNewTab()
            ->visible(function (Get $get) use ($isForAttach) {
                return $isForAttach ? (bool) $get('recordId') : true;
            });
    }

    /**
     * Determine which IndicatorComplianceResource to use based on current panel context
     */
    private function getIndicatorSuccessResourceClass(): string
    {
        // Get the current Livewire component to determine the panel context
        $currentPanel = Filament::getCurrentPanel()->getId();

        // Check if we're in a TenantPortfolio context by looking at the panel id
        if ($currentPanel === 'tenantPortfolio') {
            return \App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource::class;
        }

        // Default to TenantCluster (as it's the least permission required)
        return \App\Filament\TenantCluster\Resources\IndicatorSuccessResource::class;
    }
}
