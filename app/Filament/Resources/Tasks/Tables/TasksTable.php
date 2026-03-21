<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\UserSetting;
use App\Support\CurrencyConverter;
use App\Support\Filament\FilteredByOwner;
use App\Support\TimeDuration;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

class TasksTable
{
    /**
     * @return list<TextColumn|IconColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('title')
                ->label(__('Title'))
                ->searchable()
                ->sortable(),
            TextColumn::make('project.name')
                ->label(__('Project'))
                ->searchable()
                ->sortable(),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->sortable(),
            TextColumn::make('tracked_time')
                ->label(__('Tracked time'))
                ->state(fn (Task $record): ?string => TimeDuration::format($record->totalTrackedMinutes()))
                ->sortable()
                ->toggleable(),
            TextColumn::make('created_at')
                ->label(__('Created at'))
                ->dateTime()
                ->sortable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        $ownerId = FilteredByOwner::ownerId();
        $dateFormat = UserSetting::dateFormatForUser($ownerId);
        $dateTimeFormat = UserSetting::dateTimeFormatForUser($ownerId);
        $timezone = UserSetting::timezoneForUser($ownerId);

        return $table
            ->queryStringIdentifier('tasks')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistSortInSession()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('billing_model')
                    ->label(__('Billing'))
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_billable')
                    ->boolean()
                    ->label(__('Billable'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_invoiced')
                    ->boolean()
                    ->label(__('Invoiced'))
                    ->state(fn (Task $record): bool => $record->isInvoiced())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->state(function (Task $record): string {
                        $amount = $record->calculatedAmount();
                        $currency = $record->effectiveCurrency();

                        if ($amount === null || $currency === null) {
                            return __('N/A');
                        }

                        return CurrencyConverter::format($amount, $currency, 2);
                    })
                    ->toggleable(),
                TextColumn::make('tracked_time')
                    ->label(__('Tracked time'))
                    ->state(fn (Task $record): ?string => TimeDuration::format($record->totalTrackedMinutes()))
                    ->toggleable(),
                TextColumn::make('estimated_minutes')
                    ->label(__('Estimate'))
                    ->state(fn (Task $record): ?string => TimeDuration::format($record->estimated_minutes))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('due_date')
                    ->label(__('Due date'))
                    ->date($dateFormat)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('invoice_reference')
                    ->label(__('Invoice reference'))
                    ->state(fn (Task $record): ?string => $record->resolvedInvoiceReference())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('invoiced_at')
                    ->label(__('Invoiced at'))
                    ->state(fn (Task $record): ?CarbonInterface => $record->resolvedInvoicedAt())
                    ->dateTime($dateTimeFormat, timezone: $timezone)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime($dateTimeFormat, timezone: $timezone)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(TaskStatus::class)
                    ->multiple(),
                SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->label(__('Project'))
                    ->searchable()
                    ->preload(),
                Filter::make('ready_to_invoice')
                    ->label(__('Ready to invoice'))
                    ->query(function (Builder $query) use ($ownerId): Builder {
                        /** @var Builder<Task> $query */
                        return $query
                            ->when($ownerId !== null, fn (Builder $builder): Builder => $builder->where('owner_id', $ownerId))
                            ->billable()
                            ->done()
                            ->where('is_invoiced', false)
                            ->whereNull('invoice_reference')
                            ->whereNull('invoiced_at')
                            ->whereNotNull('completed_at');
                    }),
                Filter::make('completed_range')
                    ->label(__('Completed in period'))
                    ->form([
                        DatePicker::make('completed_from')
                            ->label(__('From')),
                        DatePicker::make('completed_until')
                            ->label(__('To')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            filled($data['completed_from'] ?? null),
                            fn (Builder $builder): Builder => $builder->whereDate('completed_at', '>=', $data['completed_from']),
                        )
                        ->when(
                            filled($data['completed_until'] ?? null),
                            fn (Builder $builder): Builder => $builder->whereDate('completed_at', '<=', $data['completed_until']),
                        )),
                TrashedFilter::make(),
            ])
            ->recordClasses(fn (Task $record): ?string => match (true) {
                $record->status->isDone() => 'opacity-60',
                self::isPastDate($record->due_date) => 'bg-danger-50 dark:bg-danger-950/50',
                default => null,
            })
            ->recordActions([
                ActionGroup::make([
                    Action::make('start_timer')
                        ->label('Start timer')
                        ->icon('heroicon-o-play-circle')
                        ->color('gray')
                        ->visible(function (Task $record): bool {
                            if (! $record->isHourly()) {
                                return false;
                            }

                            if (! $record->status->isOpen()) {
                                return false;
                            }

                            return ! $record->timeEntries()->running()->exists();
                        })
                        ->action(function (Task $record) use ($ownerId): void {
                            if ($ownerId === null) {
                                return;
                            }

                            $alreadyRunningTimerExists = TimeEntry::query()
                                ->where('owner_id', $ownerId)
                                ->running()
                                ->exists();

                            if ($alreadyRunningTimerExists) {
                                Notification::make()
                                    ->title('You already have a running timer. Stop it first.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                TimeEntry::query()->create([
                                    'owner_id' => $ownerId,
                                    'project_id' => $record->project_id,
                                    'task_id' => $record->id,
                                    'started_at' => now(),
                                ]);
                            } catch (QueryException) {
                                Notification::make()
                                    ->title('Unable to start timer for this task.')
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
                        ->visible(fn (Task $record): bool => ! $record->status->isDone())
                        ->action(function (Task $record): void {
                            $record->update([
                                'status' => TaskStatus::Done,
                                'completed_at' => $record->completed_at ?? now(),
                            ]);

                            Notification::make()
                                ->title(__('Task marked as done'))
                                ->success()
                                ->send();
                        }),
                    Action::make('mark_invoiced')
                        ->label(__('Mark as invoiced'))
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->visible(fn (Task $record): bool => (bool) $record->is_billable && ! $record->isInvoiced() && $record->isReadyToInvoice())
                        ->schema([
                            TextInput::make('invoice_reference')
                                ->label(__('Invoice reference'))
                                ->maxLength(64),
                            DatePicker::make('invoiced_at')
                                ->label(__('Invoiced at'))
                                ->default(today()->toDateString())
                                ->required(),
                        ])
                        ->fillForm(fn (): array => [
                            'invoice_reference' => null,
                            'invoiced_at' => today()->toDateString(),
                        ])
                        ->action(function (Task $record, array $data): void {
                            $record->markAsInvoiced(
                                invoiceReference: $data['invoice_reference'] ?? null,
                                invoicedAt: $data['invoiced_at'] ?? null,
                            );

                            Notification::make()
                                ->title(__('Task marked as invoiced'))
                                ->success()
                                ->send();
                        }),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('invoice_selected')
                        ->label(__('Invoice selected'))
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->schema([
                            TextInput::make('invoice_reference')
                                ->label(__('Invoice reference'))
                                ->maxLength(64),
                            DatePicker::make('invoiced_at')
                                ->label(__('Invoiced at'))
                                ->default(today()->toDateString())
                                ->required(),
                        ])
                        ->action(function (BulkAction $action, Collection $records, array $data): void {
                            $readyRecords = $records->filter(function ($record) use ($action): bool {
                                if (! $record instanceof Task) {
                                    return false;
                                }

                                if (! $record->isReadyToInvoice()) {
                                    $action->reportBulkProcessingFailure(
                                        'not_ready_to_invoice',
                                        message: function (int $failureCount, int $totalCount): string {
                                            if (($failureCount === 1) && ($totalCount === 1)) {
                                                return __('The selected task is not ready to invoice.');
                                            }

                                            if ($failureCount === $totalCount) {
                                                return __('All selected tasks are already invoiced, not billable, or not done yet.');
                                            }

                                            if ($failureCount === 1) {
                                                return __('One selected task was skipped because it is not ready to invoice.');
                                            }

                                            return __(':count selected tasks were skipped because they are not ready to invoice.', ['count' => $failureCount]);
                                        },
                                    );

                                    return false;
                                }

                                return true;
                            });

                            if ($readyRecords->isEmpty()) {
                                return;
                            }

                            foreach ($readyRecords as $record) {
                                if (! $record instanceof Task) {
                                    continue;
                                }

                                $record->markAsInvoiced(
                                    $data['invoice_reference'] ?? null,
                                    $data['invoiced_at'] ?? null,
                                );
                            }
                        })
                        ->successNotificationTitle(__('Selected tasks marked as invoiced'))
                        ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                            if ($successCount > 0) {
                                return __(':count of :total selected tasks were assigned to an invoice.', [
                                    'count' => $successCount,
                                    'total' => $totalCount,
                                ]);
                            }

                            return __('No selected tasks were ready to invoice.');
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No tasks yet')
            ->emptyStateDescription('Create the first task or start tracking time right away.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateActions([
                Action::make('create_task')
                    ->label('Create task')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => TaskResource::getUrl('create'))
                    ->button(),
                Action::make('start_timer')
                    ->label('Start timer')
                    ->icon('heroicon-o-play-circle')
                    ->url(fn (): string => TaskResource::getUrl('create'))
                    ->color('gray'),
            ]);
    }

    private static function isPastDate(mixed $value): bool
    {
        if ($value instanceof CarbonInterface) {
            return $value->isPast();
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value)->isPast();
        }

        return false;
    }
}
