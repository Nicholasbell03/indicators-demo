<?php

namespace App\Filament\Admin\Clusters\Indicators\Resources;

use App\Enums\IndicatorResponseFormatEnum;
use App\Enums\IndicatorTaskStatusEnum;
use App\Filament\Admin\Clusters\Indicators;
use App\Filament\Admin\Clusters\Indicators\Resources\IndicatorSubmissionsResource\Pages;
use App\Models\IndicatorSubmissionAttachment;
use App\Models\IndicatorTask;
use App\Services\IndicatorSubmissionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IndicatorSubmissionsResource extends Resource
{
    protected static ?string $model = IndicatorTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $cluster = Indicators::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralModelLabel = 'Indicator Submissions';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('responsible_user_id', auth()->id())
            ->with([
                'indicatable',
                'entrepreneur',
                'organisation',
                'programme',
                'latestSubmission.reviews',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form will be implemented in modal actions
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('indicatable.title')
                    ->label('Indicator')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('indicator_type')
                    ->label('Indicator Type')
                    ->badge()
                    ->getStateUsing(fn (IndicatorTask $record): string => class_basename($record->indicatable_type) === 'IndicatorSuccess' ? 'Success' : 'Compliance'
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'Success' => 'success',
                        'Compliance' => 'warning',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('entrepreneur.name')
                    ->label('Entrepreneur')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('programme.title')
                    ->label('Programme')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn (IndicatorTask $record): string => $record->display_status->color())
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('indicator_type')
                    ->label('Indicator Type')
                    ->options([
                        'IndicatorSuccess' => 'Success',
                        'IndicatorCompliance' => 'Compliance',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => $query->where('indicatable_type', 'App\\Models\\'.$value),
                        );
                    }),

                Tables\Filters\SelectFilter::make('programme')
                    ->label('Programme Name')
                    ->relationship('programme', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'overdue' => 'Overdue',
                        'needs_revision' => 'Needs Revision',
                        'submitted' => 'Submitted',
                        'completed' => 'Completed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['value']) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'pending' => $query->where('status', IndicatorTaskStatusEnum::PENDING)
                                ->where('due_date', '>=', now()),
                            'overdue' => $query->where('status', IndicatorTaskStatusEnum::PENDING)
                                ->where('due_date', '<', now()),
                            'needs_revision' => $query->where('status', IndicatorTaskStatusEnum::NEEDS_REVISION),
                            'submitted' => $query->where('status', IndicatorTaskStatusEnum::SUBMITTED),
                            'completed' => $query->where('status', IndicatorTaskStatusEnum::COMPLETED),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (IndicatorTask $record): bool => in_array($record->display_status, [
                        IndicatorTaskStatusEnum::PENDING,
                        IndicatorTaskStatusEnum::NEEDS_REVISION,
                        IndicatorTaskStatusEnum::OVERDUE,
                    ])
                    )
                    ->modalHeading(fn (IndicatorTask $record): string => class_basename($record->indicatable_type) === 'IndicatorSuccess'
                            ? 'Success Indicator'
                            : 'Compliance Indicator'
                    )
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\Placeholder::make('status_header')
                            ->hiddenLabel()
                            ->content(fn (IndicatorTask $record): string => 'Current Status: '.$record->display_label)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('review_comments')
                            ->content(function (IndicatorTask $record): ?string {
                                if ($record->status !== IndicatorTaskStatusEnum::NEEDS_REVISION) {
                                    return null;
                                }

                                $latestReview = $record->latestSubmission?->latestReview;

                                return $latestReview?->comment
                                    ? 'Previous Review Comments: '.$latestReview->comment
                                    : null;
                            })
                            ->visible(fn (IndicatorTask $record): bool => $record->status === IndicatorTaskStatusEnum::NEEDS_REVISION
                            )
                            ->columnSpanFull(),

                        Forms\Components\Section::make('Indicator Response')
                            ->schema(fn (IndicatorTask $record): array => self::getIndicatorResponseSchema($record)),
                    ])
                    ->action(function (IndicatorTask $record, array $data): void {
                        try {
                            $submissionService = app(IndicatorSubmissionService::class);
                            $submissionService->createSubmissionFromFilament(
                                $data,
                                auth()->user(),
                                $record->id
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('Submission Successful')
                                ->body('Your indicator submission has been submitted for verification.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Submission Failed')
                                ->body('There was an error submitting your indicator. Please try again.')
                                ->danger()
                                ->send();

                            // Log the error for debugging
                            \Illuminate\Support\Facades\Log::error('Indicator submission failed', [
                                'task_id' => $record->id,
                                'user_id' => auth()->id(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }),

                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (IndicatorTask $record): bool => in_array($record->display_status, [
                        IndicatorTaskStatusEnum::SUBMITTED,
                        IndicatorTaskStatusEnum::COMPLETED,
                    ])
                    )
                    ->modalHeading(fn (IndicatorTask $record): string => class_basename($record->indicatable_type) === 'IndicatorSuccess'
                            ? 'Success Indicator'
                            : 'Compliance Indicator'
                    )
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\Placeholder::make('status_header')
                            ->content(fn (IndicatorTask $record): string => 'Current Status: '.$record->display_label
                            )
                            ->columnSpanFull(),

                        Forms\Components\Section::make('Submission Details')
                            ->schema([
                                Forms\Components\Placeholder::make('indicator_title')
                                    ->label('Indicator')
                                    ->content(fn (IndicatorTask $record): string => $record->indicatable->title),

                                Forms\Components\Placeholder::make('submitted_value')
                                    ->label('Submitted Value')
                                    ->content(fn (IndicatorTask $record): string => $record->latestSubmission?->value ?? 'No submission'
                                    ),

                                Forms\Components\Placeholder::make('submitted_comment')
                                    ->label('Comments')
                                    ->content(fn (IndicatorTask $record): string => $record->latestSubmission?->comment ?? 'No comments'
                                    ),

                                Forms\Components\Placeholder::make('attachments')
                                    ->label('Attachments')
                                    ->content(function (IndicatorTask $record): string {
                                        $attachments = $record->latestSubmission?->attachments ?? collect();
                                        if ($attachments->isEmpty()) {
                                            return 'No attachments';
                                        }

                                        return $attachments->map(fn ($attachment) => $attachment->title)
                                            ->join(', ');
                                    })
                                    ->visible(fn (IndicatorTask $record): bool => $record->latestSubmission?->attachments?->isNotEmpty() ?? false
                                    ),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([
                // No bulk actions as per requirements
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('due_date', 'asc')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->deferLoading();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndicatorSubmissions::route('/'),
        ];
    }

    private static function getIndicatorResponseSchema(IndicatorTask $record): array
    {
        $responseFormat = $record->indicatable->response_format;
        $indicatable = $record->indicatable;

        $infoBox = Forms\Components\View::make('filament.forms.components.indicator-info-box')
            ->viewData([
                'targetValue' => $indicatable->target_value,
                'acceptanceValue' => $indicatable->acceptance_value,
            ])
            ->visible(fn (): bool => (bool) $indicatable->target_value);

        $valueField = match ($responseFormat) {
            IndicatorResponseFormatEnum::BOOLEAN => Forms\Components\ToggleButtons::make('value')
                ->options([
                    'true' => 'Yes',
                    'false' => 'No',
                ])
                ->inline()
                ->label($record->indicatable->title)
                ->helperText('Successful response: '.($record->indicatable->acceptance_value == 'true' ? 'Yes' : 'No'))
                ->required(),

            IndicatorResponseFormatEnum::PERCENTAGE => Forms\Components\TextInput::make('value')
                ->label($record->indicatable->title)
                ->helperText($record->indicatable->additional_instruction)
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->required(),

            IndicatorResponseFormatEnum::MONETARY => Forms\Components\TextInput::make('value')
                ->label($record->indicatable->title)
                ->helperText($record->indicatable->additional_instruction)
                ->numeric()
                ->minValue(0)
                ->prefix($record->indicatable->currency)
                ->required(),

            default => Forms\Components\TextInput::make('value')
                ->label($record->indicatable->title)
                ->helperText($record->indicatable->additional_instruction)
                ->numeric()
                ->required(),
        };

        $valueField->default(function () use ($record) {
            if ($record->status === IndicatorTaskStatusEnum::NEEDS_REVISION) {
                return $record->latestSubmission?->value;
            }

            return null;
        });

        $attachmentField = Forms\Components\FileUpload::make('attachments')
            ->label('Supporting Documents')
            ->multiple()
            ->disk(IndicatorSubmissionAttachment::getDisk())
            ->preserveFilenames()
            ->directory(fn (IndicatorTask $record): string => 'indicator_task_'.$record->id)
            ->visibility('public')
            ->maxSize(20 * 1024) // 20MB
            ->visible(fn (IndicatorTask $record): bool => ! empty($record->indicatable->supporting_documentation))
            ->default(function (IndicatorTask $record): array {
                // Show existing attachments for needs revision
                if ($record->status === IndicatorTaskStatusEnum::NEEDS_REVISION) {
                    return $record->latestSubmission?->attachments
                        ->pluck('file_path')
                        ->toArray() ?? [];
                }

                return [];
            });

        $commentField = Forms\Components\Textarea::make('comment')
            ->label('Comments')
            ->rows(3)
            ->placeholder('Add any additional comments about your submission...')
            ->default(function (IndicatorTask $record): ?string {
                // Pre-fill for needs revision tasks
                if ($record->status === IndicatorTaskStatusEnum::NEEDS_REVISION) {
                    return $record->latestSubmission?->comment;
                }

                return null;
            });

        return [$infoBox, $valueField, $attachmentField, $commentField];
    }
}
