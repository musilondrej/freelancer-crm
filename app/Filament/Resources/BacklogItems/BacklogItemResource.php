<?php

namespace App\Filament\Resources\BacklogItems;

use App\Filament\Resources\BacklogItems\Pages\CreateBacklogItem;
use App\Filament\Resources\BacklogItems\Pages\EditBacklogItem;
use App\Filament\Resources\BacklogItems\Pages\ListBacklogItems;
use App\Filament\Resources\BacklogItems\Schemas\BacklogItemForm;
use App\Filament\Resources\BacklogItems\Tables\BacklogItemsTable;
use App\Models\BacklogItem;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BacklogItemResource extends Resource
{
    protected static ?string $model = BacklogItem::class;

    protected static ?string $modelLabel = 'Backlog item';

    protected static ?string $pluralModelLabel = 'Backlog';

    protected static ?string $navigationLabel = 'Backlog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Work Log';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return BacklogItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BacklogItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $ownerId = Filament::auth()->id();

        $count = BacklogItem::query()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
            ->whereNull('converted_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Open backlog items waiting for execution.';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBacklogItems::route('/'),
            'create' => CreateBacklogItem::route('/create'),
            'edit' => EditBacklogItem::route('/{record}/edit'),
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
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
