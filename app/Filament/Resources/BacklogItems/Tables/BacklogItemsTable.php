<?php

namespace App\Filament\Resources\BacklogItems\Tables;

use App\Enums\BacklogItemStatus;
use App\Filament\Resources\BacklogItems\BacklogItemResource;
use App\Filament\Resources\Worklogs\WorklogResource;
use App\Models\BacklogItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class BacklogItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('backlog')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistSortInSession()
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('activity.name')
                    ->label('Activity template')
                    ->searchable()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BacklogItem $record): string => $record->resolvedStatusLabel())
                    ->color(fn (BacklogItem $record): string => $record->resolvedStatusColor())
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (BacklogItem $record): string => $record->resolvedPriorityLabel())
                    ->color(fn (BacklogItem $record): string => $record->resolvedPriorityColor())
                    ->sortable(),
                TextColumn::make('estimated_minutes')
                    ->label('Estimated')
                    ->state(fn (?int $state): string => $state !== null ? sprintf('%d min', $state) : '-')
                    ->sortable(),
                TextColumn::make('worklogs_count')
                    ->counts('worklogs')
                    ->label('Worklogs')
                    ->badge()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('convert_to_worklog')
                    ->label('Convert to worklog')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (BacklogItem $record): bool => $record->converted_at === null && in_array($record->resolvedStatusCode(), BacklogItemStatus::openValues(), true))
                    ->action(function (BacklogItem $record): void {
                        try {
                            $record->convertToWorklog();
                        } catch (ValidationException $validationException) {
                            Notification::make()
                                ->title((string) collect($validationException->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Backlog item converted to worklog')
                            ->success()
                            ->send();
                    }),
                Action::make('open_worklogs')
                    ->label('Open worklogs')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (BacklogItem $record): string => WorklogResource::getUrl('index', [
                        'tableFilters' => [
                            'backlog_item_id' => [
                                'value' => $record->getKey(),
                            ],
                        ],
                    ]))
                    ->visible(fn (BacklogItem $record): bool => $record->worklogs()->exists()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No backlog items yet')
            ->emptyStateDescription('Plan upcoming work first, then convert it to a worklog when execution starts.')
            ->emptyStateIcon('heroicon-o-queue-list')
            ->emptyStateActions([
                Action::make('create_backlog_item')
                    ->label('Create backlog item')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => BacklogItemResource::getUrl('create'))
                    ->button(),
            ]);
    }
}
