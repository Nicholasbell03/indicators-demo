<?php

namespace App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\RelationManagers;

use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorProgrammeStatusEnum;
use App\Models\IndicatorSuccessProgramme;
use App\Models\Programme;
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

class ProgrammesRelationManager extends RelationManager
{
    protected static string $relationship = 'programmes';

    protected static ?string $title = 'Associated Programmes';

    protected function getLevel(): IndicatorLevelEnum
    {
        return $this->getOwnerRecord()->level;
    }

    protected function isLevel(IndicatorLevelEnum $level): bool
    {
        return $this->getLevel() === $level;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->programmes->count();
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
            ->modifyQueryUsing(function (Builder $query) {
                if ($this->isLevel(IndicatorLevelEnum::PORTFOLIO)) {
                    return $query->tenantsOfCurrentTenantPortfolio();
                } else {
                    return $query->tenantsOfCurrentTenantCluster();
                }
            })
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->tooltip('The programme ID')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('programmes.id', $direction);
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('programmes.id', 'like', '%'.$search.'%');
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Programme')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Duration (months)')
                    ->wrapHeader(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => Str::title($state))
                    ->tooltip(fn (string $state): string => match ($state) {
                        IndicatorProgrammeStatusEnum::PENDING->value => 'This programme is pending approval',
                        IndicatorProgrammeStatusEnum::PUBLISHED->value => 'This programme is published and can\'t be unpublished',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        IndicatorProgrammeStatusEnum::PENDING->value => 'warning',
                        IndicatorProgrammeStatusEnum::PUBLISHED->value => 'success',
                    }),
                Tables\Columns\TextColumn::make('months')
                    ->label('Collection Months')
                    ->wrapHeader()
                    ->wrap()
                    ->getStateUsing(function (Programme $record) {
                        $pivotRecord = IndicatorSuccessProgramme::where('indicator_success_id', $this->getOwnerRecord()->id)
                            ->where('programme_id', $record->id)
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
                    ->label('Assign to Programme')
                    ->color('primary')
                    ->preloadRecordSelect()
                    ->attachAnother(false)
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->live(),
                        Forms\Components\Hidden::make('status')
                            ->default(IndicatorProgrammeStatusEnum::PENDING->value),
                        ...$this->getMonthSelectionFormComponents(isForAttach: true),
                    ])
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        if ($this->isLevel(IndicatorLevelEnum::PORTFOLIO)) {
                            return $query->tenantsOfCurrentTenantPortfolio();
                        } else {
                            return $query->tenantsOfCurrentTenantCluster();
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
                    ->visible(fn (Programme $record) => $record->pivot?->status === IndicatorProgrammeStatusEnum::PENDING->value)
                    ->requiresConfirmation()
                    ->modalHeading('Publish Programme')
                    ->modalDescription('Are you sure you want to publish this programme? Once published, it cannot be undone.')
                    ->action(function (Programme $record) {

                        $record->pivot->status = IndicatorProgrammeStatusEnum::PUBLISHED->value;
                        $record->pivot->save();

                        Notification::make()
                            ->title('Success')
                            ->body('Programme published successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make('edit_months')
                    ->label('Edit')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->modalHeading('Update Collection Months')
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->form(fn (Programme $record) => $this->getMonthSelectionFormComponents(isForAttach: false, programme: $record))
                    ->fillForm(function (Programme $record) {
                        $pivotRecord = IndicatorSuccessProgramme::where('indicator_success_id', $this->getOwnerRecord()->id)
                            ->where('programme_id', $record->id)
                            ->first();
                        $currentMonths = $pivotRecord->months()->pluck('programme_month')->toArray();

                        return [
                            'specific_months' => $currentMonths,
                        ];
                    })
                    ->action(function (array $data, Programme $record) {
                        $this->handleMonthsUpdate($record->id, $data['specific_months'] ?? []);

                        Notification::make()
                            ->title('Success')
                            ->body('Collection months updated successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DetachAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Remove Programme')
                    ->modalDescription('This action will remove the success indicator from the programme.'),
            ]);
    }

    private function getMonthSelectionFormComponents(bool $isForAttach = false, ?Programme $programme = null): array
    {
        return [
            Forms\Components\CheckboxList::make('specific_months')
                ->label('Select the months for data collection')
                ->options(function (Get $get) use ($isForAttach, $programme) {
                    if ($isForAttach) {
                        $programmeId = $get('recordId');
                        if (! $programmeId) {
                            return [];
                        }
                        $programme = Programme::find($programmeId);
                    }

                    if (! $programme) {
                        return [];
                    }

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
                    ->action(function (Set $set, Get $get) use ($isForAttach, $programme) {
                        if ($isForAttach) {
                            $programmeId = $get('recordId');
                            if (! $programmeId) {
                                return;
                            }
                            $programme = Programme::find($programmeId);
                        }

                        if (! $programme) {
                            return;
                        }

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

    private function handleMonthsUpdate(int $programmeId, array $selectedMonths): void
    {
        // Find the pivot record
        $pivotRecord = IndicatorSuccessProgramme::where('indicator_success_id', $this->getOwnerRecord()->id)
            ->where('programme_id', $programmeId)
            ->first();

        if (! $pivotRecord) {
            Notification::make()
                ->title('Error')
                ->body('Failed to find programme relationship')
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
}
