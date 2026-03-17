<?php

namespace App\Filament\Resources\Tags\RelationManagers;

use App\Filament\Resources\Worklogs\Tables\WorklogsTable;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProjectActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'projectActivities';

    protected static ?string $title = 'Worklogs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('started_at', 'desc')
            ->columns(WorklogsTable::relationColumns())
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                AttachAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
