<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Models\TimeEntry;
use App\Support\CurrencyConverter;
use App\Support\Invoicing\InvoiceIssuer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TimeEntriesTable
{
    public static function invoiceBulkAction(): BulkAction
    {
        return BulkAction::make('invoice_selected')
            ->label(__('Invoice selected'))
            ->icon('heroicon-o-banknotes')
            ->color('info')
            ->form([
                TextInput::make('invoice_reference')
                    ->label(__('Invoice reference'))
                    ->required()
                    ->maxLength(255),
                DatePicker::make('invoiced_at')
                    ->label(__('Invoiced at'))
                    ->default(today()->toDateString())
                    ->required(),
            ])
            ->action(function (BulkAction $action, Collection $records, array $data): void {
                $readyRecords = $records->filter(function ($record) use ($action): bool {
                    if (! $record instanceof TimeEntry) {
                        return false;
                    }

                    if (! $record->isReadyToInvoice()) {
                        $action->reportBulkProcessingFailure(
                            'not_ready_to_invoice',
                            message: function (int $failureCount, int $totalCount): string {
                                if (($failureCount === 1) && ($totalCount === 1)) {
                                    return __('The selected time entry is not ready to invoice.');
                                }

                                if ($failureCount === $totalCount) {
                                    return __('All selected time entries are already invoiced, running, or not billable.');
                                }

                                if ($failureCount === 1) {
                                    return __('One selected time entry was skipped because it is not ready to invoice.');
                                }

                                return __(':count selected time entries were skipped because they are not ready to invoice.', ['count' => $failureCount]);
                            },
                        );

                        return false;
                    }

                    return true;
                });

                if ($readyRecords->isEmpty()) {
                    return;
                }

                resolve(InvoiceIssuer::class)->issue(
                    $readyRecords->all(),
                    $data['invoice_reference'] ?? null,
                    $data['invoiced_at'] ?? null,
                );
            })
            ->successNotificationTitle(__('Selected time entries assigned to invoice'))
            ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                if ($successCount > 0) {
                    return __(':count of :total selected time entries were assigned to an invoice.', [
                        'count' => $successCount,
                        'total' => $totalCount,
                    ]);
                }

                return __('No selected time entries were ready to invoice.');
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return list<TextColumn|IconColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('started_at')
                ->label(__('Start date'))
                ->dateTime()
                ->sortable()
                ->toggleable(),
            TextColumn::make('minutes')
                ->label(__('Hours'))
                ->state(fn (TimeEntry $record): float => $record->resolvedHours())
                ->tooltip(fn (TimeEntry $record): string => sprintf('%d min', $record->resolvedMinutes()))
                ->sortable(),
            IconColumn::make('is_billable')
                ->label(__('Billable'))
                ->boolean()
                ->state(fn (TimeEntry $record): bool => $record->effectiveBillable((bool) $record->task?->is_billable))
                ->toggleable(),
            IconColumn::make('is_invoiced')
                ->label(__('Invoiced'))
                ->boolean()
                ->state(fn (TimeEntry $record): bool => $record->isInvoiced())
                ->sortable()
                ->toggleable(),
            TextColumn::make('hourly_rate')
                ->label(__('Hourly rate'))
                ->state(function (TimeEntry $record): string {
                    $rate = $record->effectiveHourlyRate();

                    if ($rate === null) {
                        return __('N/A');
                    }

                    $currency = $record->task?->effectiveCurrency() ?? 'CZK';

                    return CurrencyConverter::format($rate, $currency, 2);
                })
                ->toggleable(),
            TextColumn::make('amount')
                ->label(__('Amount'))
                ->state(function (TimeEntry $record): string {
                    $amount = $record->calculatedAmount();

                    if ($amount === null) {
                        return __('N/A');
                    }

                    $currency = $record->task?->effectiveCurrency() ?? 'CZK';

                    return CurrencyConverter::format($amount, $currency, 2);
                })
                ->toggleable(),

        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('currentInvoiceItem.invoice'))
            ->columns([
                TextColumn::make('task.title')
                    ->label(__('Task'))
                    ->description(fn (TimeEntry $record): ?string => $record->task?->project?->name)
                    ->searchable()
                    ->sortable(),

                ...self::relationColumns(),
            ])
            ->filters([
                Filter::make('running')
                    ->label(__('Running'))
                    ->query(fn ($query) => $query->running()),
                Filter::make('ready_to_invoice')
                    ->label(__('Ready to invoice'))
                    ->query(fn ($query) => $query->readyToInvoice()),
                Filter::make('invoiced')
                    ->label(__('Invoiced'))
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $builder): void {
                        $builder->whereHas('invoiceItems')
                            ->orWhere('is_invoiced', true)
                            ->orWhere('invoice_reference', '<>', '')
                            ->orWhereNotNull('invoiced_at');
                    })),
                TrashedFilter::make(),
            ])
            ->groups([
                Group::make('task.project.customer.name')
                    ->label(__('Customer')),
                Group::make('task.project.name')
                    ->label(__('Project')),
                Group::make('started_at')
                    ->label(__('Start date'))
                    ->date(),
            ])
            ->recordActions([
                Action::make('mark_invoiced')
                    ->label(__('Mark invoiced'))
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (TimeEntry $record): bool => $record->isReadyToInvoice())
                    ->schema([
                        TextInput::make('invoice_reference')
                            ->label(__('Invoice reference'))
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('invoiced_at')
                            ->label(__('Invoiced at'))
                            ->default(today()->toDateString())
                            ->required(),
                    ])
                    ->fillForm(fn (): array => [
                        'invoice_reference' => '',
                        'invoiced_at' => today()->toDateString(),
                    ])
                    ->action(function (TimeEntry $record, array $data): void {
                        $record->markAsInvoiced(
                            invoiceReference: $data['invoice_reference'] ?? null,
                            invoicedAt: $data['invoiced_at'] ?? null,
                        );

                        Notification::make()
                            ->success()
                            ->title(__('Time entry marked as invoiced'))
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::invoiceBulkAction(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('started_at', 'desc');
    }

    private static function toDateString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value)->toDateString();
        }

        return null;
    }
}
