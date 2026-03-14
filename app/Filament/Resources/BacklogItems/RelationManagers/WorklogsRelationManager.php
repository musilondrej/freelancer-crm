<?php

namespace App\Filament\Resources\BacklogItems\RelationManagers;

use App\Filament\Resources\ProjectActivities\ProjectActivityResource;
use App\Models\BacklogItem;
use App\Models\ProjectActivity;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorklogsRelationManager extends RelationManager
{
    protected static string $relationship = 'worklogs';

    protected static ?string $relatedResource = ProjectActivityResource::class;

    public function table(Table $table): Table
    {
        /** @var BacklogItem $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectActivity $record): string => $record->resolvedStatusLabel())
                    ->color(fn (ProjectActivity $record): string => $record->resolvedStatusColor()),
                TextColumn::make('tracked_minutes')
                    ->label('Tracked minutes')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('create_worklog')
                    ->label('Create worklog')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => ProjectActivityResource::getUrl('create', [
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
