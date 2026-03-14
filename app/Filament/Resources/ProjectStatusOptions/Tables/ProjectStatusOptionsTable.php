<?php

namespace App\Filament\Resources\ProjectStatusOptions\Tables;

use App\Models\ProjectStatusOption;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectStatusOptionsTable
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
                IconColumn::make('is_trackable')
                    ->label('Trackable')
                    ->boolean(),
                TextColumn::make('projects_in_use')
                    ->label('In use')
                    ->state(fn (ProjectStatusOption $record): int => $record->projectsUsingStatusCount())
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
                    ->disabled(fn (ProjectStatusOption $record): bool => $record->isUsedByProjects())
                    ->tooltip(fn (ProjectStatusOption $record): ?string => $record->isUsedByProjects()
                        ? 'Cannot delete status that is assigned to projects.'
                        : null),
            ])
            ->defaultSort('sort_order');
    }
}
