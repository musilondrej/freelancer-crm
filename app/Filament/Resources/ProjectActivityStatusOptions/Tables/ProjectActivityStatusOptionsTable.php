<?php

namespace App\Filament\Resources\ProjectActivityStatusOptions\Tables;

use App\Models\ProjectActivityStatusOption;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectActivityStatusOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('color')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                IconColumn::make('is_open')
                    ->label('Open')
                    ->boolean(),
                IconColumn::make('is_done')
                    ->label('Done')
                    ->boolean(),
                IconColumn::make('is_cancelled')
                    ->label('Cancelled')
                    ->boolean(),
                IconColumn::make('is_running')
                    ->label('Running')
                    ->boolean(),
                TextColumn::make('activities_in_use')
                    ->label('In use')
                    ->state(fn (ProjectActivityStatusOption $record): int => $record->activitiesUsingStatusCount())
                    ->numeric(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (ProjectActivityStatusOption $record): bool => $record->isUsedByActivities())
                    ->tooltip(fn (ProjectActivityStatusOption $record): ?string => $record->isUsedByActivities()
                        ? 'Cannot delete status that is assigned to worklogs.'
                        : null),
            ])
            ->defaultSort('sort_order');
    }
}
