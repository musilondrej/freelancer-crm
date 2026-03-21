<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    /**
     * @return list<TextColumn|IconColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Name'))
                ->searchable()
                ->sortable(),
            IconColumn::make('is_active')
                ->label(__('Active'))
                ->boolean()
                ->sortable(),
            TextColumn::make('billing_currency')
                ->label(__('Currency'))
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('projects_count')
                ->label(__('Projects'))
                ->counts('projects')
                ->sortable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Customer $record): ?string => $record->legal_name),
                TextColumn::make('email')
                    ->label(__('E-mail'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('billing_currency')
                    ->label(__('Currency'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hourly_rate_with_currency')
                    ->label(__('Hourly rate'))
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('hourly_rate', $direction)
                            ->orderBy('billing_currency', $direction),
                    ),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('active')
                    ->label(__('Active'))
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->default(),
                Filter::make('inactive')
                    ->label(__('Inactive'))
                    ->query(fn (Builder $query): Builder => $query->where('is_active', false)),
                TrashedFilter::make(),
            ])
            ->groups([
                Group::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('mark_active')
                        ->label(__('Mark active'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Customer $record): bool => ! $record->is_active)
                        ->action(function (Customer $record): void {
                            $record->update(['is_active' => true]);

                            Notification::make()
                                ->title(__('Customer marked as active'))
                                ->success()
                                ->send();
                        }),
                    Action::make('mark_inactive')
                        ->label(__('Mark inactive'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('gray')
                        ->visible(fn (Customer $record): bool => $record->is_active)
                        ->action(function (Customer $record): void {
                            $record->update(['is_active' => false]);

                            Notification::make()
                                ->title(__('Customer marked as inactive'))
                                ->success()
                                ->send();
                        }),
                    EditAction::make(),
                ]),
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
