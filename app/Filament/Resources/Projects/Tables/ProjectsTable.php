<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\TimeEntry;
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

class ProjectsTable
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
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->sortable(),
            TextColumn::make('pricing_model')
                ->badge()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('target_end_date')
                ->date()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->addSelect([
                'actual_hours_minutes' => TimeEntry::query()
                    ->selectRaw('COALESCE(SUM(time_entries.minutes), 0)')
                    ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
                    ->whereColumn('tasks.project_id', 'projects.id')
                    ->whereNull('tasks.deleted_at')
                    ->whereNull('time_entries.deleted_at'),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('pricing_model')
                    ->label(__('Pricing Model'))
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estimated_value')
                    ->label(__('Budget'))
                    ->state(fn (Project $record): string => self::formatCurrencyAmount($record->estimated_value, $record->effectiveCurrency()))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('actual_value')
                    ->label(__('Spent'))
                    ->state(fn (Project $record): string => self::formatCurrencyAmount($record->actual_value, $record->effectiveCurrency()))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('estimated_hours')
                    ->label(__('Estimated hours'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('actual_hours_minutes')
                    ->label(__('Actual hours'))
                    ->state(fn (Project $record): float => round(((float) ($record->actual_hours_minutes ?? 0)) / 60, 2))
                    ->numeric(decimalPlaces: 2)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('actual_hours_minutes', $direction))
                    ->toggleable(),
                TextColumn::make('currency')
                    ->label(__('Currency'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ProjectStatus::class)
                    ->multiple(),
                SelectFilter::make('client_id')
                    ->relationship('customer', 'name')
                    ->label('Customer')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->groups([
                Group::make('customer.name')
                    ->label('Customer'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('mark_completed')
                        ->label('Mark completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Project $record): bool => $record->status->isOpen())
                        ->action(function (Project $record): void {
                            $record->update([
                                'status' => ProjectStatus::Completed,
                                'closed_date' => $record->closed_date ?? now(),
                            ]);

                            Notification::make()
                                ->title('Project marked as completed')
                                ->success()
                                ->send();
                        }),
                    Action::make('mark_blocked')
                        ->label('Mark blocked')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->visible(fn (Project $record): bool => $record->status === ProjectStatus::InProgress)
                        ->action(function (Project $record): void {
                            $record->update([
                                'status' => ProjectStatus::Blocked,
                            ]);

                            Notification::make()
                                ->title('Project marked as blocked')
                                ->warning()
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

    private static function formatCurrencyAmount(mixed $amount, ?string $currency): string
    {
        if ($amount === null) {
            return __('N/A');
        }

        if ($currency === null || trim($currency) === '') {
            return number_format((float) $amount, 2, '.', ' ');
        }

        return number_format((float) $amount, 2, '.', ' ').' '.$currency;
    }
}
