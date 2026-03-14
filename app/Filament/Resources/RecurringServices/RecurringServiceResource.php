<?php

namespace App\Filament\Resources\RecurringServices;

use App\Enums\RecurringServiceStatus;
use App\Filament\Resources\RecurringServices\Pages\CreateRecurringService;
use App\Filament\Resources\RecurringServices\Pages\EditRecurringService;
use App\Filament\Resources\RecurringServices\Pages\ListRecurringServices;
use App\Filament\Resources\RecurringServices\RelationManagers\NotesRelationManager;
use App\Filament\Resources\RecurringServices\RelationManagers\TagsRelationManager;
use App\Filament\Resources\RecurringServices\Schemas\RecurringServiceForm;
use App\Filament\Resources\RecurringServices\Tables\RecurringServicesTable;
use App\Models\RecurringService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RecurringServiceResource extends Resource
{
    protected static ?string $model = RecurringService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Work Log';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RecurringServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringServicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
            TagsRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $ownerId = Filament::auth()->id();

        $count = RecurringService::query()
            ->where('status', RecurringServiceStatus::Active)
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Active recurring services.';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringServices::route('/'),
            'create' => CreateRecurringService::route('/create'),
            'edit' => EditRecurringService::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return parent::getEloquentQuery()
            ->with(['serviceType'])
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
