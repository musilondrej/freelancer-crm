<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
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

class TimeEntriesTable
{
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

                    return number_format($rate, 2, '.', ' ').' '.$currency;
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

                    return number_format($amount, 2, '.', ' ').' '.$currency;
                })
                ->toggleable(),

        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
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
                    ->query(fn ($query) => $query->where('is_invoiced', true)),
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
                            ->maxLength(255),
                        DatePicker::make('invoiced_at')
                            ->label(__('Invoiced at'))
                            ->default(today()->toDateString())
                            ->required(),
                    ])
                    ->fillForm(fn (TimeEntry $record): array => [
                        'invoice_reference' => $record->invoice_reference,
                        'invoiced_at' => self::toDateString($record->invoiced_at) ?? today()->toDateString(),
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
