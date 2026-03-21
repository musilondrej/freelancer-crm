<?php

namespace App\Filament\Resources\RecurringServiceTypes;

use App\Filament\Clusters\Configuration\ConfigurationCluster;
use App\Filament\Resources\RecurringServiceTypes\Pages\CreateRecurringServiceType;
use App\Filament\Resources\RecurringServiceTypes\Pages\EditRecurringServiceType;
use App\Filament\Resources\RecurringServiceTypes\Pages\ListRecurringServiceTypes;
use App\Filament\Resources\RecurringServiceTypes\Schemas\RecurringServiceTypeForm;
use App\Filament\Resources\RecurringServiceTypes\Tables\RecurringServiceTypesTable;
use App\Models\RecurringServiceType;
use App\Support\Filament\FilteredByOwner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecurringServiceTypeResource extends Resource
{
    protected static ?string $model = RecurringServiceType::class;

    protected static ?string $cluster = ConfigurationCluster::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('Recurring Service Types');
    }

    public static function getModelLabel(): string
    {
        return __('Recurring Service Type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Recurring Service Types');
    }

    public static function form(Schema $schema): Schema
    {
        return RecurringServiceTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringServiceTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringServiceTypes::route('/'),
            'create' => CreateRecurringServiceType::route('/create'),
            'edit' => EditRecurringServiceType::route('/{record}/edit'),
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
