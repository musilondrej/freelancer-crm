<?php

namespace App\Filament\Resources\RecurringServices;

use App\Enums\NavigationGroup;
use App\Enums\RecurringServiceStatus;
use App\Filament\Resources\RecurringServices\Pages\CreateRecurringService;
use App\Filament\Resources\RecurringServices\Pages\EditRecurringService;
use App\Filament\Resources\RecurringServices\Pages\ListRecurringServices;
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
    private const EXPIRING_SOON_DAYS = 14;

    protected static ?string $model = RecurringService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Projects;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('Recurring Services');
    }

    public static function getModelLabel(): string
    {
        return __('Recurring Service');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Recurring Services');
    }

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
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        $ownerId = Filament::auth()->id();
        $today = today()->toDateString();
        $expiresUntil = today()->addDays(self::EXPIRING_SOON_DAYS)->toDateString();

        $count = RecurringService::query()
            ->where('status', RecurringServiceStatus::Active)
            ->whereNotNull('ends_on')
            ->whereDate('ends_on', '>=', $today)
            ->whereDate('ends_on', '<=', $expiresUntil)
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() !== null ? 'danger' : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::getNavigationBadge() !== null
            ? sprintf('Recurring services ending within %d days.', self::EXPIRING_SOON_DAYS)
            : null;
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
