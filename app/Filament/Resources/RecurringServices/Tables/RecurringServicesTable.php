<?php

namespace App\Filament\Resources\RecurringServices\Tables;

use App\Enums\RecurringServiceStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecurringServicesTable
{
    /**
     * @return list<TextColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Name'))
                ->searchable()
                ->sortable(),
            TextColumn::make('customer.name')
                ->label(__('Customer'))
                ->searchable()
                ->sortable(),
            TextColumn::make('serviceType.name')
                ->label(__('Category'))
                ->badge()
                ->searchable()
                ->sortable(),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->sortable(),
            TextColumn::make('next_due_on')
                ->label(__('Next due on'))
                ->date()
                ->sortable()
                ->toggleable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serviceType.name')
                    ->label(__('Category'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('billing_model')
                    ->label(__('Billing model'))
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('next_due_on')
                    ->label(__('Next due on'))
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RecurringServiceStatus::class)
                    ->multiple(),
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->label('Customer')
                    ->searchable()
                    ->preload(),
                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', RecurringServiceStatus::Active->value)
                        ->whereDate('next_due_on', '<', today()->toDateString())),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
