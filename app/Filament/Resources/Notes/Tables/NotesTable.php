<?php

namespace App\Filament\Resources\Notes\Tables;

use App\Models\Note;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class NotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('noted_at')
            ->columns([
                TextColumn::make('body')
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('noteable_type')
                    ->label('Linked To')
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? class_basename($state) : '-')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_pinned')
                    ->boolean()
                    ->label('Pinned')
                    ->sortable(),
                TextColumn::make('noted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->groups([
                Group::make('noted_at')
                    ->label('Noted On')
                    ->date(),
                Group::make('noteable_type')
                    ->label('Linked To')
                    ->getTitleFromRecordUsing(fn (Note $record): string => class_basename((string) $record->noteable_type)),
                Group::make('is_pinned')
                    ->label('Pinned')
                    ->getTitleFromRecordUsing(fn (Note $record): string => $record->is_pinned ? 'Pinned' : 'Regular'),
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
