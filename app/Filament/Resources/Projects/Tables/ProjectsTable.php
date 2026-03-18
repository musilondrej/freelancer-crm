<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Models\Project;
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
                TextColumn::make('pricing_model')
                    ->label(__('Pricing Model'))
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('customer_id')
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
}
