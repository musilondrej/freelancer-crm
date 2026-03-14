<?php

namespace App\Filament\Resources\ProjectActivityStatusOptions;

use App\Filament\Clusters\Configuration\ConfigurationCluster;
use App\Filament\Resources\ProjectActivityStatusOptions\Pages\CreateProjectActivityStatusOption;
use App\Filament\Resources\ProjectActivityStatusOptions\Pages\EditProjectActivityStatusOption;
use App\Filament\Resources\ProjectActivityStatusOptions\Pages\ListProjectActivityStatusOptions;
use App\Filament\Resources\ProjectActivityStatusOptions\Schemas\ProjectActivityStatusOptionForm;
use App\Filament\Resources\ProjectActivityStatusOptions\Tables\ProjectActivityStatusOptionsTable;
use App\Models\ProjectActivityStatusOption;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectActivityStatusOptionResource extends Resource
{
    protected static ?string $model = ProjectActivityStatusOption::class;

    protected static ?string $cluster = ConfigurationCluster::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return ProjectActivityStatusOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectActivityStatusOptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Worklog Statuses';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectActivityStatusOptions::route('/'),
            'create' => CreateProjectActivityStatusOption::route('/create'),
            'edit' => EditProjectActivityStatusOption::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return parent::getEloquentQuery()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
