<?php

namespace App\Filament\Resources\ProjectStatusOptions;

use App\Filament\Clusters\Configuration\ConfigurationCluster;
use App\Filament\Resources\ProjectStatusOptions\Pages\CreateProjectStatusOption;
use App\Filament\Resources\ProjectStatusOptions\Pages\EditProjectStatusOption;
use App\Filament\Resources\ProjectStatusOptions\Pages\ListProjectStatusOptions;
use App\Filament\Resources\ProjectStatusOptions\Schemas\ProjectStatusOptionForm;
use App\Filament\Resources\ProjectStatusOptions\Tables\ProjectStatusOptionsTable;
use App\Models\ProjectStatusOption;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectStatusOptionResource extends Resource
{
    protected static ?string $model = ProjectStatusOption::class;

    protected static ?string $cluster = ConfigurationCluster::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return ProjectStatusOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectStatusOptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Project Statuses';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectStatusOptions::route('/'),
            'create' => CreateProjectStatusOption::route('/create'),
            'edit' => EditProjectStatusOption::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return parent::getEloquentQuery()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
