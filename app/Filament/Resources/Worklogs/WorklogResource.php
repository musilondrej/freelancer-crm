<?php

namespace App\Filament\Resources\Worklogs;

use App\Filament\Resources\Worklogs\Pages\CreateWorklog;
use App\Filament\Resources\Worklogs\Pages\EditWorklog;
use App\Filament\Resources\Worklogs\Pages\ListWorklogs;
use App\Filament\Resources\Worklogs\Schemas\WorklogForm;
use App\Filament\Resources\Worklogs\Tables\WorklogsTable;
use App\Models\ProjectActivityStatusOption;
use App\Models\Worklog;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class WorklogResource extends Resource
{
    protected static ?string $model = Worklog::class;

    protected static ?string $modelLabel = 'Worklog';

    protected static ?string $pluralModelLabel = 'Worklogs';

    protected static ?string $navigationLabel = 'Worklogs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Work Log';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return WorklogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorklogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        $ownerId = Filament::auth()->id();
        $openStatuses = ProjectActivityStatusOption::openCodesForOwner($ownerId);

        $count = Worklog::query()
            ->whereIn('status', $openStatuses)
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Worklogs in open workflow statuses.';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorklogs::route('/'),
            'create' => CreateWorklog::route('/create'),
            'edit' => EditWorklog::route('/{record}/edit'),
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
