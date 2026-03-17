<?php

namespace App\Filament\Resources\BacklogItems\RelationManagers;

use App\Filament\Resources\Worklogs\Tables\WorklogsTable;
use App\Filament\Resources\Worklogs\WorklogResource;
use App\Models\BacklogItem;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class WorklogsRelationManager extends RelationManager
{
    protected static string $relationship = 'worklogs';

    protected static ?string $relatedResource = WorklogResource::class;

    public function table(Table $table): Table
    {
        /** @var BacklogItem $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns(WorklogsTable::relationColumns())
            ->headerActions([
                Action::make('create_worklog')
                    ->label('Create worklog')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => WorklogResource::getUrl('create', [
                        'project_id' => $ownerRecord->project_id,
                        'activity_id' => $ownerRecord->activity_id,
                        'backlog_item_id' => $ownerRecord->getKey(),
                    ]))
                    ->button(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
