<?php

namespace App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource\RelationManagers;

use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorProgrammeStatusEnum;
use App\Models\IndicatorComplianceProgramme;
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
                    ->label(function () {
                        $complianceType = $this->getOwnerRecord()->type;

                        return match ($complianceType) {
                            IndicatorComplianceTypeEnum::ELEMENT_PROGRESS => 'Collection Months/Targets',
                            IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING => 'Target %',
                            default => 'Collection Months'
                        };
                    })
                    ->wrapHeader()
                    ->wrap()
                    ->getStateUsing(function (Programme $record) {
                        $pivotRecord = IndicatorComplianceProgramme::where('indicator_compliance_id', $this->getOwnerRecord()->id)
                            ->where('programme_id', $record->id)
                            ->first();

                        if (! $pivotRecord) {
                            return [];
                        }

                        $complianceType = $this->getOwnerRecord()->type;

                        if ($complianceType === IndicatorComplianceTypeEnum::ELEMENT_PROGRESS) {
                            // Show month: target% format
                            return $pivotRecord->months()
                                ->orderBy('programme_month')
                                ->get()
                                ->map(fn ($month) => "Month {$month->programme_month}: {$month->target_value}%")
                                ->toArray();
                        } elseif (in_array($complianceType, [IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING])) {
                            // Show target percentage for all months
                            $firstMonth = $pivotRecord->months()->first();

                            return $firstMonth ? ["{$firstMonth->target_value}%"] : [];
                        } else {
                            // Show just the months for OTHER type
                            return $pivotRecord->months()->pluck('programme_month')->toArray();
                        }
                    })
                    ->separator(', ')
                    ->listWithLineBreaks(function (): bool {
                        $complianceType = $this->getOwnerRecord()->type;

                        return $complianceType === IndicatorComplianceTypeEnum::ELEMENT_PROGRESS;
                    }),

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
                        if (! isset($data['recordId'])) {
                            return;
                        }

                        $this->handleMonthsUpdate($data['recordId'], $data);
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
                        $pivotRecord = $record->pivot;
                        $pivotRecord->status = IndicatorProgrammeStatusEnum::PUBLISHED->value;
                        $pivotRecord->save();

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
                    ->modalHeading(function () {
                        $complianceType = $this->getOwnerRecord()->type;

                        return match ($complianceType) {
                            IndicatorComplianceTypeEnum::ELEMENT_PROGRESS => 'Update Element Progress Targets',
                            IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING => 'Update Attendance Target',
                            default => 'Update Collection Months'
                        };
                    })
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->form(fn (Programme $record) => $this->getMonthSelectionFormComponents(isForAttach: false, programme: $record))
                    ->fillForm(function (Programme $record) {
                        $pivotRecord = IndicatorComplianceProgramme::where('indicator_compliance_id', $this->getOwnerRecord()->id)
                            ->where('programme_id', $record->id)
                            ->first();

                        $complianceType = $this->getOwnerRecord()->type;

                        if ($complianceType === IndicatorComplianceTypeEnum::ELEMENT_PROGRESS) {
                            $monthTargets = $pivotRecord->months()
                                ->orderBy('programme_month')
                                ->get()
                                ->keyBy('programme_month');

                            $formData = [];
                            foreach ($monthTargets as $month => $target) {
                                $formData["month_{$month}_target"] = $target->target_value;
                            }

                            return $formData;
                        } elseif (in_array($complianceType, [IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING])) {
                            $firstMonth = $pivotRecord->months()->first();

                            return ['target_percentage' => $firstMonth?->target_value];
                        } else {
                            $currentMonths = $pivotRecord->months()->pluck('programme_month')->toArray();

                            return ['specific_months' => $currentMonths];
                        }
                    })
                    ->action(function (array $data, Programme $record) {
                        $this->handleMonthsUpdate($record->id, $data);

                        Notification::make()
                            ->title('Success')
                            ->body('Collection months updated successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DetachAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Remove Programme')
                    ->modalDescription('This action will remove the compliance indicator from the programme.'),
            ]);
    }

    private function getMonthSelectionFormComponents(bool $isForAttach = false, ?Programme $programme = null): array
    {
        $complianceType = $this->getOwnerRecord()->type;

        if ($complianceType === IndicatorComplianceTypeEnum::ELEMENT_PROGRESS) {
            return $this->getElementProgressFormComponents($isForAttach, $programme);
        } elseif (in_array($complianceType, [IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING])) {
            return $this->getAttendanceFormComponents($isForAttach, $programme);
        } else {
            return $this->getOtherFormComponents($isForAttach, $programme);
        }
    }

    private function getElementProgressFormComponents(bool $isForAttach = false, ?Programme $programme = null): array
    {
        return [
            Forms\Components\Grid::make()
                ->schema(function (Get $get) use ($isForAttach, $programme) {
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

                    $duration = $programme->period ?? 12;
                    $fields = [];

                    for ($month = 1; $month <= $duration; $month++) {
                        $fields[] = Forms\Components\TextInput::make("month_{$month}_target")
                            ->label("Month {$month} Target (%)")
                            ->inlineLabel()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->step(1)
                            ->placeholder('Optional')
                            ->live()
                            ->rules([
                                function (Get $get) use ($month) {
                                    return function (string $attribute, $value, $fail) use ($get, $month) {
                                        if ($value === null || $value === '') {
                                            return; // Skip validation for empty values
                                        }

                                        // Check if this target is lower than any previous month's target
                                        for ($prevMonth = 1; $prevMonth < $month; $prevMonth++) {
                                            $prevTarget = $get("month_{$prevMonth}_target");
                                            if ($prevTarget !== null && $prevTarget !== '' && $value < $prevTarget) {
                                                $fail("Month {$month} target must be equal to or higher than Month {$prevMonth} target ({$prevTarget}%)");

                                                return;
                                            }
                                        }
                                    };
                                },
                            ]);
                    }

                    return $fields;
                })
                ->columns(1)
                ->visible(function (Get $get) use ($isForAttach) {
                    return $isForAttach ? ($get('recordId') ?? false) : true;
                }),

            Forms\Components\Placeholder::make('progression_note')
                ->label('')
                ->content('💡 **Tip:** Progress targets should increase or stay the same as the programme progresses. Leave months blank if no target is needed.')
                ->visible(function (Get $get) use ($isForAttach) {
                    return $isForAttach ? ($get('recordId') ?? false) : true;
                }),
        ];
    }

    private function getAttendanceFormComponents(bool $isForAttach = false, ?Programme $programme = null): array
    {
        return [
            Forms\Components\TextInput::make('target_percentage')
                ->label('Target Attendance Percentage')
                ->helperText('This target will be applied to all months of the programme')
                ->numeric()
                ->required()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->step(1)
                ->visible(function (Get $get) use ($isForAttach) {
                    return $isForAttach ? ($get('recordId') ?? false) : true;
                }),
        ];
    }

    private function getOtherFormComponents(bool $isForAttach = false, ?Programme $programme = null): array
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

    private function handleMonthsUpdate(int $programmeId, array $data): void
    {
        // Find the pivot record
        $pivotRecord = IndicatorComplianceProgramme::where('indicator_compliance_id', $this->getOwnerRecord()->id)
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

        $complianceType = $this->getOwnerRecord()->type;
        $programme = Programme::find($programmeId);

        DB::transaction(function () use ($pivotRecord, $data, $complianceType, $programme) {
            // Clear existing months
            $pivotRecord->months()->delete();

            if ($complianceType === IndicatorComplianceTypeEnum::ELEMENT_PROGRESS) {
                // Handle Element Progress - create rows for months with target values
                $monthsToCreate = [];

                foreach ($data as $key => $value) {
                    if (str_starts_with($key, 'month_') && str_ends_with($key, '_target') && $value !== null && $value !== '') {
                        // Extract month number from field name like "month_3_target"
                        preg_match('/month_(\d+)_target/', $key, $matches);
                        if (isset($matches[1])) {
                            $monthNumber = (int) $matches[1];
                            $monthsToCreate[] = [
                                'programme_month' => $monthNumber,
                                'target_value' => $value,
                            ];
                        }
                    }
                }

                if (! empty($monthsToCreate)) {
                    $pivotRecord->months()->createMany($monthsToCreate);
                }
            } elseif (in_array($complianceType, [IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING])) {
                // Handle Attendance - create rows for all months with same target percentage
                $targetPercentage = $data['target_percentage'] ?? null;
                if ($targetPercentage !== null && $programme) {
                    $duration = $programme->period ?? 12;
                    $monthsToCreate = [];
                    for ($month = 1; $month <= $duration; $month++) {
                        $monthsToCreate[] = [
                            'programme_month' => $month,
                            'target_value' => $targetPercentage,
                        ];
                    }
                    $pivotRecord->months()->createMany($monthsToCreate);
                }
            } else {
                // Handle Other - create rows for selected months without target values
                $selectedMonths = $data['specific_months'] ?? [];
                if (! empty($selectedMonths)) {
                    $monthsToCreate = [];
                    foreach ($selectedMonths as $month) {
                        $monthsToCreate[] = [
                            'programme_month' => $month,
                            'target_value' => null,
                        ];
                    }
                    $pivotRecord->months()->createMany($monthsToCreate);
                }
            }
        });
    }
}
