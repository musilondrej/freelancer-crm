<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
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
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('billing_currency')
                ->label('Currency')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('projects_count')
                ->label('Projects')
                ->counts('projects')
                ->sortable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Customer $record): ?string => $record->legal_name),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('projects_count')
                    ->label('Projects')
                    ->counts('projects')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('billing_currency')
                    ->label('Currency')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hourly_rate_with_currency')
                    ->label('Hourly rate')
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('hourly_rate', $direction)
                            ->orderBy('billing_currency', $direction),
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('leads_count')
                    ->label('Leads')
                    ->counts('leads')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('recurring_services_count')
                    ->label('Services')
                    ->counts('recurringServices')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('next_follow_up_at')
                    ->label('Follow-up')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->color(function (Customer $record): string {
                        $followUp = $record->getAttribute('next_follow_up_at');

                        if (! $followUp instanceof Carbon) {
                            return 'gray';
                        }

                        if ($followUp->isPast()) {
                            return 'danger';
                        }

                        return $followUp->isToday() ? 'warning' : 'gray';
                    })
                    ->toggleable(),
                TextColumn::make('last_contacted_at')
                    ->label('Last contact')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->options(CustomerStatus::class)
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->groups([
                Group::make('status')
                    ->label('Status'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('mark_active')
                        ->label('Mark active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Customer $record): bool => $record->getAttribute('status') !== CustomerStatus::Active)
                        ->action(function (Customer $record): void {
                            $record->update(['status' => CustomerStatus::Active]);

                            Notification::make()
                                ->title('Customer marked as active')
                                ->success()
                                ->send();
                        }),
                    Action::make('mark_inactive')
                        ->label('Mark inactive')
                        ->icon('heroicon-o-pause-circle')
                        ->color('gray')
                        ->visible(fn (Customer $record): bool => $record->getAttribute('status') !== CustomerStatus::Inactive)
                        ->action(function (Customer $record): void {
                            $record->update(['status' => CustomerStatus::Inactive]);

                            Notification::make()
                                ->title('Customer marked as inactive')
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
