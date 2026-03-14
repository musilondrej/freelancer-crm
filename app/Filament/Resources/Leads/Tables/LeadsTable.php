<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadPipelineStage;
use App\Enums\LeadStatus;
use App\Models\Lead;
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

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Lead')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Lead $record): string => $record->company_name ?: ($record->email ?: '-')),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('pipeline_stage')
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        5 => 'Critical',
                        4 => 'High',
                        3 => 'Normal',
                        2 => 'Low',
                        default => 'Backlog',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estimated_value_with_currency')
                    ->label('Potential')
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('estimated_value', $direction)
                            ->orderBy('currency', $direction),
                    )
                    ->toggleable(),
                TextColumn::make('expected_close_date')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_activity_at')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('leadSource.name')
                    ->label('Source')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer.name')
                    ->label('Customer')
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
