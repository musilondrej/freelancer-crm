<?php

namespace App\Filament\Resources\BacklogItems\RelationManagers;

use App\Filament\Resources\Worklogs\WorklogResource;
use App\Models\BacklogItem;
use App\Models\UserSetting;
use App\Models\Worklog;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorklogsRelationManager extends RelationManager
{
    protected static string $relationship = 'worklogs';

    protected static ?string $relatedResource = WorklogResource::class;

    public function table(Table $table): Table
    {
        /** @var BacklogItem $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();
        $userId = Filament::auth()->id();
        $dateTimeFormat = UserSetting::dateTimeFormatForUser($userId);
        $timezone = UserSetting::timezoneForUser($userId);

        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Worklog $record): string => $record->resolvedStatusLabel())
                    ->color(fn (Worklog $record): string => $record->resolvedStatusColor()),
                TextColumn::make('tracked_minutes')
                    ->label('Tracked time')
                    ->state(fn (Worklog $record): string => $record->trackedDurationLabel())
                    ->description(fn (Worklog $record): ?string => $record->tracked_minutes !== null ? $record->trackedMinutesWithSuffix() : null),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime($dateTimeFormat, timezone: $timezone)
                    ->sortable(),
            ])
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
