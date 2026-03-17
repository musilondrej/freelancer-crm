<?php

namespace App\Filament\Resources\Worklogs\Tables;

use App\Enums\ProjectActivityStatus;
use App\Enums\ProjectActivityType;
use App\Filament\Resources\Worklogs\WorklogResource;
use App\Models\UserSetting;
use App\Models\Worklog;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;

class WorklogsTable
{
    /**
     * @return list<TextColumn|IconColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('title')
                ->searchable()
                ->sortable(),
            TextColumn::make('project.name')
                ->label('Project')
                ->searchable()
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('tracked_minutes')
                ->label('Tracked time')
                ->state(fn (Worklog $record): ?string => TimeDuration::format($record->tracked_minutes))
                ->sortable()
                ->toggleable(),
            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        $ownerId = Filament::auth()->id();
        $dateFormat = UserSetting::dateFormatForUser($ownerId);
        $dateTimeFormat = UserSetting::dateTimeFormatForUser($ownerId);
        $timezone = UserSetting::timezoneForUser($ownerId);

        return $table
            ->queryStringIdentifier('worklogs')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistSortInSession()
            ->defaultSort('started_at', 'desc')
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
                TextColumn::make('priority')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
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
                    ->label('Tracked time')
                    ->state(fn (Worklog $record): ?string => TimeDuration::format($record->tracked_minutes))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('estimated_minutes')
                    ->label('Estimate')
                    ->state(fn (Worklog $record): ?string => TimeDuration::format($record->estimated_minutes))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('due_date')
                    ->date($dateFormat)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime($dateTimeFormat, timezone: $timezone)
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('start_timer')
                        ->label('Start timer')
                        ->icon('heroicon-o-play-circle')
                        ->color('gray')
                        ->visible(function (Worklog $record): bool {
                            if ((bool) $record->is_running) {
                                return false;
                            }

                            if ($record->type !== ProjectActivityType::Hourly) {
                                return false;
                            }

                            return $record->status->isOpen();
                        })
                        ->action(function (Worklog $record) use ($ownerId): void {
                            if ($ownerId === null) {
                                return;
                            }

                            $alreadyRunningTimerExists = Worklog::query()
                                ->where('owner_id', $ownerId)
                                ->whereKeyNot($record->getKey())
                                ->where('type', ProjectActivityType::Hourly->value)
                                ->where('is_running', true)
                                ->whereNull('finished_at')
                                ->exists();

                            if ($alreadyRunningTimerExists) {
                                Notification::make()
                                    ->title('You already have a running timer. Stop it first.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                $record->update([
                                    'status' => ProjectActivityStatus::runningCase(),
                                    'is_running' => true,
                                    'started_at' => $record->started_at ?? now(),
                                    'finished_at' => null,
                                ]);
                            } catch (QueryException) {
                                Notification::make()
                                    ->title('Unable to start timer for this worklog.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Timer started')
                                ->success()
                                ->send();
                        }),
                    Action::make('mark_done')
                        ->label('Mark done')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Worklog $record): bool => ! $record->status->isDone())
                        ->action(function (Worklog $record): void {
                            $record->update([
                                'status' => ProjectActivityStatus::Done,
                                'is_running' => false,
                                'finished_at' => $record->finished_at ?? now(),
                            ]);

                            Notification::make()
                                ->title('Worklog marked as done')
                                ->success()
                                ->send();
                        }),
                    Action::make('mark_invoiced')
                        ->label('Mark invoiced')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->visible(fn (Worklog $record): bool => (bool) $record->is_billable && ! $record->isInvoiced() && $record->isReadyToInvoice())
                        ->action(function (Worklog $record): void {
                            $record->update([
                                'is_invoiced' => true,
                                'invoiced_at' => $record->invoiced_at ?? now(),
                            ]);

                            Notification::make()
                                ->title('Worklog marked as invoiced')
                                ->success()
                                ->send();
                        }),
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
            ->emptyStateHeading('No worklogs yet')
            ->emptyStateDescription('Create the first worklog or start tracking time right away.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateActions([
                Action::make('create_worklog')
                    ->label('Create worklog')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => WorklogResource::getUrl('create'))
                    ->button(),
                Action::make('start_timer')
                    ->label('Start timer')
                    ->icon('heroicon-o-play-circle')
                    ->url(fn (): string => WorklogResource::getUrl('create'))
                    ->color('gray'),
            ]);
    }
}
