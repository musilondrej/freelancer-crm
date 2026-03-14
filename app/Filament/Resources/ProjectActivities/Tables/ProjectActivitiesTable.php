<?php

namespace App\Filament\Resources\ProjectActivities\Tables;

use App\Models\ProjectActivity;
use App\Models\ProjectActivityStatusOption;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('activity.name')
                    ->label('Activity')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectActivity $record): string => $record->resolvedStatusLabel())
                    ->color(fn (ProjectActivity $record): string => $record->resolvedStatusColor())
                    ->sortable(),
                IconColumn::make('is_billable')
                    ->boolean()
                    ->label('Billable')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_invoiced')
                    ->boolean()
                    ->label('Invoiced')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tracked_minutes')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('ready_to_invoice')
                    ->label('Ready to Invoice')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', ProjectActivityStatusOption::doneCodesForOwner(Filament::auth()->id()))
                        ->where('is_billable', true)
                        ->where('is_invoiced', false)
                        ->whereNull('invoice_reference')
                        ->whereNull('invoiced_at')),
                TrashedFilter::make(),
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
