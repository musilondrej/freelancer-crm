<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Enums\BillingReportStatus;
use App\Models\BillingReport;
use App\Models\TimeEntry;
use App\Support\CurrencyConverter;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
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
    public static function addToBillingReportBulkAction(): BulkAction
    {
        return BulkAction::make('add_to_billing_report')
            ->label(__('Add to Billing Report'))
            ->icon('heroicon-o-document-text')
            ->color('primary')
            ->schema([
                Select::make('billing_report_id')
                    ->label(__('Billing Report'))
                    ->options(fn (): array => BillingReport::query()
                        ->draft()
                        ->with('customer')
                        ->orderByDesc('created_at')
                        ->get()
                        ->mapWithKeys(fn (BillingReport $report): array => [
                            $report->id => sprintf('%s — %s', $report->title, $report->customer->name),
                        ])
                        ->toArray())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (Collection $records, array $data): void {
                $report = BillingReport::query()->find($data['billing_report_id']);

                if (! $report instanceof BillingReport) {
                    return;
                }

                $attached = $report->addSpecificEntries($records);

                if ($attached === 0) {
                    Notification::make()
                        ->warning()
                        ->title(__('No entries were added'))
                        ->body(__('All selected entries are already in a billing report.'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__(':count entries added to billing report', ['count' => $attached]))
                    ->send();
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
                ->state(fn (TimeEntry $record): bool => $record->effectiveBillable($record->task?->is_billable))
                ->toggleable(),
            IconColumn::make('in_billing_report')
                ->label(__('Billed'))
                ->boolean()
                ->state(fn (TimeEntry $record): bool => $record->isInvoiced())
                ->trueIcon('heroicon-o-document-check')
                ->falseIcon('heroicon-o-minus')
                ->trueColor('success')
                ->falseColor('gray')
                ->toggleable(),
            TextColumn::make('hourly_rate')
                ->label(__('Hourly rate'))
                ->state(function (TimeEntry $record): string {
                    $rate = $record->effectiveHourlyRate();

                    if ($rate === null) {
                        return __('N/A');
                    }

                    return CurrencyConverter::format($rate, $record->effectiveCurrency() ?? 'CZK', 2);
                })
                ->toggleable(),
            TextColumn::make('amount')
                ->label(__('Amount'))
                ->state(function (TimeEntry $record): string {
                    $amount = $record->calculatedAmount();

                    if ($amount === null) {
                        return __('N/A');
                    }

                    return CurrencyConverter::format($amount, $record->effectiveCurrency() ?? 'CZK', 2);
                })
                ->toggleable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['billingReportLines.billingReport']))
            ->columns([
                IconColumn::make('in_report')
                    ->label('')
                    ->state(fn (TimeEntry $record): bool => $record->isLocked())
                    ->boolean()
                    ->trueIcon('heroicon-m-lock-closed')
                    ->falseIcon('')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (TimeEntry $record): ?string => $record->isLocked() ? __('In billing report') : null),
                TextColumn::make('task.title')
                    ->label(__('Task'))
                    ->placeholder(__('No task'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->searchable()
                    ->sortable(),

                ...self::relationColumns(),
            ])
            ->recordClasses(fn (TimeEntry $record): ?string => match (true) {
                $record->isInvoiced() => 'bg-green-50',
                $record->isLocked() => 'bg-yellow-50',
                default => null,
            })
            ->filters([
                Filter::make('running')
                    ->label(__('Running'))
                    ->query(fn ($query) => $query->running()),
                Filter::make('ready_to_bill')
                    ->label(__('Ready to bill'))
                    ->query(fn ($query) => $query->readyToInvoice()),
                Filter::make('billed')
                    ->label(__('Billed'))
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'billingReportLines.billingReport',
                        fn (Builder $q): Builder => $q->where('status', BillingReportStatus::Finalized)
                    )),
                Filter::make('in_draft_report')
                    ->label(__('In draft report'))
                    ->query(fn ($query) => $query->locked()),
                TrashedFilter::make(),
            ])
            ->groups([
                Group::make('project.customer.name')
                    ->label(__('Customer')),
                Group::make('project.name')
                    ->label(__('Project')),
                Group::make('started_at')
                    ->label(__('Start date'))
                    ->date(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (TimeEntry $record): bool => ! $record->isLocked()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::addToBillingReportBulkAction(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
