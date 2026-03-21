<?php

namespace App\Filament\Pages\Timesheets;

use App\Enums\Currency;
use App\Models\TimeEntry;
use App\Support\CurrencyConverter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimesheetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                TimeEntry::query()
                    ->billed()
                    ->with(['project.customer', 'task', 'owner'])
                    ->orderBy('invoiced_at', 'desc')
                    ->orderBy('invoice_reference', 'desc')
            )
            ->groups([
                Group::make('invoice_reference')
                    ->label(__('Invoice reference'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
            ->defaultGroup('invoice_reference')
            ->columns(self::columns())
            ->filters(self::filters())
            ->striped();
    }

    /**
     * @return list<TextColumn>
     */
    private static function columns(): array
    {
        $displayCurrency = Currency::userDefault();

        return [
            TextColumn::make('project.customer.name')
                ->label(__('Customer'))
                ->searchable()
                ->sortable(),
            TextColumn::make('project.name')
                ->label(__('Project'))
                ->searchable()
                ->sortable(),
            TextColumn::make('task.title')
                ->label(__('Task'))
                ->placeholder(__('No task'))
                ->searchable(),
            TextColumn::make('description')
                ->label(__('Description'))
                ->placeholder('—')
                ->limit(60)
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('started_at')
                ->label(__('Date'))
                ->date()
                ->sortable(),
            TextColumn::make('hours')
                ->label(__('Hours'))
                ->state(fn (TimeEntry $record): string => number_format($record->resolvedHours(), 1, '.', ' ').' h')
                ->tooltip(fn (TimeEntry $record): string => sprintf('%d min', $record->resolvedMinutes())),
            TextColumn::make('hourly_rate')
                ->label(__('Rate'))
                ->state(function (TimeEntry $record) use ($displayCurrency): string {
                    $rate = $record->effectiveHourlyRate();

                    if ($rate === null) {
                        return '—';
                    }

                    $currency = $record->effectiveCurrency() ?? $displayCurrency->value;

                    return CurrencyConverter::format($rate, $currency, 0);
                }),
            TextColumn::make('amount')
                ->label(__('Amount'))
                ->state(function (TimeEntry $record) use ($displayCurrency): string {
                    $amount = $record->calculatedAmount();

                    if ($amount === null) {
                        return '—';
                    }

                    $currency = $record->effectiveCurrency() ?? $displayCurrency->value;

                    return CurrencyConverter::format($amount, $currency, 2);
                })
                ->weight('semibold'),
            TextColumn::make('invoiced_at')
                ->label(__('Invoiced at'))
                ->date()
                ->sortable()
                ->toggleable(),
        ];
    }

    /**
     * @return list<Filter>
     */
    private static function filters(): array
    {
        return [
            Filter::make('customer')
                ->form([
                    TextInput::make('customer_name')
                        ->label(__('Customer name'))
                        ->placeholder(__('Search by customer name')),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['customer_name'],
                    fn (Builder $builder, string $value): Builder => $builder->whereHas(
                        'project.customer',
                        fn (Builder $customer): Builder => $customer->where('name', 'like', sprintf('%%%s%%', $value))
                    )
                )),
            Filter::make('invoiced_period')
                ->form([
                    DatePicker::make('invoiced_from')
                        ->label(__('Invoiced from'))
                        ->native(false),
                    DatePicker::make('invoiced_until')
                        ->label(__('Invoiced until'))
                        ->native(false),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(
                        $data['invoiced_from'],
                        fn (Builder $builder, string $date): Builder => $builder->whereDate('invoiced_at', '>=', $date)
                    )
                    ->when(
                        $data['invoiced_until'],
                        fn (Builder $builder, string $date): Builder => $builder->whereDate('invoiced_at', '<=', $date)
                    )),
        ];
    }
}
