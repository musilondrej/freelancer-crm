<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\RecurringServices\Tables\RecurringServicesTable;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RecurringServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringServices';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns(RecurringServicesTable::relationColumns())
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                ]),
            ]);
    }
}
