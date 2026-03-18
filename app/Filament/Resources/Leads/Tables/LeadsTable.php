<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadPipelineStage;
use App\Enums\LeadStatus;
use App\Enums\Priority;
use App\Models\Lead;
use App\Models\UserSetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    /**
     * @return list<TextColumn>
     */
    public static function relationColumns(): array
    {
        return [
            TextColumn::make('full_name')
                ->label(__('Lead'))
                ->searchable()
                ->sortable()
                ->description(fn (Lead $record): string => $record->company_name ?: ($record->email ?: '-')),
            TextColumn::make('status')
                ->badge()
                ->label(__('Status'))
                ->sortable(),
            TextColumn::make('pipeline_stage')
                ->badge()
                ->label(__('Pipeline stage'))
                ->sortable(),
            TextColumn::make('estimated_value_with_currency')
                ->label(__('Potential'))
                ->sortable(
                    query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('estimated_value', $direction)
                        ->orderBy('currency', $direction),
                )
                ->toggleable(),
            TextColumn::make('last_activity_at')
                ->label(__('Last activity'))
                ->since()
                ->sortable()
                ->toggleable(),
        ];
    }

    public static function configure(Table $table): Table
    {
        $ownerId = Filament::auth()->id();
        $dateFormat = UserSetting::dateFormatForUser($ownerId);

        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('Lead'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Lead $record): string => $record->company_name ?: ($record->email ?: '-')),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('Status'))
                    ->sortable(),
                TextColumn::make('pipeline_stage')
                    ->label(__('Pipeline stage'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (Priority|int|null $state): string => $state instanceof Priority ? $state->getLabel() : (Priority::tryFrom((int) $state) ?? Priority::Backlog)->getLabel())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estimated_value_with_currency')
                    ->label(__('Potential'))
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('estimated_value', $direction)
                            ->orderBy('currency', $direction),
                    )
                    ->toggleable(),
                TextColumn::make('expected_close_date')
                    ->date($dateFormat)
                    ->label(__('Expected close date'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_activity_at')
                    ->label(__('Last activity'))
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('leadSource.name')
                    ->label(__('Source'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeadStatus::class)
                    ->multiple(),
                SelectFilter::make('pipeline_stage')
                    ->options(LeadPipelineStage::class)
                    ->multiple(),
                SelectFilter::make('lead_source_id')
                    ->relationship('leadSource', 'name')
                    ->label('Source')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->label('Customer')
                    ->searchable()
                    ->preload(),
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
