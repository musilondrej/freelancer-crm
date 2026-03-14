<?php

namespace App\Filament\Resources\ProjectActivities;

use App\Filament\Resources\ProjectActivities\Pages\CreateProjectActivity;
use App\Filament\Resources\ProjectActivities\Pages\EditProjectActivity;
use App\Filament\Resources\ProjectActivities\Pages\ListProjectActivities;
use App\Filament\Resources\ProjectActivities\RelationManagers\NotesRelationManager;
use App\Filament\Resources\ProjectActivities\RelationManagers\TagsRelationManager;
use App\Filament\Resources\ProjectActivities\Schemas\ProjectActivityForm;
use App\Filament\Resources\ProjectActivities\Tables\ProjectActivitiesTable;
use App\Models\ProjectActivity;
use App\Models\ProjectActivityStatusOption;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProjectActivityResource extends Resource
{
    protected static ?string $model = ProjectActivity::class;

    protected static ?string $modelLabel = 'Worklog';

    protected static ?string $pluralModelLabel = 'Worklogs';

    protected static ?string $navigationLabel = 'Worklogs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Work Log';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ProjectActivityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectActivitiesTable::configure($table);
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
        $openStatuses = ProjectActivityStatusOption::openCodesForOwner($ownerId);

        $count = ProjectActivity::query()
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
            'index' => ListProjectActivities::route('/'),
            'create' => CreateProjectActivity::route('/create'),
            'edit' => EditProjectActivity::route('/{record}/edit'),
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
