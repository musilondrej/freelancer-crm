<?php

namespace App\Filament\Resources\BacklogItems\Tables;

use App\Enums\BacklogItemStatus;
use App\Filament\Resources\BacklogItems\BacklogItemResource;
use App\Filament\Resources\Worklogs\WorklogResource;
use App\Models\BacklogItem;
use App\Models\UserSetting;
use App\Support\TimeDuration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class BacklogItemsTable
{
    /**
     * @return list<TextColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('title')
                ->searchable()
                ->sortable()
                ->limit(60),
            TextColumn::make('project.name')
                ->label('Project')
                ->searchable()
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('priority')
                ->badge()
                ->sortable(),
            TextColumn::make('due_date')
                ->label('Due')
                ->date()
                ->sortable()
                ->toggleable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        $ownerId = Filament::auth()->id();
        $dateFormat = UserSetting::dateFormatForUser($ownerId);

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
                    ->label('Activity')
                    ->searchable()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('estimated_minutes')
                    ->label('Estimate')
                    ->state(fn (BacklogItem $record): ?string => TimeDuration::format($record->estimated_minutes))
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->date($dateFormat)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BacklogItemStatus::class)
                    ->multiple(),
                SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->label('Project')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('convert_to_worklog')
                        ->label('Convert to worklog')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (BacklogItem $record): bool => $record->converted_at === null && $record->status->isOpen())
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
                    Action::make('mark_done')
                        ->label('Mark done')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (BacklogItem $record): bool => ! $record->status->isDone())
                        ->action(function (BacklogItem $record): void {
                            $record->update(['status' => BacklogItemStatus::Done]);

                            Notification::make()
                                ->title('Backlog item marked as done')
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
                ]),
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
