<?php

namespace App\Filament\TenantPortfolio\Resources;

use App\Enums\IndicatorLevelEnum;
use App\Filament\Forms\Components\IndicatorSharedFields;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\Pages;
use App\Filament\TenantPortfolio\Resources\IndicatorSuccessResource\RelationManagers\ProgrammesRelationManager;
use App\Models\IndicatorSuccess;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class IndicatorSuccessResource extends Resource
{
    protected static ?string $model = IndicatorSuccess::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $modelLabel = 'Success Indicator';

    protected static ?string $navigationGroup = 'Indicators';

    protected static ?int $navigationSort = 1;

    protected static IndicatorLevelEnum $level = IndicatorLevelEnum::PORTFOLIO;

    protected static function getLevel(): IndicatorLevelEnum
    {
        return static::$level;
    }

    protected static function isLevel(IndicatorLevelEnum $level): bool
    {
        return static::getLevel() === $level;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Basic Information')
                    ->description('What is the success indicator?')
                    ->schema([
                        IndicatorSharedFields::titleInput(),
                        IndicatorSharedFields::descriptionInput(),
                        IndicatorSharedFields::additionalInstructionsTextarea(),
                    ]),
                Section::make('Response Definition')
                    ->description('How should the entrepreneur respond?')
                    ->columns(2)
                    ->schema([
                        IndicatorSharedFields::responseFormatSelect(),
                        IndicatorSharedFields::currencySelect(),
                        IndicatorSharedFields::currencyPlaceholder(),
                        IndicatorSharedFields::booleanAcceptanceToggle(),
                        IndicatorSharedFields::targetValueInput(),
                        IndicatorSharedFields::acceptanceValueInput(),
                        IndicatorSharedFields::supportingDocumentationCheckbox(),
                        IndicatorSharedFields::supportingDocumentationTextarea(),
                    ]),
                Section::make('Verification')
                    ->description('Does this require verification?')
                    ->schema([
                        IndicatorSharedFields::requiresVerificationCheckbox(),
                        IndicatorSharedFields::verifier1Select(),
                        IndicatorSharedFields::verifier2Select(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->description(fn (IndicatorSuccess $record) => Str::limit($record->description, 50, '...'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('level')
                    ->label('Set By')
                    ->searchable()
                    ->formatStateUsing(fn (IndicatorLevelEnum $state) => $state->label())
                    ->color(fn (IndicatorSuccess $record) => $record->level === IndicatorLevelEnum::PORTFOLIO ? 'primary' : 'secondary')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('programmes_count')
                    ->label('Associated Programmes')
                    ->wrapHeader()
                    ->counts('programmes')
                    ->tooltip('The number of programmes that have this success indicator')
                    ->color('gray')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('H:i d/m/Y')
                    ->toggleable()
                    ->alignCenter()
                    ->wrap()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('H:i d/m/Y')
                    ->toggleable()
                    ->alignCenter()
                    ->wrap()
                    ->sortable(),
            ])
            ->defaultSort('title', 'asc')
            ->filters([
                //
            ])
            ->recordUrl(function (IndicatorSuccess $record) {
                if ($record->level === IndicatorLevelEnum::PORTFOLIO) {
                    return static::getUrl('edit', ['record' => $record]);
                } else {
                    return static::getUrl('view', ['record' => $record]);
                }
            })
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->hidden(fn (IndicatorSuccess $record) => $record->level === static::getLevel())
                    ->tooltip(function (IndicatorSuccess $record) {
                        if ($record->level === IndicatorLevelEnum::PORTFOLIO) {
                            return 'You may only view this indicator becauses it was created in the Portfolio dashboard.';
                        } else {
                            return 'You may only view this indicator becauses it was created in the Parent Group dashboard.';
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (IndicatorSuccess $record) => $record->level === static::getLevel()),
                // TODO: Add delete action
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProgrammesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndicatorSuccesses::route('/'),
            'create' => Pages\CreateIndicatorSuccess::route('/create'),
            'view' => Pages\ViewIndicatorSuccess::route('/{record}'),
            'edit' => Pages\EditIndicatorSuccess::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->ofCurrentTenantPortfolioOrCluster()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
