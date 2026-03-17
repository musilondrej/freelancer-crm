<?php

namespace App\Filament\Resources\Tags\RelationManagers;

use App\Filament\Resources\Notes\Tables\NotesTable;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns(NotesTable::relationColumns())
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                AttachAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
