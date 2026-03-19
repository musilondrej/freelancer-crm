<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\TimeEntries\Tables\TimeEntriesTable;
use App\Models\TimeEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    protected static ?string $title = 'Time Entries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('started_at')
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('task.title')
                    ->label(__('Task'))
                    ->placeholder(__('No task'))
                    ->description(fn (TimeEntry $record): ?string => $record->task?->activity?->name)
                    ->searchable()
                    ->sortable(),
                ...TimeEntriesTable::relationColumns(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TimeEntriesTable::invoiceBulkAction(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
                ->with(['project.customer', 'task.activity', 'task.project.customer']));
    }
}
