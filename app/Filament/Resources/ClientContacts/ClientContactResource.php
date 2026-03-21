<?php

namespace App\Filament\Resources\ClientContacts;

use App\Filament\Resources\ClientContacts\RelationManagers\NotesRelationManager;
use App\Filament\Resources\ClientContacts\RelationManagers\PrimaryProjectsRelationManager;
use App\Filament\Resources\ClientContacts\Schemas\ClientContactForm;
use App\Filament\Resources\ClientContacts\Tables\ClientContactsTable;
use App\Models\ClientContact;
use App\Support\Filament\FilteredByOwner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ClientContactResource extends Resource
{
    protected static ?string $model = ClientContact::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return ClientContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientContactsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PrimaryProjectsRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [];
    }

    public static function canGloballySearch(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return FilteredByOwner::applyTo(parent::getEloquentQuery());
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return FilteredByOwner::applyTo(
            parent::getRecordRouteBindingEloquentQuery()
                ->withoutGlobalScopes([SoftDeletingScope::class])
        );
    }
}
