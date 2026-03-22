<?php

namespace App\Filament\Resources\BillingReports\Tables;

use App\Enums\BillingReportStatus;
use App\Models\BillingReport;
use App\Support\CurrencyConverter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class BillingReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period')
                    ->label(__('Period'))
                    ->state(fn (BillingReport $record): string => match (true) {
                        $record->period_from !== null && $record->period_to !== null => $record->period_from->format('d.m.Y').' – '.$record->period_to->format('d.m.Y'),
                        $record->period_from !== null => $record->period_from->format('d.m.Y'),
                        default => '—',
                    }),

                TextColumn::make('lines_sum_total_amount')
                    ->label(__('Total'))
                    ->state(fn (BillingReport $record): string => $record->lines_sum_total_amount !== null
                        ? CurrencyConverter::format((float) $record->lines_sum_total_amount, $record->currency, 2)
                        : '—')
                    ->weight('semibold')
                    ->sortable(),

                TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('finalized_at')
                    ->label(__('Finalized'))
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(BillingReportStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, BillingReport> $records */
                            $records
                                ->filter(fn (BillingReport $r): bool => $r->isDraft())
                                ->each->delete();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }
}
