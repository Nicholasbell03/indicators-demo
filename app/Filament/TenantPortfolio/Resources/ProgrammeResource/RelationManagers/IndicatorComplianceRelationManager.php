<?php

namespace App\Filament\TenantPortfolio\Resources\ProgrammeResource\RelationManagers;

use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorProgrammeStatusEnum;
use App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource;
use App\Models\IndicatorCompliance;
use App\Models\IndicatorComplianceProgramme;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IndicatorComplianceRelationManager extends RelationManager
{
    protected static string $relationship = 'indicatorCompliances';

    protected static ?string $title = 'Compliance Indicators';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->indicatorCompliances->count();
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
                Tables\Columns\TextColumn::make('title')
                    ->label('Compliance Indicator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => Str::title($state))
                    ->tooltip(fn (string $state): string => match ($state) {
                        IndicatorProgrammeStatusEnum::PENDING->value => 'This compliance indicator is pending approval',
                        IndicatorProgrammeStatusEnum::PUBLISHED->value => 'This compliance indicator is published and can\'t be unpublished',
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
                    ->label('Collection Months / Targets')
                    ->wrapHeader()
                    ->wrap()
                    ->getStateUsing(function (IndicatorCompliance $record) {
                        $pivotRecord = IndicatorComplianceProgramme::where('indicator_compliance_id', $record->id)
                            ->where('programme_id', $this->getOwnerRecord()->id)
                            ->first();

                        if (! $pivotRecord) {
                            return [];
                        }

                        $complianceType = $record->type;

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
                    ->listWithLineBreaks(function (IndicatorCompliance $record): bool {
                        $complianceType = $record->type;
                        if ($complianceType === IndicatorComplianceTypeEnum::ELEMENT_PROGRESS) {
                            return true;
                        } else {
                            return false;
                        }
                    }),

            ])
            ->defaultSort('title', 'asc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Attach Compliance Indicator')
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
                        $this->handleMonthsUpdate($data['recordId'], $data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make('publish')
                    ->label('Publish')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(function (IndicatorCompliance $record) {
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
                    ->modalHeading('Publish Compliance Indicator')
                    ->modalDescription('Are you sure you want to publish this compliance indicator? Once published, it cannot be undone.')
                    ->action(function (IndicatorCompliance $record) {
                        $pivotRecord = IndicatorComplianceProgramme::where('indicator_compliance_id', $record->id)
                            ->where('programme_id', $this->getOwnerRecord()->id)
                            ->first();
                        $pivotRecord->status = IndicatorProgrammeStatusEnum::PUBLISHED->value;
                        $pivotRecord->save();

                        Notification::make()
                            ->title('Success')
                            ->body('Compliance indicator published successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make('edit_months')
                    ->label('Edit')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->visible(function (IndicatorCompliance $record) {
                        $currentPanel = Filament::getCurrentPanel()->getId();

                        if ($currentPanel === 'tenantPortfolio') {
                            return $record->level === IndicatorLevelEnum::PORTFOLIO;
                        } else {
                            return $record->level === IndicatorLevelEnum::ESO;
                        }
                    })
                    ->modalHeading(function (IndicatorCompliance $record) {
                        $complianceType = $record->type;

                        return match ($complianceType) {
                            IndicatorComplianceTypeEnum::ELEMENT_PROGRESS => 'Update Element Progress Targets',
                            IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING => 'Update Attendance Target',
                            default => 'Update Collection Months'
                        };
                    })
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->form(fn (IndicatorCompliance $record) => [
                        Forms\Components\Actions::make([
                            $this->getViewIndicatorAction(isForAttach: false, record: $record),
                        ])
                            ->alignStart(),
                        ...$this->getMonthSelectionFormComponents(isForAttach: false, indicatorCompliance: $record),
                    ])
                    ->fillForm(function (IndicatorCompliance $record) {
                        $pivotRecord = IndicatorComplianceProgramme::where('indicator_compliance_id', $record->id)
                            ->where('programme_id', $this->getOwnerRecord()->id)
                            ->first();
                        $complianceType = $record->type;

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
                    ->action(function (array $data, IndicatorCompliance $record) {
                        $this->handleMonthsUpdate($record->id, $data);

                        Notification::make()
                            ->title('Success')
                            ->body('Collection months updated successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DetachAction::make()
                    ->requiresConfirmation()
                    ->visible(function (IndicatorCompliance $record) {
                        $currentPanel = Filament::getCurrentPanel()->getId();

                        if ($currentPanel === 'tenantPortfolio') {
                            return $record->level === IndicatorLevelEnum::PORTFOLIO;
                        } else {
                            return $record->level === IndicatorLevelEnum::ESO;
                        }
                    })
                    ->modalHeading('Remove Compliance Indicator')
                    ->modalDescription('This action will remove the compliance indicator from the programme.'),
            ]);
    }

    private function getMonthSelectionFormComponents(bool $isForAttach = false, ?IndicatorCompliance $indicatorCompliance = null): array
    {
        if ($isForAttach) {
            // For attach action, we need to determine the compliance type from the selected record
            return [
                Forms\Components\Grid::make()
                    ->schema(function (Get $get) {
                        $recordId = $get('recordId');
                        if (! $recordId) {
                            return [];
                        }

                        $indicatorCompliance = IndicatorCompliance::find($recordId);
                        if (! $indicatorCompliance) {
                            return [];
                        }

                        return $this->getFormComponentsForType($indicatorCompliance->type, $indicatorCompliance, true);
                    })
                    ->visible(function (Get $get) {
                        return (bool) $get('recordId');
                    }),
            ];
        } else {
            // For edit action, we already have the compliance indicator
            if (! $indicatorCompliance) {
                return [];
            }

            return $this->getFormComponentsForType($indicatorCompliance->type, $indicatorCompliance, false);
        }
    }

    private function getFormComponentsForType(IndicatorComplianceTypeEnum $complianceType, IndicatorCompliance $indicatorCompliance, bool $isForAttach): array
    {
        if ($complianceType === IndicatorComplianceTypeEnum::ELEMENT_PROGRESS) {
            return $this->getElementProgressFormComponents($isForAttach);
        } elseif (in_array($complianceType, [IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING])) {
            return $this->getAttendanceFormComponents($isForAttach);
        } else {
            return $this->getOtherFormComponents($isForAttach);
        }
    }

    private function getElementProgressFormComponents(bool $isForAttach = false): array
    {
        $programme = $this->getOwnerRecord();

        return [
            Forms\Components\Grid::make()
                ->schema(function () use ($programme) {
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
                ->columns(1),

            Forms\Components\Placeholder::make('progression_note')
                ->label('')
                ->content('💡 **Tip:** Progress targets should increase or stay the same as the programme progresses. Leave months blank if no target is needed.'),
        ];
    }

    private function getAttendanceFormComponents(bool $isForAttach = false): array
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
                ->step(1),
        ];
    }

    private function getOtherFormComponents(bool $isForAttach = false): array
    {
        $programme = $this->getOwnerRecord();

        return [
            Forms\Components\CheckboxList::make('specific_months')
                ->label('Select the months for data collection')
                ->options($this->getMonthOptions($programme))
                ->columns(3)
                ->gridDirection('row')
                ->required()
                ->reactive(),

            Actions::make([
                Action::make('select_all_months')
                    ->label('Select all')
                    ->link()
                    ->action(function (Set $set) use ($programme) {
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

    private function handleMonthsUpdate(int $indicatorComplianceId, array $data): void
    {
        // Find the pivot record (note the parameter order is swapped from ProgrammesRelationManager)
        $pivotRecord = IndicatorComplianceProgramme::where('indicator_compliance_id', $indicatorComplianceId)
            ->where('programme_id', $this->getOwnerRecord()->id)
            ->first();

        if (! $pivotRecord) {
            Notification::make()
                ->title('Error')
                ->body('Failed to find compliance indicator relationship')
                ->danger()
                ->send();

            return;
        }

        $indicatorCompliance = IndicatorCompliance::find($indicatorComplianceId);
        $complianceType = $indicatorCompliance->type;
        $programme = $this->getOwnerRecord();

        try {
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
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Failed to update compliance indicator months: '.$e->getMessage())
                ->danger()
                ->send();

            Log::error('Failed to update compliance indicator months: '.$e->getMessage());
        }
    }

    private function getViewIndicatorAction(bool $isForAttach = false, ?IndicatorCompliance $record = null): Action
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

                    return $this->getIndicatorComplianceResourceClass()::getUrl('view', ['record' => $recordId]);
                } else {
                    return $this->getIndicatorComplianceResourceClass()::getUrl('view', ['record' => $record->id]);
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
    private function getIndicatorComplianceResourceClass(): string
    {
        // Get the current Livewire component to determine the panel context
        $currentPanel = Filament::getCurrentPanel()->getId();

        // Check if we're in a TenantPortfolio context by looking at the panel id
        if ($currentPanel === 'tenantPortfolio') {
            return \App\Filament\TenantPortfolio\Resources\IndicatorComplianceResource::class;
        }

        // Default to TenantCluster (as it's the least permission required)
        return \App\Filament\TenantCluster\Resources\IndicatorComplianceResource::class;
    }
}
