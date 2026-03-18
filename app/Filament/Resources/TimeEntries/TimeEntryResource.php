<?php

namespace App\Filament\Resources\TimeEntries;

use App\Enums\NavigationGroup;
use App\Filament\Resources\TimeEntries\Pages\CreateTimeEntry;
use App\Filament\Resources\TimeEntries\Pages\EditTimeEntry;
use App\Filament\Resources\TimeEntries\Pages\ListTimeEntries;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Filament\Resources\TimeEntries\Tables\TimeEntriesTable;
use App\Models\TimeEntry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'started_at';

    public static function getNavigationLabel(): string
    {
        return __('Reporting timesheets');
    }

    public static function getModelLabel(): string
    {
        return __('Time Entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Time Entries');
    }

    public static function form(Schema $schema): Schema
    {
        return TimeEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TimeEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTimeEntries::route('/'),
            'create' => CreateTimeEntry::route('/create'),
            'edit' => EditTimeEntry::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->when(Filament::auth()->id() !== null, fn (Builder $query): Builder => $query->where('owner_id', Filament::auth()->id()));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['task.project.customer', 'task.owner', 'owner'])
            ->when(Filament::auth()->id() !== null, fn (Builder $query): Builder => $query->where('owner_id', Filament::auth()->id()));
    }
}
