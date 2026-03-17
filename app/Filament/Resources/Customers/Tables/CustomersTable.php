<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\CustomerStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    /**
     * @return list<TextColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('email')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('hourly_rate_with_currency')
                ->label('Hourly rate')
                ->sortable(
                    query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('hourly_rate', $direction)
                        ->orderBy('billing_currency', $direction),
                )
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('hourly_rate_with_currency')
                    ->label('Hourly rate')
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('hourly_rate', $direction)
                            ->orderBy('billing_currency', $direction),
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CustomerStatus::class)
                    ->multiple(),
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
