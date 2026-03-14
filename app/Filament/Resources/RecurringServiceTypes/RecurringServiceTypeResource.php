<?php

namespace App\Filament\Resources\RecurringServiceTypes;

use App\Filament\Clusters\Configuration\ConfigurationCluster;
use App\Filament\Resources\RecurringServiceTypes\Pages\CreateRecurringServiceType;
use App\Filament\Resources\RecurringServiceTypes\Pages\EditRecurringServiceType;
use App\Filament\Resources\RecurringServiceTypes\Pages\ListRecurringServiceTypes;
use App\Filament\Resources\RecurringServiceTypes\Schemas\RecurringServiceTypeForm;
use App\Filament\Resources\RecurringServiceTypes\Tables\RecurringServiceTypesTable;
use App\Models\RecurringServiceType;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecurringServiceTypeResource extends Resource
{
    protected static ?string $model = RecurringServiceType::class;

    protected static ?string $modelLabel = 'Service category';

    protected static ?string $pluralModelLabel = 'Service categories';

    protected static ?string $navigationLabel = 'Service categories';

    protected static ?string $cluster = ConfigurationCluster::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

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
        $ownerId = Filament::auth()->id();

        return parent::getEloquentQuery()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return parent::getRecordRouteBindingEloquentQuery()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
