<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Models\Invoice;
use App\Support\CurrencyConverter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label(__('Invoice reference'))
                    ->placeholder(__('N/A'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->placeholder(__('N/A'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->placeholder(__('N/A'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label(__('Items'))
                    ->state(fn (Invoice $record): int => $record->items->count())
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label(__('Amount'))
                    ->state(function (Invoice $record): string {
                        $amount = $record->totalAmount();

                        return CurrencyConverter::format($amount, $record->currency, 2);
                    })
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label(__('Invoiced at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('issued_at', 'desc');
    }
}
