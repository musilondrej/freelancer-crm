<?php

namespace App\Filament\Resources\ProjectActivities\Tables;

use App\Enums\ProjectActivityType;
use App\Filament\Resources\ProjectActivities\ProjectActivityResource;
use App\Models\ProjectActivity;
use App\Models\ProjectActivityStatusOption;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

class ProjectActivitiesTable
{
    public static function configure(Table $table): Table
    {
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
                    ->label('Activity template')
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
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('today')
                    ->label('Today')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $builder): void {
                        $builder
                            ->whereDate('started_at', today()->toDateString())
                            ->orWhere(function (Builder $orBuilder): void {
                                $orBuilder
                                    ->whereNull('started_at')
                                    ->whereDate('created_at', today()->toDateString());
                            });
                    })),
                Filter::make('this_week')
                    ->label('This week')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $builder): void {
                        $builder
                            ->whereBetween('started_at', [today()->startOfWeek(), today()->endOfWeek()])
                            ->orWhere(function (Builder $orBuilder): void {
                                $orBuilder
                                    ->whereNull('started_at')
                                    ->whereBetween('created_at', [today()->startOfWeek(), today()->endOfWeek()]);
                            });
                    })),
                Filter::make('overdue')
                    ->label('Overdue')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', today()->toDateString())
                        ->whereIn('status', ProjectActivityStatusOption::openCodesForOwner(Filament::auth()->id()))),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => self::worklogStatusOptions(Filament::auth()->id()))
                    ->multiple(),
                SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->label('Project')
                    ->searchable()
                    ->preload(),
                Filter::make('ready_to_invoice')
                    ->label('Ready to Invoice')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', ProjectActivityStatusOption::doneCodesForOwner(Filament::auth()->id()))
                        ->where('is_billable', true)
                        ->where('is_invoiced', false)
                        ->whereNull('invoice_reference')
                        ->whereNull('invoiced_at')),
                TrashedFilter::make(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->recordActions([
                Action::make('start_timer')
                    ->label('Start timer')
                    ->icon('heroicon-o-play-circle')
                    ->color('gray')
                    ->visible(function (ProjectActivity $record): bool {
                        $ownerId = Filament::auth()->id();

                        if ($ownerId === null || (bool) $record->is_running) {
                            return false;
                        }

                        $resolvedType = (string) $record->getRawOriginal('type');

                        if ($resolvedType !== ProjectActivityType::Hourly->value) {
                            return false;
                        }

                        return in_array($record->resolvedStatusCode(), ProjectActivityStatusOption::openCodesForOwner($ownerId), true);
                    })
                    ->action(function (ProjectActivity $record): void {
                        $ownerId = Filament::auth()->id();

                        if ($ownerId === null) {
                            return;
                        }

                        $alreadyRunningTimerExists = ProjectActivity::query()
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
                                'status' => ProjectActivityStatusOption::runningCodeForOwner($ownerId),
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
                    ->visible(fn (ProjectActivity $record): bool => ! in_array(
                        $record->resolvedStatusCode(),
                        ProjectActivityStatusOption::doneCodesForOwner(Filament::auth()->id()),
                        true,
                    ))
                    ->action(function (ProjectActivity $record): void {
                        $ownerId = Filament::auth()->id();

                        $doneStatusCode = ProjectActivityStatusOption::doneCodesForOwner($ownerId)[0]
                            ?? ProjectActivityStatusOption::defaultCodeForOwner($ownerId);

                        $record->update([
                            'status' => $doneStatusCode,
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
                    ->visible(fn (ProjectActivity $record): bool => (bool) $record->is_billable && ! $record->isInvoiced() && $record->isReadyToInvoice(Filament::auth()->id()))
                    ->action(function (ProjectActivity $record): void {
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
                    ->url(fn (): string => ProjectActivityResource::getUrl('create'))
                    ->button(),
                Action::make('start_timer')
                    ->label('Start timer')
                    ->icon('heroicon-o-play-circle')
                    ->url(fn (): string => ProjectActivityResource::getUrl('create'))
                    ->color('gray'),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function worklogStatusOptions(?int $ownerId): array
    {
        $allowedStatusCodes = [
            'in_progress',
            'done',
            'cancelled',
        ];

        return collect(ProjectActivityStatusOption::optionsForOwner($ownerId))
            ->only($allowedStatusCodes)
            ->all();
    }
}
