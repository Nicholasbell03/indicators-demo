<?php

namespace App\Filament\Admin\Clusters\Indicators\Resources;

use App\Enums\IndicatorResponseFormatEnum;
use App\Enums\UserPermissions;
use App\Events\Indicator\IndicatorSubmissionApproved;
use App\Events\Indicator\IndicatorSubmissionRejected;
use App\Filament\Admin\Clusters\Indicators;
use App\Filament\Admin\Clusters\Indicators\Resources\IndicatorVerificationsResource\Pages;
use App\Models\IndicatorReviewTask;
use App\Models\IndicatorSubmissionReview;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// TODO: Adopt this to handle models without a verifierUser
// Null checks and possible a filter for admins to see this.
class IndicatorVerificationsResource extends Resource
{
    protected static ?string $model = IndicatorReviewTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $cluster = Indicators::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $pluralModelLabel = 'Indicator Verifications';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->with([
                'indicatorSubmission.task.indicatable',
                'indicatorSubmission.task.entrepreneur',
                'indicatorSubmission.task.organisation',
                'indicatorSubmission.task.programme',
                'indicatorSubmission.attachments',
                'indicatorSubmissionReview',
            ]);

        if ($user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value)) {
            return $query;
        }

        return $query
            ->where('verifier_user_id', $user->id);
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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('indicatorSubmission.task.indicatable.title')
                    ->label('Indicator')
                    ->width(200)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('indicator_type')
                    ->label('Indicator Type')
                    ->getStateUsing(fn (IndicatorReviewTask $record): string => class_basename($record->indicatorSubmission->task->indicatable_type) === 'IndicatorSuccess' ? 'Success' : 'Compliance'
                    )
                    ->colors([
                        'success' => 'Success',
                        'warning' => 'Compliance',
                    ])
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('indicatorSubmission.task.entrepreneur.name')
                    ->label('Entrepreneur')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('indicatorSubmission.task.organisation.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('indicatorSubmission.task.programme.title')
                    ->label('Programme Name')
                    ->wrap()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('indicatorSubmission.submitted_at')
                    ->label('Date Submitted')
                    ->wrap()
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('verifierUser.name')
                    ->label('Verifier')
                    ->visible(function (): bool {
                        /** @var User $user */
                        $user = auth()->user();

                        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value);
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('verifier_level')
                    ->label('Verification Level')
                    ->getStateUsing(fn (IndicatorReviewTask $record): string => "Level {$record->verifier_level}"
                    )
                    ->colors([
                        'info' => fn ($state): bool => str_contains($state, '1'),
                        'warning' => fn ($state): bool => str_contains($state, '2'),
                    ])
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('decision')
                    ->label('Decision')
                    ->getStateUsing(function (IndicatorReviewTask $record): ?string {
                        $review = $record->indicatorSubmissionReview;
                        if (! $review) {
                            return null;
                        }

                        return $review->approved ? 'Approve' : 'Reject';
                    })
                    ->colors([
                        'success' => 'Approve',
                        'danger' => 'Reject',
                    ])
                    ->visible(fn ($livewire) => $livewire->activeTab === 'completed'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed At')
                    ->dateTime()
                    ->sortable()
                    ->visible(fn ($livewire) => $livewire->activeTab === 'completed'),
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
                            fn (Builder $query, $value): Builder => $query->whereHas('indicatorSubmission.task',
                                fn (Builder $query) => $query->where('indicatable_type', 'App\\Models\\'.$value)
                            ),
                        );
                    }),

                Tables\Filters\SelectFilter::make('programme')
                    ->label('Programme Name')
                    ->relationship('indicatorSubmission.task.programme', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('verifierUser')
                    ->label('Verifier')
                    ->relationship('verifierUser', 'name')
                    ->searchable()
                    ->visible(function (): bool {
                        /** @var User $user */
                        $user = auth()->user();

                        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value);
                    })
                    ->preload(),
                TernaryFilter::make('is_orphaned')
                    ->label('Items with no verifier')
                    ->placeholder('All Tasks')
                    ->trueLabel('No Verifier')
                    ->falseLabel('Has Verifier')
                    ->visible(function (): bool {
                        /** @var User $user */
                        $user = auth()->user();

                        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value);
                    })
                    ->queries(
                        true: fn (Builder $query): Builder => $query->orphaned(),
                        false: fn (Builder $query): Builder => $query->notOrphaned(),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->actions([
                self::assignVerifierAction(),
                self::verifyAction(),
            ])
            ->bulkActions([
                // No bulk actions for verification workflow
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('indicatorSubmission.submitted_at', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->deferLoading();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndicatorVerifications::route('/'),
        ];
    }

    private static function verifyAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('verify')
            ->label('Verify')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(function (IndicatorReviewTask $record): bool {
                /** @var User $user */
                $user = auth()->user();
                $userCanVerify = $user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value) || $record->verifier_user_id === $user->id;
                $recordIsNotCompleted = $record->completed_at === null;

                return $userCanVerify && $recordIsNotCompleted;
            })
            ->form([
                Forms\Components\Placeholder::make('instructions')
                    ->hiddenLabel()
                    ->content(fn (IndicatorReviewTask $record): string => "You are reviewing a Level {$record->verifier_level} verification for this indicator submission. Please review all submission data carefully and make your decision."
                    )
                    ->columnSpanFull(),

                Forms\Components\Section::make('Indicator Data')
                    ->schema([
                        Forms\Components\Placeholder::make('indicator_details')
                            ->label('Indicator')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorTask->indicatable->title ?? 'N/A'
                            )
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('indicator_instructions')
                            ->label('Additional Instructions')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorTask->indicatable->additional_instruction ?? 'N/A'
                            )
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('target_value')
                            ->label('Target Value')
                            ->visible(fn (IndicatorReviewTask $record): bool => $record->indicatorTask->indicatable->response_format !== IndicatorResponseFormatEnum::BOOLEAN->value)
                            ->content(function (IndicatorReviewTask $record): string {
                                $responseFormat = $record->indicatorTask->indicatable->response_format;
                                $targetValue = $record->indicatorTask->indicatable->target_value;

                                if ($responseFormat === IndicatorResponseFormatEnum::NUMERIC) {
                                    return $targetValue;
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::PERCENTAGE) {
                                    return $targetValue.'%';
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::MONETARY) {
                                    return $targetValue.' '.$record->indicatorTask->indicatable->currency;
                                }

                                return 'N/A';
                            }),
                        Forms\Components\Placeholder::make('acceptance_value')
                            ->label('Acceptance Value')
                            ->content(function (IndicatorReviewTask $record): string {
                                $responseFormat = $record->indicatorTask->indicatable->response_format;
                                $acceptanceValue = $record->indicatorTask->indicatable->acceptance_value;

                                if ($responseFormat === IndicatorResponseFormatEnum::NUMERIC) {
                                    return $acceptanceValue;
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::PERCENTAGE) {
                                    return $acceptanceValue.'%';
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::MONETARY) {
                                    return $acceptanceValue.' '.$record->indicatorTask->indicatable->currency;
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::BOOLEAN) {
                                    return $acceptanceValue ? 'Yes' : 'No';
                                }

                                return 'N/A';
                            }),

                    ])
                    ->columns(2),

                Forms\Components\Section::make('Submission Data')
                    ->schema([
                        Forms\Components\Placeholder::make('entrepreneur_details')
                            ->label('Entrepreneur')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorSubmission->task->entrepreneur->name ?? 'N/A'
                            ),

                        Forms\Components\Placeholder::make('organisation_details')
                            ->label('Organisation')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorSubmission->task->organisation->name ?? 'N/A'
                            ),

                        Forms\Components\Placeholder::make('submission_date')
                            ->label('Submitted On')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorSubmission->submitted_at?->format('d M Y, H:i') ?? 'N/A'
                            ),

                        Forms\Components\Placeholder::make('submitted_value')
                            ->label('Submitted Value')
                            ->content(function (IndicatorReviewTask $record): string {
                                $responseFormat = $record->indicatorTask->indicatable->response_format;
                                $value = $record->indicatorSubmission->value;

                                if ($responseFormat === IndicatorResponseFormatEnum::NUMERIC) {
                                    return $value;
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::PERCENTAGE) {
                                    return $value.'%';
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::MONETARY) {
                                    return $value.' '.$record->indicatorTask->indicatable->currency;
                                }

                                if ($responseFormat === IndicatorResponseFormatEnum::BOOLEAN) {
                                    return $value ? 'Yes' : 'No';
                                }

                                return 'N/A';
                            }),

                        Forms\Components\Placeholder::make('attachments')
                            ->label('Attachments')
                            ->content(function (IndicatorReviewTask $record): string {
                                $attachments = $record->indicatorSubmission->attachments;
                                if ($attachments->isEmpty()) {
                                    return 'No attachments';
                                }

                                $list = $attachments->map(fn ($attachment) => "<a href='{$attachment->file_url}' target='_blank' class='text-primary-600 hover:text-primary-500'>{$attachment->title}</a>"
                                )->join('<br>');

                                return $list;
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('helper_text')
                            ->hiddenLabel()
                            ->content(function (IndicatorReviewTask $record) {
                                $responseFormat = $record->indicatorTask->indicatable->response_format;
                                $submittedValue = $record->indicatorSubmission->value;
                                $acceptanceValue = $record->indicatorTask->indicatable->acceptance_value;

                                if (in_array($responseFormat, [IndicatorResponseFormatEnum::NUMERIC, IndicatorResponseFormatEnum::PERCENTAGE, IndicatorResponseFormatEnum::MONETARY])) {
                                    if ($submittedValue >= $acceptanceValue) {
                                        $value = 'The entrepreneur has met the acceptable criteria.';
                                        $badgeColor = '#dcfce7'; // bg-green-50
                                        $badgeTextColor = '#059669'; // text-green-600
                                    } else {
                                        $value = 'The entrepreneur has not met the acceptable criteria.';
                                        $badgeColor = '#fef2f2'; // bg-red-50
                                        $badgeTextColor = '#dc2626'; // text-red-600
                                    }
                                } elseif ($responseFormat === IndicatorResponseFormatEnum::BOOLEAN) {
                                    if ($submittedValue === $acceptanceValue) {
                                        $value = 'The entrepreneur has met the acceptable criteria.';
                                        $badgeColor = '#dcfce7'; // bg-green-50
                                        $badgeTextColor = '#059669'; // text-green-600
                                    } else {
                                        $value = 'The entrepreneur has not met the acceptable criteria.';
                                        $badgeColor = '#fef2f2'; // bg-red-50
                                        $badgeTextColor = '#dc2626'; // text-red-600
                                    }
                                } else {
                                    $value = 'N/A';
                                    $badgeColor = '#f9fafb'; // bg-gray-50
                                    $badgeTextColor = '#6b7280'; // text-gray-500
                                }

                                return new \Illuminate\Support\HtmlString(
                                    view('filament.forms.components.badge-field', compact('value', 'badgeColor', 'badgeTextColor'))->render()
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Verification Decision')
                    ->schema([
                        Forms\Components\Radio::make('decision')
                            ->label('Decision')
                            ->options([
                                'approve' => 'Approve',
                                'reject' => 'Reject',
                            ])
                            ->required()
                            ->reactive()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('feedback')
                            ->label('Feedback/Comments')
                            ->helperText('Provide detailed feedback, especially if rejecting the submission.')
                            ->required(fn (callable $get): bool => $get('decision') === 'reject')
                            ->visible(fn (callable $get): bool => $get('decision') !== null)
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (IndicatorReviewTask $record, array $data): void {
                try {
                    DB::transaction(function () use ($record, $data) {
                        $approved = $data['decision'] === 'approve';

                        // Create the review record
                        $review = IndicatorSubmissionReview::create([
                            'indicator_review_task_id' => $record->id,
                            'indicator_submission_id' => $record->indicatorSubmission->id,
                            'reviewer_id' => auth()->id(),
                            'approved' => $approved,
                            'verifier_level' => $record->verifier_level,
                            'comment' => $data['feedback'] ?? null,
                            'reviewed_at' => now(),
                        ]);

                        // Update the review task as completed
                        $record->update([
                            'completed_at' => now(),
                        ]);

                        // Dispatch appropriate event - let listeners handle status updates and Level 2 logic
                        if ($approved) {
                            event(new IndicatorSubmissionApproved($review));
                        } else {
                            event(new IndicatorSubmissionRejected($review));
                        }
                    });

                    \Filament\Notifications\Notification::make()
                        ->title('Verification Complete')
                        ->body($data['decision'] === 'approve' ? 'Submission approved successfully.' : 'Submission rejected.')
                        ->success()
                        ->send();

                } catch (\Exception $e) {
                    Log::error('Verification failed', [
                        'user_id' => auth()->id(),
                        'review_task_id' => $record->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Verification Failed')
                        ->body('An error occurred while processing the verification. Please try again or contact support.')
                        ->danger()
                        ->send();
                }
            });
    }

    private static function assignVerifierAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('assignVerifier')
            ->label('Change Verifier')
            ->icon('heroicon-o-user')
            ->color('warning')
            ->visible(function (): bool {
                /** @var User $user */
                $user = auth()->user();

                return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value);
            })
            ->modalHeading('Assign / Change Verifier')
            ->form([
                Forms\Components\Section::make('Task Details')
                    ->schema([
                        Forms\Components\Placeholder::make('indicator')
                            ->label('Indicator')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorTask->indicatable->title ?? 'N/A')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('entrepreneur')
                            ->label('Entrepreneur')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorSubmission->task->entrepreneur->name ?? 'N/A'),

                        Forms\Components\Placeholder::make('organisation')
                            ->label('Organisation')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorSubmission->task->organisation->name ?? 'N/A'),

                        Forms\Components\Placeholder::make('programme')
                            ->label('Programme')
                            ->content(fn (IndicatorReviewTask $record): string => $record->indicatorSubmission->task->programme->title ?? 'N/A'),

                        Forms\Components\Placeholder::make('current_verifier')
                            ->label('Current Verifier')
                            ->content(fn (IndicatorReviewTask $record): string => $record->verifierUser?->name ?? 'Unassigned')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Select Verifier')
                    ->schema([
                        Forms\Components\Select::make('verifier_user_id')
                            ->label('Verifier')
                            ->searchable()
                            ->preload()
                            ->placeholder('Unassigned')
                            ->nullable()
                            ->relationship('verifierUser', 'name', function (Builder $query) {
                                $roles = config('success-compliance-indicators.verifier_roles', []);

                                return $query
                                    ->tenantsOfCurrentTenantPortfolio()
                                    ->whereHas('roles', function (Builder $roleQuery) use ($roles) {
                                        $roleQuery->whereIn('name', $roles);
                                    })
                                    ->orderBy('name');
                            })
                            ->helperText('Shows users with verifier roles within your tenant portfolio.'),
                    ])
                    ->columns(1),
            ])
            ->action(function (IndicatorReviewTask $record, array $data): void {
                try {
                    $record->update([
                        'verifier_user_id' => $data['verifier_user_id'] ?? null,
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Verifier updated')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Log::error('Failed to assign verifier', [
                        'user_id' => auth()->id(),
                        'review_task_id' => $record->id,
                        'error' => $e->getMessage(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Assigning verifier failed')
                        ->body('An error occurred while assigning the verifier. Please try again.')
                        ->danger()
                        ->send();
                }
            });
    }
}
