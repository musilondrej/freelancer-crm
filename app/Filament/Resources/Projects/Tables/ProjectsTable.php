<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Project;
use App\Models\ProjectStatusOption;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Project $record): string => $record->resolvedStatusLabel())
                    ->color(fn (Project $record): string => $record->resolvedStatusColor())
                    ->sortable(),
                TextColumn::make('pricing_model')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('target_end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => ProjectStatusOption::optionsForOwner(Filament::auth()->id()))
                    ->multiple(),
                SelectFilter::make('client_id')
                    ->relationship('customer', 'name')
                    ->label('Customer')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->groups([
                Group::make('customer.name')
                    ->label('Customer'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
