<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Leads\Tables\LeadsTable;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns(LeadsTable::relationColumns())
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
