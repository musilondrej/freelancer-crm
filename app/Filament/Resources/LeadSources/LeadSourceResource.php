<?php

namespace App\Filament\Resources\LeadSources;

use App\Filament\Clusters\Configuration\ConfigurationCluster;
use App\Filament\Resources\LeadSources\Pages\CreateLeadSource;
use App\Filament\Resources\LeadSources\Pages\EditLeadSource;
use App\Filament\Resources\LeadSources\Pages\ListLeadSources;
use App\Filament\Resources\LeadSources\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\LeadSources\Schemas\LeadSourceForm;
use App\Filament\Resources\LeadSources\Tables\LeadSourcesTable;
use App\Models\LeadSource;
use App\Support\Filament\FilteredByOwner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadSourceResource extends Resource
{
    protected static ?string $model = LeadSource::class;

    protected static ?string $cluster = ConfigurationCluster::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LeadSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadSourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LeadsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadSources::route('/'),
            'create' => CreateLeadSource::route('/create'),
            'edit' => EditLeadSource::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return FilteredByOwner::applyTo(parent::getEloquentQuery());
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return FilteredByOwner::applyTo(parent::getRecordRouteBindingEloquentQuery());
    }
}
