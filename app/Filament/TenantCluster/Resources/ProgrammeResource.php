<?php

namespace App\Filament\TenantCluster\Resources;

use App\Filament\TenantCluster\Resources\ProgrammeResource\Pages;
use App\Filament\TenantPortfolio\Resources\ProgrammeResource\RelationManagers\IndicatorComplianceRelationManager;
use App\Filament\TenantPortfolio\Resources\ProgrammeResource\RelationManagers\IndicatorSuccessRelationManager;
use App\Models\Programme;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

// Remove the import for the main Registration page - we'll use our own

class ProgrammeResource extends Resource
{
    protected static ?string $model = Programme::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Indicators';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Programme Description')
                            ->columnSpan(5)
                            ->disabled(),
                        Forms\Components\TextInput::make('period')
                            ->label('Programme Duration')
                            ->columnSpan(1)
                            ->disabled(),
                    ])
                    ->columns(6),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->tenantsOfCurrentTenantCluster()
                    ->withCount(['indicatorSuccesses', 'indicatorCompliances']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->description(fn (Programme $record) => Str::limit($record->description, 100, '...'))
                    ->label('Programme Name')
                    ->wrap()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Programme Duration')
                    ->tooltip('Duration in months')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('indicator_successes_count')
                    ->label('Success Indicators')
                    ->color('gray')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('indicator_compliances_count')
                    ->label('Compliance Indicators')
                    ->color('gray')
                    ->badge()
                    ->alignCenter(),
            ])
            ->defaultSort('title', 'asc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Manage')
                    ->icon('heroicon-o-pencil-square'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Note: This relation manager is from the portfolion panel resource
            IndicatorSuccessRelationManager::class,
            IndicatorComplianceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgrammes::route('/'),
            'edit' => Pages\EditProgramme::route('/{record}/edit'),
            'registration' => Pages\Registration::route('/{record}/registration'),
        ];
    }
}
